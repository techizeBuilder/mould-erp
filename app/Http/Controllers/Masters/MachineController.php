<?php

namespace App\Http\Controllers\Masters;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Masters\Concerns\HandlesMasterForms;
use App\Models\Machine;
use App\Models\Mould;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class MachineController extends Controller
{
    use HandlesMasterForms;

    public function index(Request $request)
    {
        $machines = Machine::withCount('moulds')
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%'.$request->search.'%';
                $q->where(fn ($q) => $q->where('code', 'like', $term)->orWhere('name', 'like', $term)
                    ->orWhere('make', 'like', $term)->orWhere('model_no', 'like', $term)->orWhere('location', 'like', $term));
            })
            ->when($request->filled('type'), fn ($q) => $q->where('machine_type', $request->type))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('masters.machines.index', ['machines' => $machines]);
    }

    public function show(Machine $machine)
    {
        $machine->load(['moulds' => fn ($q) => $q->withCount('products')->orderBy('code'), 'creator:id,name']);

        return view('masters.machines.show', ['machine' => $machine]);
    }

    public function create()
    {
        return view('masters.machines.form', [
            'machine' => new Machine(['status' => 'active']),
            'nextCode' => Machine::nextCode(),
            'moulds' => $this->mouldOptions(),
            'selected' => [],
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $machine = DB::transaction(function () use ($request, $data) {
            $machine = new Machine($data);
            $machine->created_by = $request->user()->id;
            $mouldIds = $this->compatibleMouldIds($request, $machine);
            $machine->save();
            $machine->moulds()->sync($mouldIds);

            return $machine;
        });

        return redirect()->route('masters.machines.show', $machine)->with('success', "Machine \"{$machine->code} – {$machine->name}\" created.");
    }

    public function edit(Machine $machine)
    {
        return view('masters.machines.form', [
            'machine' => $machine,
            'nextCode' => null,
            'moulds' => $this->mouldOptions(),
            'selected' => $machine->moulds()->pluck('moulds.id')->all(),
        ]);
    }

    public function update(Request $request, Machine $machine)
    {
        $data = $this->validated($request);

        DB::transaction(function () use ($request, $machine, $data) {
            $machine->fill($data);
            $mouldIds = $this->compatibleMouldIds($request, $machine);
            $machine->save();
            $machine->moulds()->sync($mouldIds);
        });

        return redirect()->route('masters.machines.show', $machine)->with('success', "Machine \"{$machine->code}\" updated.");
    }

    public function destroy(Machine $machine)
    {
        $machine->delete();

        return redirect()->route('masters.machines.index')->with('success', "Machine \"{$machine->code}\" deleted.");
    }

    protected function mouldOptions()
    {
        return Mould::orderBy('mould_type')->orderBy('code')->get();
    }

    /** Selected moulds, rejecting any whose type can't run on this machine. */
    protected function compatibleMouldIds(Request $request, Machine $machine): array
    {
        $moulds = Mould::whereIn('id', $this->ids($request, 'mould_ids'))->get();

        $wrong = $moulds->reject(fn (Mould $m) => $m->fitsMachine($machine));
        if ($wrong->isNotEmpty()) {
            $this->fail('mould_ids', 'These moulds cannot run on a '.($machine->typeLabel() ?? 'machine of this type').': '
                .$wrong->pluck('code')->join(', ').'. Preform moulds go on preform machines, blow moulds on blow moulding machines.');
        }

        return $moulds->modelKeys();
    }

    protected function validated(Request $request): array
    {
        $request->validate([
            'machine_type' => ['required', Rule::in(array_keys(Machine::TYPES))],
            'make' => ['nullable', 'string', 'max:100'],
            'model_no' => ['nullable', 'string', 'max:100'],
            'tonnage' => ['nullable', 'numeric', 'gt:0', 'max:100000'],
            'cavities' => ['nullable', 'integer', 'min:1', 'max:1000'],
            ...$this->capacityRules('min_size'),
            ...$this->capacityRules('max_size'),
            'location' => ['nullable', 'string', 'max:100'],
            'status' => ['required', Rule::in(array_keys(Machine::STATUSES))],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'mould_ids' => ['nullable', 'array'],
            'mould_ids.*' => ['integer', 'exists:moulds,id'],
        ], [], [
            'machine_type' => 'machine type',
            'min_size_value' => 'minimum bottle size',
            'max_size_value' => 'maximum bottle size',
        ]);

        $min = $this->capacityFromRequest($request, 'min_size');
        $max = $this->capacityFromRequest($request, 'max_size');
        if ($min !== null && $max !== null && $max < $min) {
            $this->fail('max_size_value', 'The maximum bottle size must be at least the minimum.');
        }

        // The name isn't entered: Machine generates it from these specs when saved.
        return $request->only(['machine_type', 'make', 'model_no', 'tonnage', 'cavities', 'location', 'status', 'remarks'])
            + ['min_capacity_ml' => $min, 'max_capacity_ml' => $max];
    }
}

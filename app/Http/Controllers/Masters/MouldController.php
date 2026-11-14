<?php

namespace App\Http\Controllers\Masters;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Masters\Concerns\HandlesMasterForms;
use App\Models\Machine;
use App\Models\Mould;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class MouldController extends Controller
{
    use HandlesMasterForms;

    public function index(Request $request)
    {
        $moulds = Mould::withCount(['machines', 'products'])
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%'.$request->search.'%';
                $q->where(fn ($q) => $q->where('code', 'like', $term)->orWhere('name', 'like', $term)->orWhere('neck_type', 'like', $term)->orWhere('location', 'like', $term));
            })
            ->when($request->filled('type'), fn ($q) => $q->where('mould_type', $request->type))
            ->when($request->filled('shape'), fn ($q) => $q->where('shape', $request->shape))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('masters.moulds.index', ['moulds' => $moulds]);
    }

    public function show(Mould $mould)
    {
        $mould->load([
            'machines' => fn ($q) => $q->orderBy('code'),
            'products' => fn ($q) => $q->orderBy('code'),
            'creator:id,name',
            'neck',
        ]);

        return view('masters.moulds.show', ['mould' => $mould]);
    }

    public function create()
    {
        return view('masters.moulds.form', [
            'mould' => new Mould(['cavities' => 1, 'status' => 'active']),
            'nextCode' => Mould::nextCode(),
            'machines' => $this->machineOptions(),
            'selected' => [],
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $mould = DB::transaction(function () use ($request, $data) {
            $mould = new Mould($data);
            $mould->created_by = $request->user()->id;
            $machineIds = $this->compatibleMachineIds($request, $mould);
            $this->syncImage($request, $mould, 'moulds');
            $mould->save();
            $mould->machines()->sync($machineIds);

            return $mould;
        });

        return redirect()->route('masters.moulds.show', $mould)->with('success', "Mould \"{$mould->code} – {$mould->name}\" created.");
    }

    public function edit(Mould $mould)
    {
        return view('masters.moulds.form', [
            'mould' => $mould,
            'nextCode' => null,
            'machines' => $this->machineOptions(),
            'selected' => $mould->machines()->pluck('machines.id')->all(),
        ]);
    }

    public function update(Request $request, Mould $mould)
    {
        $data = $this->validated($request);

        DB::transaction(function () use ($request, $mould, $data) {
            $mould->fill($data);
            $machineIds = $this->compatibleMachineIds($request, $mould);
            $this->guardLinkedProducts($mould);
            $this->syncImage($request, $mould, 'moulds');
            $mould->save();
            $mould->machines()->sync($machineIds);
        });

        return redirect()->route('masters.moulds.show', $mould)->with('success', "Mould \"{$mould->code}\" updated.");
    }

    public function destroy(Mould $mould)
    {
        $linked = $mould->products()->count();
        $mould->delete();

        return redirect()->route('masters.moulds.index')->with('success', "Mould \"{$mould->code}\" deleted."
            .($linked ? " It was removed from {$linked} product(s)." : ''));
    }

    protected function machineOptions()
    {
        return Machine::orderBy('machine_type')->orderBy('code')->get();
    }

    /** Selected machines, rejecting any this type of mould can't be fitted on. */
    protected function compatibleMachineIds(Request $request, Mould $mould): array
    {
        $machines = Machine::whereIn('id', $this->ids($request, 'machine_ids'))->get();

        $wrong = $machines->reject(fn (Machine $m) => $mould->fitsMachine($m));
        if ($wrong->isNotEmpty()) {
            $this->fail('machine_ids', 'A '.strtolower($mould->typeLabel()).' cannot be fitted on: '.$wrong->pluck('code')->join(', ')
                .'. Preform moulds go on preform machines, blow moulds on blow moulding machines.');
        }

        return $machines->modelKeys();
    }

    /** Changing a mould's type must not leave it linked to products that can't use it. */
    protected function guardLinkedProducts(Mould $mould): void
    {
        if (! $mould->isDirty('mould_type')) {
            return;
        }

        $clash = $mould->products()->get()
            ->reject(fn (Product $p) => in_array($mould->mould_type, Product::MOULD_TYPES_FOR[$p->product_type] ?? [], true));

        if ($clash->isNotEmpty()) {
            $this->fail('mould_type', 'This mould is used by '.$clash->pluck('code')->join(', ')
                .', which cannot use a '.strtolower($mould->typeLabel()).'. Remove it from those products first.');
        }
    }

    protected function validated(Request $request): array
    {
        $request->validate([
            'mould_type' => ['required', Rule::in(array_keys(Mould::TYPES))],
            ...$this->shapeRules(),
            ...$this->capacityRules('capacity'),
            'weight_grams' => ['nullable', 'numeric', 'gt:0', 'max:100000'],
            'cavities' => ['required', 'integer', 'min:1', 'max:1000'],
            'location' => ['nullable', 'string', 'max:100'],
            'status' => ['required', Rule::in(array_keys(Mould::STATUSES))],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'machine_ids' => ['nullable', 'array'],
            'machine_ids.*' => ['integer', 'exists:machines,id'],
            ...$this->imageRules(),
        ], [], [
            'mould_type' => 'mould type',
            'capacity_value' => 'bottle / jar volume',
            'weight_grams' => 'weight',
            'new_shape' => 'new shape',
        ]);

        // The name isn't entered: Mould generates it from these specs when saved.
        return $request->only(['mould_type', 'cavities', 'location', 'status', 'remarks'])
            + [
                'capacity_ml' => $this->capacityFromRequest($request, 'capacity'),
                'shape' => $this->shapeFromRequest($request),
                'neck_id' => $this->neckFromRequest($request),
                'neck_size_mm' => null,
                'neck_height_mm' => null,
                'neck_type' => null,
                'weight_grams' => $request->input('weight_grams') ?: null,
            ];
    }
}

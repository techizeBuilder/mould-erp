<?php

namespace App\Http\Controllers\Masters;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Masters\Concerns\HandlesMasterForms;
use App\Models\Neck;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NeckController extends Controller
{
    use HandlesMasterForms;

    public function index(Request $request)
    {
        $necks = Neck::withCount(['moulds', 'products'])
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%'.$request->search.'%';
                $q->where(fn ($q) => $q->where('code', 'like', $term)->orWhere('name', 'like', $term)->orWhere('neck_type', 'like', $term));
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->orderBy('code')
            ->paginate(20)
            ->withQueryString();

        return view('masters.necks.index', ['necks' => $necks]);
    }

    public function show(Neck $neck)
    {
        return view('masters.necks.show', [
            'neck' => $neck->load([
                'moulds' => fn ($q) => $q->orderBy('code'),
                'products' => fn ($q) => $q->orderBy('code'),
                'creator:id,name',
            ]),
        ]);
    }

    public function create()
    {
        return view('masters.necks.form', ['neck' => new Neck(['status' => 'active']), 'nextCode' => Neck::nextCode(), 'locked' => false]);
    }

    public function store(Request $request)
    {
        $neck = new Neck($this->validated($request));
        $neck->created_by = $request->user()->id;
        $neck->save();

        return redirect()->route('masters.necks.show', $neck)->with('success', "Neck \"{$neck->code} – {$neck->name}\" created.");
    }

    public function edit(Neck $neck)
    {
        return view('masters.necks.form', ['neck' => $neck, 'nextCode' => null, 'locked' => $neck->isUsed()]);
    }

    public function update(Request $request, Neck $neck)
    {
        $neck->update($this->validated($request, $neck));

        return redirect()->route('masters.necks.show', $neck)->with('success', "Neck \"{$neck->code}\" updated.");
    }

    public function destroy(Neck $neck)
    {
        if ($neck->isUsed()) {
            return back()->with('error', "{$neck->code} is used by moulds or products, so it can't be deleted. Set it to Inactive instead.");
        }

        $neck->delete();

        return redirect()->route('masters.necks.index')->with('success', "Neck \"{$neck->code}\" deleted.");
    }

    protected function validated(Request $request, ?Neck $neck = null): array
    {
        $rules = ['status' => ['required', Rule::in(array_keys(Neck::STATUSES))], 'remarks' => ['nullable', 'string', 'max:2000']];

        // A neck in use keeps its parameters: only its status and remarks can change.
        if ($neck?->isUsed()) {
            return $request->validate($rules);
        }

        $data = $request->validate($rules + $this->neckSpecRules(), [], $this->neckSpecAttributes());

        $twin = Neck::findMatching($data, $neck?->id);
        if ($twin) {
            $this->fail('neck_size_mm', "This neck already exists as {$twin->code}.");
        }

        return $data + array_fill_keys(Neck::SPEC_FIELDS, null);
    }
}

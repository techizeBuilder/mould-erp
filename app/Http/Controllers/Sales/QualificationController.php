<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\BuyerQualification;
use App\Models\Lead;
use App\Support\BuyerScore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class QualificationController extends Controller
{
    public function index(Request $request)
    {
        $decision = $request->input('decision');

        $leads = Lead::with(['items', 'qualification.assessor:id,name'])
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%'.$request->search.'%';
                $q->where(fn ($q) => $q->where('code', 'like', $term)->orWhere('company_name', 'like', $term)->orWhere('phone', 'like', $term));
            })
            ->when($request->input('scope', 'open') === 'open', fn ($q) => $q->open())
            ->when($decision === 'pending', fn ($q) => $q->where(fn ($q) => $q->doesntHave('qualification')->orWhereHas('qualification', fn ($r) => $r->where('decision', 'pending'))))
            ->when($decision && $decision !== 'pending', fn ($q) => $q->whereHas('qualification', fn ($r) => $r->where('decision', $decision)))
            ->when($request->filled('grade'), fn ($q) => $q->whereHas('qualification', fn ($r) => $r->where('grade', $request->grade)))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('sales.qualification.index', [
            'leads' => $leads,
            'counts' => [
                'pending' => Lead::open()->where(fn ($q) => $q->doesntHave('qualification')->orWhereHas('qualification', fn ($r) => $r->where('decision', 'pending')))->count(),
                'qualified' => BuyerQualification::where('decision', 'qualified')->count(),
                'nurture' => BuyerQualification::where('decision', 'nurture')->count(),
                'disqualified' => BuyerQualification::where('decision', 'disqualified')->count(),
            ],
        ]);
    }

    public function edit(Lead $lead)
    {
        $lead->load(['items.product', 'qualification.assessor:id,name']);

        return view('sales.qualification.form', [
            'lead' => $lead,
            'qualification' => $lead->qualification ?? new BuyerQualification([
                'monthly_volume' => $lead->estimatedMonthlyVolume(),
                'mould_fit' => $lead->suggestedMouldFit(),
                'buyer_type' => 'brand_owner',
                'price_fit' => 'unknown',
                'decision_authority' => 'unknown',
                'purchase_timeline' => 'one_to_three',
                'payment_terms' => 'unknown',
                'sample_required' => true,
                'decision' => 'pending',
            ]),
        ]);
    }

    public function update(Request $request, Lead $lead)
    {
        $existing = $lead->qualification;
        abort_unless($request->user()->canModule('sales_qualification', $existing ? 'edit' : 'create'), 403);

        $criteria = collect(BuyerScore::CRITERIA)->map(fn ($c) => ['required', Rule::in(array_keys($c['options']))])->all();

        $data = $request->validate($criteria + [
            'monthly_volume' => ['required', 'integer', 'min:0', 'max:1000000000'],
            'gst_verified' => ['nullable', 'boolean'],
            'sample_required' => ['nullable', 'boolean'],
            'compliance_required' => ['nullable', 'string', 'max:150'],
            'current_supplier' => ['nullable', 'string', 'max:150'],
            'decision' => ['required', Rule::in(array_keys(BuyerQualification::DECISIONS))],
            'decision_reason' => ['nullable', 'required_if:decision,disqualified', 'string', 'max:500'],
        ], [
            'decision_reason.required_if' => 'Say why this buyer is disqualified.',
        ], [
            'monthly_volume' => 'monthly volume',
            'decision_reason' => 'reason',
        ]);

        if ($data['decision'] === 'qualified' && $data['mould_fit'] === 'not_feasible') {
            return back()->withInput()->withErrors(['decision' => "A product that can't be made can't be a qualified buyer. Change the mould fit, or disqualify."]);
        }
        if ($data['decision'] === 'disqualified' && $lead->quotations()->open()->exists()) {
            return back()->withInput()->withErrors(['decision' => 'This lead has an open quotation. Mark that quotation Lost first.']);
        }

        DB::transaction(function () use ($request, $lead, $data, $existing) {
            $qualification = $existing ?? new BuyerQualification(['lead_id' => $lead->id]);
            $qualification->lead_id = $lead->id;
            $qualification->fill($data + ['gst_verified' => false, 'sample_required' => false]);
            $qualification->gst_verified = $request->boolean('gst_verified');
            $qualification->sample_required = $request->boolean('sample_required');
            $qualification->qualified_by = $request->user()->id;
            $qualification->save();

            $lead->applyQualificationDecision($data['decision']);
        });

        return redirect()->route('sales.leads.show', $lead)->with('success', "Qualification for \"{$lead->company_name}\" saved.");
    }

    public function destroy(Lead $lead)
    {
        DB::transaction(function () use ($lead) {
            $lead->qualification?->delete();
            $lead->applyQualificationDecision('pending');
        });

        return redirect()->route('sales.qualification.index')->with('success', "Qualification for \"{$lead->company_name}\" removed.");
    }
}

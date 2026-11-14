<?php

namespace App\Http\Controllers\Accounts;

use App\Http\Controllers\Controller;
use App\Models\Ledger;
use App\Models\VoucherLine;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class LedgerController extends Controller
{
    public function index(Request $request)
    {
        $ledgers = Ledger::query()
            ->withTotals()
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%'.$request->search.'%';
                $q->where(fn ($q) => $q->where('code', 'like', $term)->orWhere('name', 'like', $term)->orWhere('phone', 'like', $term)->orWhere('gstin', 'like', $term));
            })
            ->when($request->filled('group'), fn ($q) => $q->where('ledger_group', $request->group))
            ->where('status', $request->input('status', 'active'))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        $sum = fn (array $groups) => Ledger::whereIn('ledger_group', $groups)->withTotals()->get()->sum(fn (Ledger $l) => $l->closing());

        return view('accounts.ledgers.index', [
            'ledgers' => $ledgers,
            'stats' => [
                'money' => $sum(Ledger::CASH_GROUPS),
                'receivable' => $sum(['debtors']),
                'payable' => -$sum(['creditors']),
            ],
        ]);
    }

    /** The ledger's statement for a period, opening with the balance brought forward. */
    public function show(Request $request, Ledger $ledger)
    {
        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        // Defaults to the current financial year (April to March).
        $today = today();
        $from = Carbon::parse($data['from'] ?? $today->copy()->subMonths(3)->startOfYear()->addMonths(3));
        $to = Carbon::parse($data['to'] ?? $today);

        $lines = VoucherLine::query()
            ->select('voucher_lines.*')
            ->join('vouchers', 'vouchers.id', '=', 'voucher_lines.voucher_id')
            ->where('ledger_id', $ledger->id)
            ->whereDate('vouchers.voucher_date', '>=', $from)
            ->whereDate('vouchers.voucher_date', '<=', $to)
            ->orderBy('vouchers.voucher_date')
            ->orderBy('vouchers.id')
            ->with('voucher.lines.ledger:id,name')
            ->get();

        $opening = $ledger->balanceBefore($from);
        $running = $opening;
        $rows = $lines->map(function (VoucherLine $line) use (&$running) {
            $running = round($running + (float) $line->debit - (float) $line->credit, 2);

            return ['line' => $line, 'balance' => $running];
        });

        return view('accounts.ledgers.show', [
            'ledger' => $ledger->load('customer', 'creator:id,name'),
            'from' => $from,
            'to' => $to,
            'opening' => $opening,
            'rows' => $rows,
            'closing' => $running,
            'totalDebit' => (float) $lines->sum('debit'),
            'totalCredit' => (float) $lines->sum('credit'),
        ]);
    }

    public function create(Request $request)
    {
        return view('accounts.ledgers.form', [
            'ledger' => new Ledger([
                'status' => 'active',
                'opening_type' => 'dr',
                'ledger_group' => array_key_exists($request->input('group'), Ledger::GROUPS) ? $request->input('group') : null,
            ]),
            'nextCode' => Ledger::nextCode(),
        ]);
    }

    public function store(Request $request)
    {
        $ledger = new Ledger($this->validated($request));
        $ledger->created_by = $request->user()->id;
        $ledger->save();

        return redirect()->route('accounts.ledgers.show', $ledger)->with('success', "Ledger \"{$ledger->code} – {$ledger->name}\" created.");
    }

    public function edit(Ledger $ledger)
    {
        return view('accounts.ledgers.form', ['ledger' => $ledger, 'nextCode' => null]);
    }

    public function update(Request $request, Ledger $ledger)
    {
        $data = $this->validated($request, $ledger);

        // A customer's details live on the customer, and vouchers depend on a built-in ledger's group.
        $data = match (true) {
            $ledger->isCustomerLedger() => collect($data)->only(['opening_balance', 'opening_type', 'pan', 'remarks', 'status'])->all(),
            $ledger->isSystem() => collect($data)->except(['name', 'ledger_group', 'status'])->all(),
            default => $data,
        };

        $ledger->update($data);

        return redirect()->route('accounts.ledgers.show', $ledger)->with('success', "Ledger \"{$ledger->code}\" updated.");
    }

    public function destroy(Ledger $ledger)
    {
        if ($ledger->isLocked()) {
            return back()->with('error', $ledger->isSystem()
                ? "\"{$ledger->name}\" is a built-in ledger that vouchers post to, so it can't be deleted."
                : "\"{$ledger->name}\" belongs to a customer. Set the ledger to Inactive instead.");
        }
        if ($ledger->lines()->exists()) {
            return back()->with('error', "\"{$ledger->name}\" has vouchers, so it can't be deleted. Set it to Inactive instead.");
        }

        $ledger->delete();

        return redirect()->route('accounts.ledgers.index')->with('success', "Ledger \"{$ledger->code}\" deleted.");
    }

    protected function validated(Request $request, ?Ledger $ledger = null): array
    {
        $locked = $ledger?->isLocked();

        $request->validate([
            'name' => [$locked ? 'nullable' : 'required', 'string', 'max:255', Rule::unique('ledgers', 'name')->ignore($ledger)],
            'ledger_group' => [$locked ? 'nullable' : 'required', Rule::in(array_keys(Ledger::GROUPS))],
            'opening_balance' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'opening_type' => ['required', 'in:dr,cr'],
            'contact_person' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^[0-9+\-\s()]{7,20}$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'state' => ['nullable', 'string', 'max:100'],
            'gstin' => ['nullable', 'regex:/^[0-9]{2}[A-Za-z]{5}[0-9]{4}[A-Za-z][0-9A-Za-z][Zz][0-9A-Za-z]$/'],
            'pan' => ['nullable', 'regex:/^[A-Za-z]{5}[0-9]{4}[A-Za-z]$/'],
            'bank_name' => ['nullable', 'string', 'max:100'],
            'account_number' => ['nullable', 'string', 'max:30', 'regex:/^[0-9A-Za-z]+$/'],
            'ifsc' => ['nullable', 'regex:/^[A-Za-z]{4}0[A-Za-z0-9]{6}$/'],
            'status' => ['required', Rule::in(array_keys(Ledger::STATUSES))],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ], [
            'name.unique' => 'A ledger with this name already exists.',
            'gstin.regex' => 'GSTIN must look like 27ABCDE1234F1Z5.',
            'pan.regex' => 'PAN must look like ABCDE1234F.',
            'ifsc.regex' => 'IFSC must look like SBIN0001234.',
            'phone.regex' => 'Enter a valid phone number.',
        ], [
            'ledger_group' => 'ledger type',
            'opening_balance' => 'opening balance',
        ]);

        $data = $request->only(['name', 'ledger_group', 'opening_type', 'contact_person', 'phone', 'email', 'address', 'state', 'bank_name', 'account_number', 'status', 'remarks']) + [
            'opening_balance' => $request->input('opening_balance') ?: 0,
            'gstin' => $request->filled('gstin') ? strtoupper($request->gstin) : null,
            'pan' => $request->filled('pan') ? strtoupper($request->pan) : null,
            'ifsc' => $request->filled('ifsc') ? strtoupper($request->ifsc) : null,
        ];

        // Bank details only mean something on a bank ledger.
        $group = $locked ? $ledger->ledger_group : $data['ledger_group'];

        return in_array($group, Ledger::BANK_GROUPS, true) ? $data : ['bank_name' => null, 'account_number' => null, 'ifsc' => null] + $data;
    }
}

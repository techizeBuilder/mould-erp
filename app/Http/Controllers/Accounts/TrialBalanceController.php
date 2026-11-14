<?php

namespace App\Http\Controllers\Accounts;

use App\Http\Controllers\Controller;
use App\Models\Ledger;
use App\Support\VoucherLines;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class TrialBalanceController extends Controller
{
    public function __invoke(Request $request)
    {
        $data = $request->validate(['as_of' => ['nullable', 'date']]);
        $asOf = Carbon::parse($data['as_of'] ?? today());

        // Ledgers with nothing on them don't belong on the statement.
        $ledgers = Ledger::query()->withTotals($asOf)->orderBy('name')->get()->filter(fn (Ledger $l) => $l->closing() != 0);

        $sections = collect(Ledger::GROUPS)
            ->map(fn (array $group, string $key) => ['label' => $group['label'], 'nature' => $group['nature'], 'ledgers' => $ledgers->where('ledger_group', $key)->values()])
            ->filter(fn (array $section) => $section['ledgers']->isNotEmpty());

        $debit = round($ledgers->sum(fn (Ledger $l) => max($l->closing(), 0)), 2);
        $credit = round($ledgers->sum(fn (Ledger $l) => max(-$l->closing(), 0)), 2);

        return view('accounts.trial-balance', [
            'asOf' => $asOf,
            'sections' => $sections,
            'debit' => $debit,
            'credit' => $credit,
            // Vouchers always balance, so any gap comes from the opening balances.
            'difference' => VoucherLines::paise($debit) === VoucherLines::paise($credit) ? 0.0 : round($debit - $credit, 2),
        ]);
    }
}

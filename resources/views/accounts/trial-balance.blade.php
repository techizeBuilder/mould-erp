@extends('layouts.app')

@section('title', 'Trial Balance')
@section('breadcrumb', 'Accounts / Trial Balance')

@use('App\Support\Money')

@section('content')
    <div class="max-w-4xl space-y-6">
        @if ($difference != 0)
            <div class="flex items-start gap-3 rounded border border-amber-200 border-l-4 border-l-amber-600 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                <x-icon name="alert" class="mt-0.5 h-4 w-4 shrink-0" />
                <span>Debits and credits differ by <strong>₹{{ Money::format(abs($difference)) }}</strong>. Vouchers always balance, so check the opening balances entered on the ledgers.</span>
            </div>
        @endif

        <div class="card">
            <div class="card-header flex-wrap">
                <h2 class="card-title">{{ config('company.name') }} — Trial Balance as on {{ $asOf->format('d M Y') }}</h2>
                <form method="GET" class="flex items-end gap-2 print:hidden">
                    <input type="date" name="as_of" value="{{ $asOf->format('Y-m-d') }}" class="form-input w-auto" aria-label="As on date">
                    <button class="btn btn-secondary">Show</button>
                    <button type="button" onclick="window.print()" class="btn btn-secondary" title="Print" aria-label="Print"><x-icon name="printer" class="h-4 w-4" /></button>
                </form>
            </div>
            <div class="overflow-x-auto">
                <table class="table-classic">
                    <thead><tr><th>Ledger</th><th class="text-right">Debit (₹)</th><th class="text-right">Credit (₹)</th></tr></thead>
                    <tbody>
                    @forelse ($sections as $section)
                        <tr class="bg-slate-50/60"><td colspan="3" class="text-xs font-bold tracking-wide text-slate-600 uppercase">{{ $section['label'] }}</td></tr>
                        @foreach ($section['ledgers'] as $ledger)
                            @php $balance = $ledger->closing(); @endphp
                            <tr>
                                <td class="pl-8">
                                    @module('accounts_ledgers', 'view')
                                        <a href="{{ route('accounts.ledgers.show', $ledger) }}" class="hover:text-brand-600 hover:underline">{{ $ledger->name }}</a>
                                    @else
                                        {{ $ledger->name }}
                                    @endmodule
                                </td>
                                <td class="text-right tabular-nums whitespace-nowrap">{{ $balance > 0 ? Money::format($balance) : '' }}</td>
                                <td class="text-right tabular-nums whitespace-nowrap">{{ $balance < 0 ? Money::format(-$balance) : '' }}</td>
                            </tr>
                        @endforeach
                    @empty
                        <tr><td colspan="3" class="py-12 text-center text-sm text-slate-500">No balances on this date yet.</td></tr>
                    @endforelse
                    <tr class="font-bold text-navy-900">
                        <td>Total</td>
                        <td class="text-right tabular-nums whitespace-nowrap">{{ Money::format($debit) }}</td>
                        <td class="text-right tabular-nums whitespace-nowrap">{{ Money::format($credit) }}</td>
                    </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

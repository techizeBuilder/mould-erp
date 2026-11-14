@extends('layouts.app')

@section('title', $voucher->typeLabel().' '.$voucher->code)
@section('breadcrumb', 'Accounts / Vouchers / '.$voucher->code)

@use('App\Support\Money')
@use('App\Support\VoucherLines')

@section('content')
    <div class="max-w-3xl space-y-6">
        <div class="card">
            <div class="card-header flex-wrap">
                <div>
                    <div class="font-mono text-xs font-semibold text-navy-700">{{ $voucher->code }}</div>
                    <h2 class="card-title">{{ $voucher->typeLabel() }} voucher</h2>
                </div>
                <div class="flex flex-wrap gap-2 print:hidden">
                    <button type="button" onclick="window.print()" class="btn btn-secondary"><x-icon name="printer" class="h-4 w-4" /> Print</button>
                    @module('accounts_vouchers', 'edit')
                        <a href="{{ route('accounts.vouchers.edit', $voucher) }}" class="btn btn-secondary"><x-icon name="pencil" class="h-4 w-4" /> Edit</a>
                    @endmodule
                    @module('accounts_vouchers', 'delete')
                        <form method="POST" action="{{ route('accounts.vouchers.destroy', $voucher) }}" data-confirm="Delete voucher {{ $voucher->code }}? Ledger balances will change.">
                            @csrf @method('DELETE')
                            <button class="btn border border-red-200 bg-white text-red-600 hover:bg-red-50"><x-icon name="trash" class="h-4 w-4" /> Delete</button>
                        </form>
                    @endmodule
                </div>
            </div>
            <dl class="grid gap-5 p-5 sm:grid-cols-2 lg:grid-cols-3">
                <x-detail label="Date">{{ $voucher->voucher_date->format('d M Y') }}</x-detail>
                <x-detail label="Reference">{{ $voucher->reference }}</x-detail>
                <x-detail label="Entered by">{{ $voucher->creator?->name }}</x-detail>
                @if ($voucher->taxable_amount !== null)
                    <x-detail label="Amount before GST">₹{{ Money::format($voucher->taxable_amount) }}</x-detail>
                    <x-detail label="GST">{{ (float) $voucher->gst_rate }}% · {{ $voucher->is_interstate ? 'Other state (IGST)' : 'Within state (CGST + SGST)' }}</x-detail>
                @endif
                @if ($voucher->narration)<x-detail label="Narration" class="sm:col-span-2 lg:col-span-3"><span class="whitespace-pre-line">{{ $voucher->narration }}</span></x-detail>@endif
            </dl>
            <div class="overflow-x-auto">
                <table class="table-classic">
                    <thead><tr><th>Ledger</th><th class="text-right">Debit (₹)</th><th class="text-right">Credit (₹)</th></tr></thead>
                    <tbody>
                    @foreach ($voucher->lines as $line)
                        <tr>
                            <td>
                                @module('accounts_ledgers', 'view')
                                    <a href="{{ route('accounts.ledgers.show', $line->ledger) }}" class="font-semibold text-slate-800 hover:text-brand-600 hover:underline">{{ $line->ledger->name }}</a>
                                @else
                                    <span class="font-semibold">{{ $line->ledger->name }}</span>
                                @endmodule
                            </td>
                            <td class="text-right tabular-nums whitespace-nowrap">{{ (float) $line->debit ? Money::format($line->debit) : '' }}</td>
                            <td class="text-right tabular-nums whitespace-nowrap">{{ (float) $line->credit ? Money::format($line->credit) : '' }}</td>
                        </tr>
                    @endforeach
                    <tr class="font-bold text-navy-900">
                        <td>Total</td>
                        <td class="text-right tabular-nums">{{ Money::format($voucher->amount) }}</td>
                        <td class="text-right tabular-nums">{{ Money::format($voucher->amount) }}</td>
                    </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <a href="{{ route('accounts.vouchers.index') }}" class="inline-block text-sm font-semibold text-brand-600 hover:underline print:hidden">← Back to vouchers</a>
    </div>
@endsection

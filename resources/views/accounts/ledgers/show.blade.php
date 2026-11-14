@extends('layouts.app')

@section('title', $ledger->name)
@section('breadcrumb', 'Accounts / Ledgers / '.$ledger->code)

@use('App\Support\Money')
@use('App\Support\VoucherLines')

@section('content')
    <div class="max-w-5xl space-y-6">
        <div class="card">
            <div class="card-header flex-wrap">
                <div>
                    <div class="font-mono text-xs font-semibold text-navy-700">{{ $ledger->code }}</div>
                    <h2 class="card-title">{{ $ledger->name }}
                        @if ($ledger->status !== 'active')<span class="badge ml-1 {{ $ledger->statusTone() }}">{{ $ledger->statusLabel() }}</span>@endif
                    </h2>
                    <div class="text-sm text-slate-500">{{ $ledger->groupLabel() }}</div>
                </div>
                <div class="flex flex-wrap gap-2 print:hidden">
                    <button type="button" onclick="window.print()" class="btn btn-secondary"><x-icon name="printer" class="h-4 w-4" /> Print</button>
                    @module('accounts_ledgers', 'edit')
                        <a href="{{ route('accounts.ledgers.edit', $ledger) }}" class="btn btn-secondary"><x-icon name="pencil" class="h-4 w-4" /> Edit</a>
                    @endmodule
                    @if (! $ledger->isLocked())
                        @module('accounts_ledgers', 'delete')
                            <form method="POST" action="{{ route('accounts.ledgers.destroy', $ledger) }}" data-confirm="Delete ledger &quot;{{ $ledger->name }}&quot;?">
                                @csrf @method('DELETE')
                                <button class="btn border border-red-200 bg-white text-red-600 hover:bg-red-50"><x-icon name="trash" class="h-4 w-4" /> Delete</button>
                            </form>
                        @endmodule
                    @endif
                </div>
            </div>
            @php
                $details = array_filter([
                    'Contact person' => $ledger->contact_person, 'Phone' => $ledger->phone, 'Email' => $ledger->email, 'State' => $ledger->state,
                    'GSTIN' => $ledger->gstin, 'PAN' => $ledger->pan, 'Bank' => $ledger->bank_name, 'Account number' => $ledger->account_number, 'IFSC' => $ledger->ifsc,
                    'Address' => $ledger->address, 'Remarks' => $ledger->remarks,
                ]);
            @endphp
            @if ($details || $ledger->customer)
                <dl class="grid gap-5 p-5 sm:grid-cols-2 lg:grid-cols-4">
                    @if ($ledger->customer)
                        <x-detail label="Customer">
                            @module('sales_retention', 'view')
                                <a href="{{ route('sales.retention.show', $ledger->customer) }}" class="font-semibold text-brand-600 hover:underline">{{ $ledger->customer->code }}</a>
                            @else
                                {{ $ledger->customer->code }}
                            @endmodule
                        </x-detail>
                    @endif
                    @foreach ($details as $label => $value)
                        <x-detail :label="$label">{{ $value }}</x-detail>
                    @endforeach
                </dl>
            @endif
        </div>

        <div class="card">
            <div class="card-header flex-wrap">
                <h2 class="card-title">Statement <span class="text-sm font-normal text-slate-500">{{ $from->format('d M Y') }} to {{ $to->format('d M Y') }}</span></h2>
                <form method="GET" class="flex flex-wrap items-end gap-2 print:hidden">
                    <input type="date" name="from" value="{{ $from->format('Y-m-d') }}" class="form-input w-auto" aria-label="From date">
                    <input type="date" name="to" value="{{ $to->format('Y-m-d') }}" class="form-input w-auto" aria-label="To date">
                    <button class="btn btn-secondary">Show</button>
                </form>
            </div>
            @error('to') <p class="form-error px-5 pt-3">{{ $message }}</p> @enderror
            <div class="overflow-x-auto">
                <table class="table-classic">
                    <thead>
                    <tr>
                        <th>Date</th>
                        <th>Voucher</th>
                        <th>Particulars</th>
                        <th class="text-right">Debit</th>
                        <th class="text-right">Credit</th>
                        <th class="text-right">Balance</th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr class="bg-slate-50/60">
                        <td colspan="5" class="font-semibold">Opening balance</td>
                        <td class="text-right font-semibold tabular-nums whitespace-nowrap">{{ VoucherLines::drCr($opening) }}</td>
                    </tr>
                    @forelse ($rows as $row)
                        @php $line = $row['line']; $voucher = $line->voucher; @endphp
                        <tr>
                            <td class="whitespace-nowrap">{{ $voucher->voucher_date->format('d M Y') }}</td>
                            <td class="whitespace-nowrap">
                                @module('accounts_vouchers', 'view')
                                    <a href="{{ route('accounts.vouchers.show', $voucher) }}" class="font-mono text-xs font-semibold text-brand-600 hover:underline">{{ $voucher->code }}</a>
                                @else
                                    <span class="font-mono text-xs font-semibold">{{ $voucher->code }}</span>
                                @endmodule
                                @if ($voucher->reference)<div class="text-xs text-slate-500">{{ $voucher->reference }}</div>@endif
                            </td>
                            <td>
                                {{ $voucher->lines->where('ledger_id', '!=', $ledger->id)->map(fn ($l) => $l->ledger->name)->unique()->join(', ') }}
                                @if ($voucher->narration)<div class="text-xs text-slate-500">{{ $voucher->narration }}</div>@endif
                            </td>
                            <td class="text-right tabular-nums whitespace-nowrap">{{ (float) $line->debit ? Money::format($line->debit) : '' }}</td>
                            <td class="text-right tabular-nums whitespace-nowrap">{{ (float) $line->credit ? Money::format($line->credit) : '' }}</td>
                            <td class="text-right tabular-nums whitespace-nowrap">{{ VoucherLines::drCr($row['balance']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-6 text-center text-sm text-slate-500">No vouchers in this period.</td></tr>
                    @endforelse
                    <tr class="font-bold text-navy-900">
                        <td colspan="3">Closing balance</td>
                        <td class="text-right tabular-nums whitespace-nowrap">{{ Money::format($totalDebit) }}</td>
                        <td class="text-right tabular-nums whitespace-nowrap">{{ Money::format($totalCredit) }}</td>
                        <td class="text-right tabular-nums whitespace-nowrap">{{ VoucherLines::drCr($closing) }}</td>
                    </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <a href="{{ route('accounts.ledgers.index') }}" class="inline-block text-sm font-semibold text-brand-600 hover:underline print:hidden">← Back to ledgers</a>
    </div>
@endsection

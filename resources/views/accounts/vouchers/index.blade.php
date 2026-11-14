@extends('layouts.app')

@section('title', 'Vouchers')
@section('breadcrumb', 'Accounts / Vouchers')

@use('App\Models\Voucher')
@use('App\Support\Money')

@section('content')
    <div class="card">
        <div class="card-header flex-wrap">
            <h2 class="card-title">Vouchers @module('accounts_vouchers', 'view')<span class="ml-1 text-sm font-normal text-slate-500">({{ $vouchers->total() }}, ₹{{ Money::format($total) }})</span>@endmodule</h2>
            @module('accounts_vouchers', 'create')
                <div class="flex flex-wrap gap-1.5">
                    @foreach (Voucher::TYPES as $key => $type)
                        <a href="{{ route('accounts.vouchers.create', ['type' => $key]) }}" class="btn btn-secondary btn-sm" title="{{ $type['hint'] }}"><x-icon name="plus" class="h-3.5 w-3.5" /> {{ $type['label'] }}</a>
                    @endforeach
                </div>
            @endmodule
        </div>

        @module('accounts_vouchers', 'view')
            <form method="GET" class="flex flex-wrap items-end gap-3 border-b border-slate-200 bg-slate-50/60 px-5 py-3">
                <div class="min-w-[180px] flex-1">
                    <div class="relative">
                        <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400" />
                        <input name="search" value="{{ request('search') }}" class="form-input pl-9" placeholder="Search voucher no., reference or narration">
                    </div>
                </div>
                <select name="type" class="form-input w-auto" aria-label="Voucher type">
                    <option value="">All types</option>
                    @foreach (Voucher::TYPES as $key => $type)
                        <option value="{{ $key }}" @selected(request('type') === $key)>{{ $type['label'] }}</option>
                    @endforeach
                </select>
                <select name="ledger_id" class="form-input w-auto max-w-56" aria-label="Ledger">
                    <option value="">All ledgers</option>
                    @foreach ($ledgers as $ledger)
                        <option value="{{ $ledger->id }}" @selected((int) request('ledger_id') === $ledger->id)>{{ $ledger->name }}</option>
                    @endforeach
                </select>
                <input type="date" name="from" value="{{ request('from') }}" class="form-input w-auto" aria-label="From date">
                <input type="date" name="to" value="{{ request('to') }}" class="form-input w-auto" aria-label="To date">
                <button class="btn btn-secondary">Filter</button>
                @if (request()->hasAny(['search', 'type', 'ledger_id', 'from', 'to']))
                    <a href="{{ route('accounts.vouchers.index') }}" class="text-sm font-semibold text-slate-500 hover:text-slate-700">Reset</a>
                @endif
            </form>

            <div class="overflow-x-auto">
                <table class="table-classic">
                    <thead><tr><th>Date</th><th>Voucher</th><th>Type</th><th>Particulars</th><th class="text-right">Amount (₹)</th><th class="text-right">Actions</th></tr></thead>
                    <tbody>
                    @forelse ($vouchers as $voucher)
                        <tr>
                            <td class="whitespace-nowrap">{{ $voucher->voucher_date->format('d M Y') }}</td>
                            <td class="whitespace-nowrap">
                                <a href="{{ route('accounts.vouchers.show', $voucher) }}" class="font-mono text-xs font-semibold text-navy-800 hover:underline">{{ $voucher->code }}</a>
                                @if ($voucher->reference)<div class="text-xs text-slate-500">{{ $voucher->reference }}</div>@endif
                            </td>
                            <td><span class="badge {{ $voucher->typeTone() }}">{{ $voucher->typeLabel() }}</span></td>
                            <td class="max-w-sm">
                                <div class="truncate font-semibold text-slate-800">{{ $voucher->particulars() }}</div>
                                @if ($voucher->narration)<div class="truncate text-xs text-slate-500">{{ $voucher->narration }}</div>@endif
                            </td>
                            <td class="text-right font-semibold tabular-nums whitespace-nowrap">{{ Money::format($voucher->amount) }}</td>
                            <td>
                                <div class="flex justify-end gap-1.5">
                                    <a href="{{ route('accounts.vouchers.show', $voucher) }}" class="btn btn-secondary btn-sm" title="View"><x-icon name="eye" class="h-4 w-4" /></a>
                                    @module('accounts_vouchers', 'edit')
                                        <a href="{{ route('accounts.vouchers.edit', $voucher) }}" class="btn btn-secondary btn-sm" title="Edit"><x-icon name="pencil" class="h-4 w-4" /></a>
                                    @endmodule
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-12 text-center text-sm text-slate-500">No vouchers found.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if ($vouchers->hasPages())<div class="border-t border-slate-200 px-5 py-3">{{ $vouchers->links() }}</div>@endif
        @else
            <p class="px-5 py-10 text-center text-sm text-slate-500">You don't have permission to view these records.</p>
        @endmodule
    </div>
@endsection

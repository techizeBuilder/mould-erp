@extends('layouts.app')

@section('title', 'Ledgers')
@section('breadcrumb', 'Accounts / Ledgers')

@use('App\Models\Ledger')
@use('App\Support\Money')
@use('App\Support\VoucherLines')

@section('content')
    @module('accounts_ledgers', 'view')
        <div class="mb-6 grid gap-4 sm:grid-cols-3">
            @include('sales._stat', ['label' => 'Cash & bank balance', 'value' => '₹'.VoucherLines::drCr($stats['money'])])
            @include('sales._stat', ['label' => 'Receivable from customers', 'value' => '₹'.VoucherLines::drCr($stats['receivable']), 'tone' => 'text-emerald-700', 'href' => route('accounts.ledgers.index', ['group' => 'debtors'])])
            @include('sales._stat', ['label' => 'Payable to suppliers', 'value' => '₹'.VoucherLines::drCr(-$stats['payable']), 'tone' => 'text-amber-700', 'href' => route('accounts.ledgers.index', ['group' => 'creditors'])])
        </div>
    @endmodule

    <div class="card">
        <div class="card-header flex-wrap">
            <h2 class="card-title">Ledgers @module('accounts_ledgers', 'view')<span class="ml-1 text-sm font-normal text-slate-500">({{ $ledgers->total() }})</span>@endmodule</h2>
            @module('accounts_ledgers', 'create')
                <a href="{{ route('accounts.ledgers.create') }}" class="btn btn-primary"><x-icon name="plus" class="h-4 w-4" /> Add Ledger</a>
            @endmodule
        </div>

        @module('accounts_ledgers', 'view')
            <form method="GET" class="flex flex-wrap items-end gap-3 border-b border-slate-200 bg-slate-50/60 px-5 py-3">
                <div class="min-w-[200px] flex-1">
                    <div class="relative">
                        <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400" />
                        <input name="search" value="{{ request('search') }}" class="form-input pl-9" placeholder="Search code, name, phone or GSTIN">
                    </div>
                </div>
                <select name="group" class="form-input w-auto max-w-64" aria-label="Ledger type">
                    <option value="">All ledger types</option>
                    @foreach (Ledger::groupOptions() as $nature => $groups)
                        <optgroup label="{{ $nature }}">
                            @foreach ($groups as $value => $label)
                                <option value="{{ $value }}" @selected(request('group') === $value)>{{ $label }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
                <select name="status" class="form-input w-auto" aria-label="Status">
                    @foreach (Ledger::STATUSES as $value => $label)
                        <option value="{{ $value }}" @selected(request('status', 'active') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <button class="btn btn-secondary">Filter</button>
                @if (request()->hasAny(['search', 'group', 'status']))
                    <a href="{{ route('accounts.ledgers.index') }}" class="text-sm font-semibold text-slate-500 hover:text-slate-700">Reset</a>
                @endif
            </form>

            <div class="overflow-x-auto">
                <table class="table-classic">
                    <thead>
                    <tr>
                        <th>Code</th>
                        <th>Ledger</th>
                        <th>Type</th>
                        <th class="text-right">Balance (₹)</th>
                        <th class="text-right">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($ledgers as $ledger)
                        <tr>
                            <td class="font-mono text-xs font-semibold whitespace-nowrap text-navy-800">{{ $ledger->code }}</td>
                            <td>
                                <a href="{{ route('accounts.ledgers.show', $ledger) }}" class="font-semibold text-slate-800 hover:text-brand-600 hover:underline">{{ $ledger->name }}</a>
                                @if ($ledger->phone)<div class="text-xs text-slate-500">{{ $ledger->phone }}</div>@endif
                            </td>
                            <td>{{ $ledger->groupLabel() }}</td>
                            <td class="text-right font-semibold tabular-nums whitespace-nowrap">{{ VoucherLines::drCr($ledger->closing()) }}</td>
                            <td>
                                <div class="flex justify-end gap-1.5">
                                    <a href="{{ route('accounts.ledgers.show', $ledger) }}" class="btn btn-secondary btn-sm" title="Statement"><x-icon name="eye" class="h-4 w-4" /></a>
                                    @module('accounts_ledgers', 'edit')
                                        <a href="{{ route('accounts.ledgers.edit', $ledger) }}" class="btn btn-secondary btn-sm" title="Edit"><x-icon name="pencil" class="h-4 w-4" /></a>
                                    @endmodule
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-12 text-center text-sm text-slate-500">No ledgers found.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if ($ledgers->hasPages())<div class="border-t border-slate-200 px-5 py-3">{{ $ledgers->links() }}</div>@endif
        @else
            <p class="px-5 py-10 text-center text-sm text-slate-500">You don't have permission to view these records.</p>
        @endmodule
    </div>
@endsection

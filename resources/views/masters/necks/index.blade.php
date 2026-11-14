@extends('layouts.app')

@section('title', 'Neck Master')
@section('breadcrumb', 'Masters / Neck Master')

@use('App\Models\Neck')
@use('App\Support\Capacity')

@php $mm = fn ($v) => $v !== null ? Capacity::trim($v) : '—'; @endphp

@section('content')
    <div class="card">
        <div class="card-header flex-wrap">
            <h2 class="card-title">Necks @module('neck_master', 'view')<span class="ml-1 text-sm font-normal text-slate-500">({{ $necks->total() }})</span>@endmodule</h2>
            @module('neck_master', 'create')
                <a href="{{ route('masters.necks.create') }}" class="btn btn-primary"><x-icon name="plus" class="h-4 w-4" /> Add Neck</a>
            @endmodule
        </div>

        @module('neck_master', 'view')
            <form method="GET" class="flex flex-wrap items-end gap-3 border-b border-slate-200 bg-slate-50/60 px-5 py-3">
                <div class="min-w-[200px] flex-1">
                    <div class="relative">
                        <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400" />
                        <input name="search" value="{{ request('search') }}" class="form-input pl-9" placeholder="Search serial no., name or neck type">
                    </div>
                </div>
                <select name="status" class="form-input w-auto" aria-label="Status">
                    <option value="">All statuses</option>
                    @foreach (Neck::STATUSES as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <button class="btn btn-secondary">Filter</button>
                @if (request()->hasAny(['search', 'status']))
                    <a href="{{ route('masters.necks.index') }}" class="text-sm font-semibold text-slate-500 hover:text-slate-700">Reset</a>
                @endif
            </form>

            <div class="overflow-x-auto">
                <table class="table-classic">
                    <thead>
                    <tr>
                        <th>Serial no.</th>
                        <th>Neck</th>
                        <th class="text-right">Size (mm)</th>
                        <th>Type</th>
                        <th class="text-right">Height (mm)</th>
                        <th class="text-right">Inner dia</th>
                        <th class="text-right">Outer dia</th>
                        <th class="text-center">Moulds</th>
                        <th class="text-center">Products</th>
                        <th>Status</th>
                        <th class="text-right">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($necks as $neck)
                        <tr>
                            <td class="font-mono text-xs font-semibold whitespace-nowrap text-navy-800">{{ $neck->code }}</td>
                            <td><a href="{{ route('masters.necks.show', $neck) }}" class="font-semibold text-slate-800 hover:text-brand-600 hover:underline">{{ $neck->name }}</a></td>
                            <td class="text-right tabular-nums">{{ $mm($neck->neck_size_mm) }}</td>
                            <td>{{ $neck->neck_type ?: '—' }}</td>
                            <td class="text-right tabular-nums">{{ $mm($neck->neck_height_mm) }}</td>
                            <td class="text-right tabular-nums">{{ $mm($neck->inner_dia_mm) }}</td>
                            <td class="text-right tabular-nums">{{ $mm($neck->outer_dia_mm) }}</td>
                            <td class="text-center tabular-nums">{{ $neck->moulds_count }}</td>
                            <td class="text-center tabular-nums">{{ $neck->products_count }}</td>
                            <td><span class="badge {{ $neck->statusTone() }}">{{ $neck->statusLabel() }}</span></td>
                            <td>
                                <div class="flex justify-end gap-1.5">
                                    <a href="{{ route('masters.necks.show', $neck) }}" class="btn btn-secondary btn-sm" title="View"><x-icon name="eye" class="h-4 w-4" /></a>
                                    @module('neck_master', 'edit')
                                        <a href="{{ route('masters.necks.edit', $neck) }}" class="btn btn-secondary btn-sm" title="Edit"><x-icon name="pencil" class="h-4 w-4" /></a>
                                    @endmodule
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="11" class="py-12 text-center text-sm text-slate-500">No necks found.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if ($necks->hasPages())<div class="border-t border-slate-200 px-5 py-3">{{ $necks->links() }}</div>@endif
        @else
            <p class="px-5 py-10 text-center text-sm text-slate-500">You don't have permission to view these records.</p>
        @endmodule
    </div>
@endsection

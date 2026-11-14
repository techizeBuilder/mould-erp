@extends('layouts.app')

@section('title', 'Mould Master')
@section('breadcrumb', 'Masters / Mould Master')

@section('content')
    <div class="card">
        <div class="card-header flex-wrap">
            <h2 class="card-title">Moulds @module('mould_master', 'view')<span class="ml-1 text-sm font-normal text-slate-500">({{ $moulds->total() }})</span>@endmodule</h2>
            @module('mould_master', 'create')
                <a href="{{ route('masters.moulds.create') }}" class="btn btn-primary"><x-icon name="plus" class="h-4 w-4" /> Add Mould</a>
            @endmodule
        </div>

        @module('mould_master', 'view')
            <form method="GET" class="flex flex-wrap items-end gap-3 border-b border-slate-200 bg-slate-50/60 px-5 py-3">
                <div class="min-w-[200px] flex-1">
                    <div class="relative">
                        <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400" />
                        <input name="search" value="{{ request('search') }}" class="form-input pl-9" placeholder="Search serial no., name, neck type or location">
                    </div>
                </div>
                <select name="type" class="form-input w-auto min-w-[150px]">
                    <option value="">All mould types</option>
                    @foreach (\App\Models\Mould::TYPES as $value => $label)
                        <option value="{{ $value }}" @selected(request('type') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <select name="shape" class="form-input w-auto">
                    <option value="">Any shape</option>
                    @foreach (\App\Models\Shape::options() as $value => $label)
                        <option value="{{ $value }}" @selected(request('shape') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <select name="status" class="form-input w-auto">
                    <option value="">Any status</option>
                    @foreach (\App\Models\Mould::STATUSES as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <button class="btn btn-secondary">Filter</button>
                @if (request()->hasAny(['search', 'type', 'shape', 'status']))
                    <a href="{{ route('masters.moulds.index') }}" class="text-sm font-semibold text-slate-500 hover:text-slate-700">Reset</a>
                @endif
            </form>

            <div class="overflow-x-auto">
                <table class="table-classic">
                    <thead>
                    <tr>
                        <th class="w-14"></th>
                        <th>Serial no.</th>
                        <th>Mould</th>
                        <th>Type</th>
                        <th>Shape</th>
                        <th>Volume</th>
                        <th>Neck</th>
                        <th>Weight</th>
                        <th class="text-center">Cavities</th>
                        <th class="text-center">Machines</th>
                        <th class="text-center">Products</th>
                        <th>Status</th>
                        <th class="text-right">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($moulds as $mould)
                        <tr>
                            <td>
                                <div class="flex h-10 w-10 items-center justify-center overflow-hidden rounded border border-slate-200 bg-slate-50">
                                    @if ($mould->imageUrl())
                                        <img src="{{ $mould->imageUrl() }}" alt="" class="h-full w-full object-contain" loading="lazy">
                                    @else
                                        <x-icon name="cube" class="h-5 w-5 text-slate-300" />
                                    @endif
                                </div>
                            </td>
                            <td class="font-mono text-xs font-semibold whitespace-nowrap text-navy-800">{{ $mould->code }}</td>
                            <td>
                                <a href="{{ route('masters.moulds.show', $mould) }}" class="font-semibold text-slate-800 hover:text-brand-600 hover:underline">{{ $mould->name }}</a>
                                @if ($mould->location)<div class="text-xs text-slate-500">{{ $mould->location }}</div>@endif
                            </td>
                            <td class="whitespace-nowrap">
                                @if ($mould->typeLabel())
                                    <span class="badge {{ $mould->mould_type === 'preform' ? 'bg-sky-50 text-sky-700' : 'bg-violet-50 text-violet-700' }}">{{ $mould->typeLabel() }}</span>
                                @else
                                    <span class="badge bg-amber-50 text-amber-700" title="Edit this mould to choose Preform or Blow">Type not set</span>
                                @endif
                            </td>
                            <td>{{ $mould->shapeLabel() ?? '—' }}</td>
                            <td class="whitespace-nowrap">{{ \App\Support\Capacity::format($mould->capacity_ml) ?? '—' }}</td>
                            <td class="whitespace-nowrap">{{ collect([$mould->neck_size_mm ? \App\Support\Capacity::trim($mould->neck_size_mm).' mm' : null, $mould->neck_type])->filter()->join(' ') ?: '—' }}</td>
                            <td class="whitespace-nowrap">{{ $mould->weight_grams ? \App\Support\Capacity::trim($mould->weight_grams).' g' : '—' }}</td>
                            <td class="text-center tabular-nums">{{ $mould->cavities }}</td>
                            <td class="text-center tabular-nums">{{ $mould->machines_count }}</td>
                            <td class="text-center tabular-nums">{{ $mould->products_count }}</td>
                            <td><span class="badge whitespace-nowrap {{ $mould->statusTone() }}">{{ $mould->statusLabel() }}</span></td>
                            <td>
                                <div class="flex justify-end gap-1.5">
                                    <a href="{{ route('masters.moulds.show', $mould) }}" class="btn btn-secondary btn-sm" title="View"><x-icon name="eye" class="h-4 w-4" /></a>
                                    @module('mould_master', 'edit')
                                        <a href="{{ route('masters.moulds.edit', $mould) }}" class="btn btn-secondary btn-sm" title="Edit"><x-icon name="pencil" class="h-4 w-4" /></a>
                                    @endmodule
                                    @module('mould_master', 'delete')
                                        <form method="POST" action="{{ route('masters.moulds.destroy', $mould) }}"
                                              data-confirm="Delete mould &quot;{{ $mould->code }} – {{ $mould->name }}&quot;?{{ $mould->products_count ? ' It will be removed from '.$mould->products_count.' product(s).' : '' }} To keep its history, set its status to Inactive instead.">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-sm border border-red-200 bg-white text-red-600 hover:bg-red-50" title="Delete"><x-icon name="trash" class="h-4 w-4" /></button>
                                        </form>
                                    @endmodule
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="13" class="py-12 text-center">
                                <x-icon name="cube" class="mx-auto h-10 w-10 text-slate-300" />
                                <p class="mt-2 text-slate-500">No moulds found.</p>
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            @if ($moulds->hasPages())
                <div class="border-t border-slate-200 px-5 py-3">{{ $moulds->links() }}</div>
            @endif
        @else
            @include('masters._no-view')
        @endmodule
    </div>
@endsection

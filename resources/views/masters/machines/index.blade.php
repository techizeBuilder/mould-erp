@extends('layouts.app')

@section('title', 'Machine Master')
@section('breadcrumb', 'Masters / Machine Master')

@section('content')
    <div class="card">
        <div class="card-header flex-wrap">
            <h2 class="card-title">Machines @module('machine_master', 'view')<span class="ml-1 text-sm font-normal text-slate-500">({{ $machines->total() }})</span>@endmodule</h2>
            @module('machine_master', 'create')
                <a href="{{ route('masters.machines.create') }}" class="btn btn-primary"><x-icon name="plus" class="h-4 w-4" /> Add Machine</a>
            @endmodule
        </div>

        @module('machine_master', 'view')
            <form method="GET" class="flex flex-wrap items-end gap-3 border-b border-slate-200 bg-slate-50/60 px-5 py-3">
                <div class="min-w-[200px] flex-1">
                    <div class="relative">
                        <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400" />
                        <input name="search" value="{{ request('search') }}" class="form-input pl-9" placeholder="Search serial no., name, brand, model or location">
                    </div>
                </div>
                <select name="type" class="form-input w-auto min-w-[160px]">
                    <option value="">All machine types</option>
                    @foreach (\App\Models\Machine::TYPES as $value => $label)
                        <option value="{{ $value }}" @selected(request('type') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <select name="status" class="form-input w-auto">
                    <option value="">Any status</option>
                    @foreach (\App\Models\Machine::STATUSES as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <button class="btn btn-secondary">Filter</button>
                @if (request()->hasAny(['search', 'type', 'status']))
                    <a href="{{ route('masters.machines.index') }}" class="text-sm font-semibold text-slate-500 hover:text-slate-700">Reset</a>
                @endif
            </form>

            <div class="overflow-x-auto">
                <table class="table-classic">
                    <thead>
                    <tr>
                        <th>Serial no.</th>
                        <th>Machine</th>
                        <th>Type</th>
                        <th>Brand / model</th>
                        <th class="text-right">Tonnage</th>
                        <th class="text-center">Cavities</th>
                        <th>Bottle size</th>
                        <th class="text-center">Moulds</th>
                        <th>Status</th>
                        <th class="text-right">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($machines as $machine)
                        <tr>
                            <td class="font-mono text-xs font-semibold whitespace-nowrap text-navy-800">{{ $machine->code }}</td>
                            <td>
                                <a href="{{ route('masters.machines.show', $machine) }}" class="font-semibold text-slate-800 hover:text-brand-600 hover:underline">{{ $machine->name }}</a>
                                @if ($machine->location)<div class="text-xs text-slate-500">{{ $machine->location }}</div>@endif
                            </td>
                            <td class="whitespace-nowrap">{{ $machine->typeLabel() ?? '—' }}</td>
                            <td>{{ collect([$machine->make, $machine->model_no])->filter()->join(' / ') ?: '—' }}</td>
                            <td class="text-right tabular-nums whitespace-nowrap">{{ $machine->tonnage ? \App\Support\Capacity::trim($machine->tonnage).' T' : '—' }}</td>
                            <td class="text-center tabular-nums">{{ $machine->cavities ?? '—' }}</td>
                            <td class="whitespace-nowrap">{{ \App\Support\Capacity::range($machine->min_capacity_ml, $machine->max_capacity_ml) ?? '—' }}</td>
                            <td class="text-center tabular-nums">{{ $machine->moulds_count }}</td>
                            <td><span class="badge whitespace-nowrap {{ $machine->statusTone() }}">{{ $machine->statusLabel() }}</span></td>
                            <td>
                                <div class="flex justify-end gap-1.5">
                                    <a href="{{ route('masters.machines.show', $machine) }}" class="btn btn-secondary btn-sm" title="View"><x-icon name="eye" class="h-4 w-4" /></a>
                                    @module('machine_master', 'edit')
                                        <a href="{{ route('masters.machines.edit', $machine) }}" class="btn btn-secondary btn-sm" title="Edit"><x-icon name="pencil" class="h-4 w-4" /></a>
                                    @endmodule
                                    @module('machine_master', 'delete')
                                        <form method="POST" action="{{ route('masters.machines.destroy', $machine) }}" data-confirm="Delete machine &quot;{{ $machine->code }} – {{ $machine->name }}&quot;? To keep its history, set its status to Inactive instead.">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-sm border border-red-200 bg-white text-red-600 hover:bg-red-50" title="Delete"><x-icon name="trash" class="h-4 w-4" /></button>
                                        </form>
                                    @endmodule
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="py-12 text-center">
                                <x-icon name="cog" class="mx-auto h-10 w-10 text-slate-300" />
                                <p class="mt-2 text-slate-500">No machines found.</p>
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            @if ($machines->hasPages())
                <div class="border-t border-slate-200 px-5 py-3">{{ $machines->links() }}</div>
            @endif
        @else
            @include('masters._no-view')
        @endmodule
    </div>
@endsection

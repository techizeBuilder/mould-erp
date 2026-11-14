@extends('layouts.app')

@section('title', $machine->name)
@section('breadcrumb', 'Masters / Machine Master / '.$machine->code)

@section('content')
    <div class="max-w-5xl space-y-6">
        <div class="card">
            <div class="card-header flex-wrap">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 items-center justify-center rounded bg-navy-900 text-gold-500"><x-icon name="cog" /></span>
                    <div>
                        <div class="font-mono text-xs font-semibold text-navy-700">{{ $machine->code }}</div>
                        <h2 class="card-title">{{ $machine->name }}</h2>
                    </div>
                    <span class="badge {{ $machine->statusTone() }}">{{ $machine->statusLabel() }}</span>
                </div>
                <div class="flex gap-2">
                    @module('machine_master', 'edit')
                        <a href="{{ route('masters.machines.edit', $machine) }}" class="btn btn-secondary"><x-icon name="pencil" class="h-4 w-4" /> Edit</a>
                    @endmodule
                    @module('machine_master', 'delete')
                        <form method="POST" action="{{ route('masters.machines.destroy', $machine) }}" data-confirm="Delete machine &quot;{{ $machine->code }}&quot;? To keep its history, set its status to Inactive instead.">
                            @csrf @method('DELETE')
                            <button class="btn border border-red-200 bg-white text-red-600 hover:bg-red-50"><x-icon name="trash" class="h-4 w-4" /> Delete</button>
                        </form>
                    @endmodule
                </div>
            </div>
            <dl class="grid gap-5 p-5 sm:grid-cols-2 lg:grid-cols-4">
                <x-detail label="Machine type">{{ $machine->typeLabel() }}</x-detail>
                <x-detail label="Brand / manufacturer">{{ $machine->make }}</x-detail>
                <x-detail label="Model number">{{ $machine->model_no }}</x-detail>
                <x-detail label="Location">{{ $machine->location }}</x-detail>
                <x-detail label="Tonnage">{{ $machine->tonnage ? \App\Support\Capacity::trim($machine->tonnage).' tonnes' : '' }}</x-detail>
                <x-detail label="Cavities">{{ $machine->cavities }}</x-detail>
                <x-detail label="Supported bottle size">{{ \App\Support\Capacity::range($machine->min_capacity_ml, $machine->max_capacity_ml) }}</x-detail>
                <x-detail label="Added by">{{ $machine->creator?->name }}{{ $machine->creator ? ' · ' : '' }}{{ $machine->created_at?->format('d M Y') }}</x-detail>
                @if ($machine->remarks)
                    <x-detail label="Remarks" class="sm:col-span-2 lg:col-span-4"><span class="whitespace-pre-line">{{ $machine->remarks }}</span></x-detail>
                @endif
            </dl>
        </div>

        <div class="card">
            <div class="card-header"><h2 class="card-title">Compatible moulds <span class="ml-1 text-sm font-normal text-slate-500">({{ $machine->moulds->count() }})</span></h2></div>
            <div class="overflow-x-auto">
                <table class="table-classic">
                    <thead><tr><th>Serial no.</th><th>Mould</th><th>Type</th><th>Capacity</th><th>Neck</th><th class="text-center">Cavities</th><th class="text-center">Products</th><th>Status</th></tr></thead>
                    <tbody>
                    @forelse ($machine->moulds as $mould)
                        <tr>
                            <td class="font-mono text-xs font-semibold text-navy-800">{{ $mould->code }}</td>
                            <td>
                                @module('mould_master', 'view')
                                    <a href="{{ route('masters.moulds.show', $mould) }}" class="font-semibold text-slate-800 hover:text-brand-600 hover:underline">{{ $mould->name }}</a>
                                @else
                                    <span class="font-semibold text-slate-800">{{ $mould->name }}</span>
                                @endmodule
                                @unless ($machine->supportsCapacity($mould->capacity_ml))
                                    <div class="text-xs font-medium text-amber-700">Outside this machine's bottle size range</div>
                                @endunless
                            </td>
                            <td>{{ $mould->typeLabel() ?? '—' }}</td>
                            <td>{{ \App\Support\Capacity::format($mould->capacity_ml) ?? '—' }}</td>
                            <td>{{ $mould->neck_size_mm ? \App\Support\Capacity::trim($mould->neck_size_mm).' mm' : '—' }}</td>
                            <td class="text-center tabular-nums">{{ $mould->cavities }}</td>
                            <td class="text-center tabular-nums">{{ $mould->products_count }}</td>
                            <td><span class="badge {{ $mould->statusTone() }}">{{ $mould->statusLabel() }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="py-8 text-center text-slate-500">No moulds assigned to this machine yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <a href="{{ route('masters.machines.index') }}" class="inline-block text-sm font-semibold text-brand-600 hover:underline">← Back to machines</a>
    </div>
@endsection

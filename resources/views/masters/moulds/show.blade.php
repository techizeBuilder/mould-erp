@extends('layouts.app')

@section('title', $mould->name)
@section('breadcrumb', 'Masters / Mould Master / '.$mould->code)

@section('content')
    <div class="max-w-5xl space-y-6">
        <div class="card">
            <div class="card-header flex-wrap">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 items-center justify-center rounded bg-navy-900 text-gold-500"><x-icon name="cube" /></span>
                    <div>
                        <div class="font-mono text-xs font-semibold text-navy-700">{{ $mould->code }}</div>
                        <h2 class="card-title">{{ $mould->name }}</h2>
                    </div>
                    <span class="badge {{ $mould->statusTone() }}">{{ $mould->statusLabel() }}</span>
                </div>
                <div class="flex gap-2">
                    @module('mould_master', 'edit')
                        <a href="{{ route('masters.moulds.edit', $mould) }}" class="btn btn-secondary"><x-icon name="pencil" class="h-4 w-4" /> Edit</a>
                    @endmodule
                    @module('mould_master', 'delete')
                        <form method="POST" action="{{ route('masters.moulds.destroy', $mould) }}" data-confirm="Delete mould &quot;{{ $mould->code }}&quot;? To keep its history, set its status to Inactive instead.">
                            @csrf @method('DELETE')
                            <button class="btn border border-red-200 bg-white text-red-600 hover:bg-red-50"><x-icon name="trash" class="h-4 w-4" /> Delete</button>
                        </form>
                    @endmodule
                </div>
            </div>
            <div class="flex flex-col gap-6 p-5 md:flex-row">
                <div class="flex h-44 w-full shrink-0 items-center justify-center overflow-hidden rounded border border-slate-200 bg-slate-50 md:w-44">
                    @if ($mould->imageUrl())
                        <a href="{{ $mould->imageUrl() }}" target="_blank" class="h-full w-full"><img src="{{ $mould->imageUrl() }}" alt="{{ $mould->name }}" class="h-full w-full object-contain"></a>
                    @else
                        <x-icon name="cube" class="h-12 w-12 text-slate-300" />
                    @endif
                </div>
                <dl class="grid flex-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    <x-detail label="Mould type">{{ $mould->typeLabel() ?? 'Not set — edit to choose' }}</x-detail>
                    <x-detail label="Shape">{{ $mould->shapeLabel() }}</x-detail>
                    <x-detail label="Bottle / jar volume">{{ \App\Support\Capacity::format($mould->capacity_ml) }}</x-detail>
                    <x-detail label="Default neck">
                        @if ($mould->neck)
                            @module('neck_master', 'view')
                                <a href="{{ route('masters.necks.show', $mould->neck) }}" class="font-mono text-xs font-semibold text-brand-600 hover:underline">{{ $mould->neck->code }}</a>
                            @else
                                <span class="font-mono text-xs font-semibold">{{ $mould->neck->code }}</span>
                            @endmodule
                        @endif
                    </x-detail>
                    <x-detail label="Neck size">{{ $mould->neck_size_mm ? \App\Support\Capacity::trim($mould->neck_size_mm).' mm' : '' }}</x-detail>
                    <x-detail label="Neck type">{{ $mould->neck_type }}</x-detail>
                    <x-detail label="Neck height">{{ $mould->neck_height_mm ? \App\Support\Capacity::trim($mould->neck_height_mm).' mm' : '' }}</x-detail>
                    <x-detail label="Default weight">{{ $mould->weight_grams ? \App\Support\Capacity::trim($mould->weight_grams).' g' : '' }}</x-detail>
                    <x-detail label="Cavities">{{ $mould->cavities }}</x-detail>
                    <x-detail label="Storage location">{{ $mould->location }}</x-detail>
                    <x-detail label="Added by">{{ $mould->creator?->name }}{{ $mould->creator ? ' · ' : '' }}{{ $mould->created_at?->format('d M Y') }}</x-detail>
                    @if ($mould->remarks)
                        <x-detail label="Remarks" class="sm:col-span-2 lg:col-span-3"><span class="whitespace-pre-line">{{ $mould->remarks }}</span></x-detail>
                    @endif
                </dl>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <div class="card">
                <div class="card-header"><h2 class="card-title">Compatible machines <span class="ml-1 text-sm font-normal text-slate-500">({{ $mould->machines->count() }})</span></h2></div>
                <ul class="divide-y divide-slate-100">
                    @forelse ($mould->machines as $machine)
                        <li class="flex items-center justify-between gap-3 px-5 py-3 text-sm">
                            <div>
                                <span class="font-mono text-xs font-semibold text-navy-800">{{ $machine->code }}</span>
                                @module('machine_master', 'view')
                                    <a href="{{ route('masters.machines.show', $machine) }}" class="ml-1 font-semibold text-slate-800 hover:text-brand-600 hover:underline">{{ $machine->name }}</a>
                                @else
                                    <span class="ml-1 font-semibold text-slate-800">{{ $machine->name }}</span>
                                @endmodule
                                <div class="text-xs text-slate-500">{{ $machine->typeLabel() }}{{ $machine->tonnage ? ' · '.\App\Support\Capacity::trim($machine->tonnage).' T' : '' }}</div>
                                @unless ($machine->supportsCapacity($mould->capacity_ml))
                                    <div class="text-xs font-medium text-amber-700">This mould's capacity is outside the machine's size range ({{ \App\Support\Capacity::range($machine->min_capacity_ml, $machine->max_capacity_ml) }})</div>
                                @endunless
                            </div>
                            <span class="badge shrink-0 {{ $machine->statusTone() }}">{{ $machine->statusLabel() }}</span>
                        </li>
                    @empty
                        <li class="px-5 py-8 text-center text-sm text-slate-500">Not assigned to any machine yet.</li>
                    @endforelse
                </ul>
            </div>

            <div class="card">
                <div class="card-header"><h2 class="card-title">Products made with this mould <span class="ml-1 text-sm font-normal text-slate-500">({{ $mould->products->count() }})</span></h2></div>
                <ul class="divide-y divide-slate-100">
                    @forelse ($mould->products as $product)
                        <li class="flex items-center justify-between gap-3 px-5 py-3 text-sm">
                            <div>
                                <span class="font-mono text-xs font-semibold text-navy-800">{{ $product->code }}</span>
                                @module('product_master', 'view')
                                    <a href="{{ route('masters.products.show', $product) }}" class="ml-1 font-semibold text-slate-800 hover:text-brand-600 hover:underline">{{ $product->name }}</a>
                                @else
                                    <span class="ml-1 font-semibold text-slate-800">{{ $product->name }}</span>
                                @endmodule
                                <div class="text-xs text-slate-500">{{ collect([$product->typeLabel(), \App\Support\Capacity::format($product->capacity_ml), $product->colour])->filter()->join(' · ') }}</div>
                            </div>
                            <span class="badge shrink-0 {{ $product->statusTone() }}">{{ $product->statusLabel() }}</span>
                        </li>
                    @empty
                        <li class="px-5 py-8 text-center text-sm text-slate-500">No products linked yet. Link moulds from the Product Master.</li>
                    @endforelse
                </ul>
            </div>
        </div>

        <a href="{{ route('masters.moulds.index') }}" class="inline-block text-sm font-semibold text-brand-600 hover:underline">← Back to moulds</a>
    </div>
@endsection

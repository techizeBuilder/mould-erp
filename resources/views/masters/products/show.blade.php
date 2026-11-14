@extends('layouts.app')

@section('title', $product->name)
@section('breadcrumb', 'Masters / Product Master / '.$product->code)

@use('App\Support\Capacity')
@php $mould = $product->mould; @endphp

@section('content')
    <div class="max-w-5xl space-y-6">
        <div class="card">
            <div class="card-header flex-wrap">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 items-center justify-center rounded bg-navy-900 text-gold-500"><x-icon name="box" /></span>
                    <div>
                        <div class="font-mono text-xs font-semibold text-navy-700">{{ $product->code }}</div>
                        <h2 class="card-title">{{ $product->name }}</h2>
                        <div class="text-sm text-slate-500">On bill: <span class="font-medium text-slate-700">{{ $product->bill_name }}</span></div>
                    </div>
                    <span class="badge {{ $product->statusTone() }}">{{ $product->statusLabel() }}</span>
                    <span class="badge {{ $product->isClientProduct() ? 'bg-rose-50 text-rose-700' : 'bg-brand-50 text-brand-700' }}">{{ $product->ownerLabel() }}</span>
                </div>
                <div class="flex flex-wrap gap-2">
                    @module('product_master', 'create')
                        <a href="{{ route('masters.products.create', ['from' => $product->id]) }}" class="btn btn-secondary" title="Create a new product with the same details, e.g. another colour"><x-icon name="copy" class="h-4 w-4" /> Duplicate as variant</a>
                    @endmodule
                    @module('product_master', 'edit')
                        <a href="{{ route('masters.products.edit', $product) }}" class="btn btn-secondary"><x-icon name="pencil" class="h-4 w-4" /> Edit</a>
                    @endmodule
                    @module('product_master', 'delete')
                        <form method="POST" action="{{ route('masters.products.destroy', $product) }}" data-confirm="Delete product &quot;{{ $product->code }}&quot;? To keep its history, set its status to Inactive instead.">
                            @csrf @method('DELETE')
                            <button class="btn border border-red-200 bg-white text-red-600 hover:bg-red-50"><x-icon name="trash" class="h-4 w-4" /> Delete</button>
                        </form>
                    @endmodule
                </div>
            </div>
            <div class="flex flex-col gap-6 p-5 md:flex-row">
                <div class="flex h-44 w-full shrink-0 items-center justify-center overflow-hidden rounded border border-slate-200 bg-slate-50 md:w-44">
                    @if ($product->imageUrl())
                        <a href="{{ $product->imageUrl() }}" target="_blank" class="h-full w-full"><img src="{{ $product->imageUrl() }}" alt="{{ $product->name }}" class="h-full w-full object-contain"></a>
                    @else
                        <x-icon name="box" class="h-12 w-12 text-slate-300" />
                    @endif
                </div>
                <dl class="grid flex-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
                    <x-detail label="Product type">{{ $product->typeLabel() }}</x-detail>
                    <x-detail label="Business type">{{ $product->businessTypeLabel() }}</x-detail>
                    <x-detail label="Bottle / jar volume">{{ Capacity::format($product->capacity_ml) }}</x-detail>
                    <x-detail label="Shape">{{ $product->shapeLabel() }}</x-detail>
                    <x-detail label="Neck">
                        @if ($product->neck)
                            @module('neck_master', 'view')
                                <a href="{{ route('masters.necks.show', $product->neck) }}" class="font-mono text-xs font-semibold text-brand-600 hover:underline">{{ $product->neck->code }}</a>
                            @else
                                <span class="font-mono text-xs font-semibold">{{ $product->neck->code }}</span>
                            @endmodule
                            @unless ($product->usesMouldNeck())<span class="badge ml-1 bg-amber-50 text-amber-700">Not the mould's default</span>@endunless
                        @endif
                    </x-detail>
                    <x-detail label="Neck size">{{ $product->neck_size_mm ? Capacity::trim($product->neck_size_mm).' mm' : '' }}</x-detail>
                    <x-detail label="Neck type">{{ $product->neck_type }}</x-detail>
                    <x-detail label="Neck height">{{ $product->neck_height_mm ? Capacity::trim($product->neck_height_mm).' mm' : '' }}</x-detail>
                    <x-detail label="Weight">
                        {{ $product->weight_grams !== null ? Capacity::trim($product->weight_grams).' g' : '' }}
                        @unless ($product->usesMouldWeight())<span class="badge ml-1 bg-amber-50 text-amber-700">Not the mould's default</span>@endunless
                    </x-detail>
                    @if ($product->basedOn)
                        <x-detail label="Made from" class="sm:col-span-2">
                            <a href="{{ route('masters.products.show', $product->basedOn) }}" class="font-mono text-xs font-semibold text-brand-600 hover:underline">{{ $product->basedOn->code }}</a>
                            <span class="text-slate-600">{{ $product->basedOn->name }}</span>
                        </x-detail>
                    @endif
                    <x-detail label="Material">{{ $product->material }}</x-detail>
                    <x-detail label="Colour">{{ $product->colour }}</x-detail>
                    <x-detail label="Unit">{{ $product->unitLabel() }}</x-detail>
                    <x-detail label="HSN code">{{ $product->hsn_code }}</x-detail>
                    <x-detail label="Added by" class="sm:col-span-2">{{ $product->creator?->name }}{{ $product->creator ? ' · ' : '' }}{{ $product->created_at?->format('d M Y') }}</x-detail>
                    @if ($product->remarks)
                        <x-detail label="Remarks" class="sm:col-span-2 lg:col-span-4"><span class="whitespace-pre-line">{{ $product->remarks }}</span></x-detail>
                    @endif
                </dl>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <div>
                    <h2 class="card-title">Mould &amp; machines</h2>
                    <p class="text-xs text-slate-500">The mould this product is made with, and the machines that mould runs on.</p>
                </div>
            </div>

            @if (! $product->isManufactured())
                <p class="px-5 py-8 text-center text-sm text-slate-500">Trading product — bought from suppliers and resold, so no mould or machine is involved.</p>
            @elseif (! $mould)
                <p class="m-5 rounded border border-dashed border-amber-300 bg-amber-50 px-3 py-4 text-center text-sm text-amber-800">No mould linked — edit the product to select one.</p>
            @else
                <div class="p-5">
                    <div class="flex items-start justify-between gap-2">
                        <div class="text-sm">
                            <span class="badge mr-1 {{ $mould->mould_type === 'preform' ? 'bg-sky-50 text-sky-700' : 'bg-violet-50 text-violet-700' }}">{{ $mould->typeLabel() }}</span>
                            <span class="font-mono text-xs font-semibold text-navy-800">{{ $mould->code }}</span>
                            @module('mould_master', 'view')
                                <a href="{{ route('masters.moulds.show', $mould) }}" class="ml-1 font-semibold text-slate-800 hover:text-brand-600 hover:underline">{{ $mould->name }}</a>
                            @else
                                <span class="ml-1 font-semibold text-slate-800">{{ $mould->name }}</span>
                            @endmodule
                            <div class="mt-1 text-xs text-slate-500">{{ $mould->cavities }} cavity · {{ $mould->location ?: 'location not set' }}</div>
                        </div>
                        <span class="badge shrink-0 {{ $mould->statusTone() }}">{{ $mould->statusLabel() }}</span>
                    </div>
                    <div class="mt-3 border-t border-slate-100 pt-3">
                        <span class="text-xs font-semibold text-slate-500">Runs on:</span>
                        @forelse ($mould->machines as $machine)
                            <span class="badge mt-1 mr-1 {{ $machine->status === 'active' ? 'bg-slate-100 text-slate-700' : 'bg-amber-50 text-amber-700' }}" title="{{ $machine->statusLabel() }}">{{ $machine->code }} · {{ $machine->name }}</span>
                        @empty
                            <span class="text-xs text-amber-700">no machine assigned to this mould yet</span>
                        @endforelse
                    </div>
                </div>
            @endif
        </div>

        <a href="{{ route('masters.products.index') }}" class="inline-block text-sm font-semibold text-brand-600 hover:underline">← Back to products</a>
    </div>
@endsection

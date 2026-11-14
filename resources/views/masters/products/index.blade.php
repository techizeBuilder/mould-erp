@extends('layouts.app')

@section('title', 'Product Master')
@section('breadcrumb', 'Masters / Product Master')

@section('content')
    <div class="card">
        <div class="card-header flex-wrap">
            <h2 class="card-title">Products @module('product_master', 'view')<span class="ml-1 text-sm font-normal text-slate-500">({{ $products->total() }})</span>@endmodule</h2>
            @module('product_master', 'create')
                <a href="{{ route('masters.products.create') }}" class="btn btn-primary"><x-icon name="plus" class="h-4 w-4" /> Add Product</a>
            @endmodule
        </div>

        @module('product_master', 'view')
            <form method="GET" class="flex flex-wrap items-end gap-3 border-b border-slate-200 bg-slate-50/60 px-5 py-3">
                <div class="min-w-[200px] flex-1">
                    <div class="relative">
                        <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400" />
                        <input name="search" value="{{ request('search') }}" class="form-input pl-9" placeholder="Search serial no., name, bill name, client, colour or HSN">
                    </div>
                </div>
                <select name="type" class="form-input w-auto">
                    <option value="">All product types</option>
                    @foreach (\App\Models\Product::TYPES as $value => $label)
                        <option value="{{ $value }}" @selected(request('type') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <select name="business" class="form-input w-auto">
                    <option value="">Made or traded</option>
                    @foreach (\App\Models\Product::BUSINESS_TYPES as $value => $label)
                        <option value="{{ $value }}" @selected(request('business') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <select name="owner" class="form-input w-auto">
                    <option value="">VR &amp; client</option>
                    @foreach (\App\Models\Product::OWNER_TYPES as $value => $label)
                        <option value="{{ $value }}" @selected(request('owner') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <select name="material" class="form-input w-auto">
                    <option value="">Any material</option>
                    @foreach (\App\Models\Product::MATERIALS as $material)
                        <option @selected(request('material') === $material)>{{ $material }}</option>
                    @endforeach
                </select>
                <select name="status" class="form-input w-auto">
                    <option value="">Any status</option>
                    @foreach (\App\Models\Product::STATUSES as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <button class="btn btn-secondary">Filter</button>
                @if (request()->hasAny(['search', 'type', 'business', 'owner', 'material', 'status']))
                    <a href="{{ route('masters.products.index') }}" class="text-sm font-semibold text-slate-500 hover:text-slate-700">Reset</a>
                @endif
            </form>

            <div class="overflow-x-auto">
                <table class="table-classic">
                    <thead>
                    <tr>
                        <th class="w-14"></th>
                        <th>Serial no.</th>
                        <th>Product</th>
                        <th>Type</th>
                        <th>Business</th>
                        <th>Volume</th>
                        <th>Neck</th>
                        <th class="text-right">Weight (g)</th>
                        <th>Material</th>
                        <th class="text-center">Mould</th>
                        <th>Status</th>
                        <th class="text-right">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($products as $product)
                        <tr>
                            <td>
                                <div class="flex h-10 w-10 items-center justify-center overflow-hidden rounded border border-slate-200 bg-slate-50">
                                    @if ($product->imageUrl())
                                        <img src="{{ $product->imageUrl() }}" alt="" class="h-full w-full object-contain" loading="lazy">
                                    @else
                                        <x-icon name="box" class="h-5 w-5 text-slate-300" />
                                    @endif
                                </div>
                            </td>
                            <td class="font-mono text-xs font-semibold whitespace-nowrap text-navy-800">{{ $product->code }}</td>
                            <td>
                                <a href="{{ route('masters.products.show', $product) }}" class="font-semibold text-slate-800 hover:text-brand-600 hover:underline">{{ $product->name }}</a>
                                <div class="text-xs text-slate-500">Bill: {{ $product->bill_name }} · {{ $product->unitLabel() }}</div>
                                @if ($product->isClientProduct())<span class="badge mt-1 bg-rose-50 text-rose-700">{{ $product->ownerLabel() }}</span>@endif
                            </td>
                            <td>{{ $product->typeLabel() }}</td>
                            <td>
                                <span class="badge {{ match ($product->business_type) { 'trading' => 'bg-amber-50 text-amber-700', 'both' => 'bg-brand-50 text-brand-700', default => 'bg-slate-100 text-slate-700' } }}">{{ $product->businessTypeLabel() }}</span>
                            </td>
                            <td class="whitespace-nowrap">{{ \App\Support\Capacity::format($product->capacity_ml) ?? '—' }}</td>
                            <td class="whitespace-nowrap">
                                {{ $product->neck_size_mm ? \App\Support\Capacity::trim($product->neck_size_mm).' mm' : '—' }}
                                @if ($product->neck)<div class="font-mono text-xs text-slate-500">{{ $product->neck->code }}</div>@endif
                            </td>
                            <td class="text-right tabular-nums">{{ $product->weight_grams !== null ? \App\Support\Capacity::trim($product->weight_grams) : '—' }}</td>
                            <td>{{ $product->material ?: '—' }}</td>
                            <td class="text-center tabular-nums">
                                @if (! $product->isManufactured())
                                    <span class="text-slate-400" title="Trading only — no mould needed">n/a</span>
                                @elseif (! $product->mould)
                                    <span class="badge bg-amber-50 text-amber-700" title="Manufactured product without a mould">None</span>
                                @else
                                    <span class="font-mono text-xs font-semibold text-navy-800">{{ $product->mould->code }}</span>
                                @endif
                            </td>
                            <td><span class="badge {{ $product->statusTone() }}">{{ $product->statusLabel() }}</span></td>
                            <td>
                                <div class="flex justify-end gap-1.5">
                                    <a href="{{ route('masters.products.show', $product) }}" class="btn btn-secondary btn-sm" title="View"><x-icon name="eye" class="h-4 w-4" /></a>
                                    @module('product_master', 'edit')
                                        <a href="{{ route('masters.products.edit', $product) }}" class="btn btn-secondary btn-sm" title="Edit"><x-icon name="pencil" class="h-4 w-4" /></a>
                                    @endmodule
                                    @module('product_master', 'delete')
                                        <form method="POST" action="{{ route('masters.products.destroy', $product) }}" data-confirm="Delete product &quot;{{ $product->code }} – {{ $product->name }}&quot;? To keep its history, set its status to Inactive instead.">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-sm border border-red-200 bg-white text-red-600 hover:bg-red-50" title="Delete"><x-icon name="trash" class="h-4 w-4" /></button>
                                        </form>
                                    @endmodule
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="12" class="py-12 text-center">
                                <x-icon name="box" class="mx-auto h-10 w-10 text-slate-300" />
                                <p class="mt-2 text-slate-500">No products found.</p>
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            @if ($products->hasPages())
                <div class="border-t border-slate-200 px-5 py-3">{{ $products->links() }}</div>
            @endif
        @else
            @include('masters._no-view')
        @endmodule
    </div>
@endsection

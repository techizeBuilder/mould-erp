@extends('layouts.app')

@section('title', $neck->name)
@section('breadcrumb', 'Masters / Neck Master / '.$neck->code)

@use('App\Support\Capacity')

@php $mm = fn ($v) => $v !== null ? Capacity::trim($v).' mm' : ''; @endphp

@section('content')
    <div class="max-w-5xl space-y-6">
        <div class="card">
            <div class="card-header flex-wrap">
                <div>
                    <div class="font-mono text-xs font-semibold text-navy-700">{{ $neck->code }}</div>
                    <h2 class="card-title">{{ $neck->name }} <span class="badge ml-1 {{ $neck->statusTone() }}">{{ $neck->statusLabel() }}</span></h2>
                </div>
                <div class="flex flex-wrap gap-2">
                    @module('neck_master', 'edit')
                        <a href="{{ route('masters.necks.edit', $neck) }}" class="btn btn-secondary"><x-icon name="pencil" class="h-4 w-4" /> Edit</a>
                    @endmodule
                    @if ($neck->moulds->isEmpty() && $neck->products->isEmpty())
                        @module('neck_master', 'delete')
                            <form method="POST" action="{{ route('masters.necks.destroy', $neck) }}" data-confirm="Delete neck &quot;{{ $neck->code }}&quot;?">
                                @csrf @method('DELETE')
                                <button class="btn border border-red-200 bg-white text-red-600 hover:bg-red-50"><x-icon name="trash" class="h-4 w-4" /> Delete</button>
                            </form>
                        @endmodule
                    @endif
                </div>
            </div>
            <dl class="grid gap-5 p-5 sm:grid-cols-2 lg:grid-cols-4">
                <x-detail label="Neck size">{{ $mm($neck->neck_size_mm) }}</x-detail>
                <x-detail label="Neck type">{{ $neck->neck_type }}</x-detail>
                <x-detail label="Neck height">{{ $mm($neck->neck_height_mm) }}</x-detail>
                <x-detail label="Inner diameter">{{ $mm($neck->inner_dia_mm) }}</x-detail>
                <x-detail label="Outer / thread diameter">{{ $mm($neck->outer_dia_mm) }}</x-detail>
                <x-detail label="Added by">{{ $neck->creator?->name }}{{ $neck->creator ? ' · ' : '' }}{{ $neck->created_at?->format('d M Y') }}</x-detail>
                @if ($neck->remarks)<x-detail label="Remarks" class="sm:col-span-2 lg:col-span-4"><span class="whitespace-pre-line">{{ $neck->remarks }}</span></x-detail>@endif
            </dl>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <div class="card">
                <div class="card-header"><h2 class="card-title">Moulds with this default neck <span class="ml-1 text-sm font-normal text-slate-500">({{ $neck->moulds->count() }})</span></h2></div>
                <ul class="divide-y divide-slate-100">
                    @forelse ($neck->moulds as $mould)
                        <li class="px-5 py-2.5 text-sm">
                            <a href="{{ route('masters.moulds.show', $mould) }}" class="font-mono text-xs font-semibold text-brand-600 hover:underline">{{ $mould->code }}</a>
                            <div class="text-slate-700">{{ $mould->name }}</div>
                        </li>
                    @empty
                        <li class="px-5 py-4 text-sm text-slate-500">None.</li>
                    @endforelse
                </ul>
            </div>
            <div class="card">
                <div class="card-header"><h2 class="card-title">Products made with this neck <span class="ml-1 text-sm font-normal text-slate-500">({{ $neck->products->count() }})</span></h2></div>
                <ul class="divide-y divide-slate-100">
                    @forelse ($neck->products as $product)
                        <li class="px-5 py-2.5 text-sm">
                            <a href="{{ route('masters.products.show', $product) }}" class="font-mono text-xs font-semibold text-brand-600 hover:underline">{{ $product->code }}</a>
                            <div class="text-slate-700">{{ $product->name }}</div>
                        </li>
                    @empty
                        <li class="px-5 py-4 text-sm text-slate-500">None.</li>
                    @endforelse
                </ul>
            </div>
        </div>

        <a href="{{ route('masters.necks.index') }}" class="inline-block text-sm font-semibold text-brand-600 hover:underline">← Back to necks</a>
    </div>
@endsection

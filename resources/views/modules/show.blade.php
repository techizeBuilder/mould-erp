@extends('layouts.app')

@section('title', $module['label'])
@section('breadcrumb', 'Modules / '.$module['label'])

@section('content')
    <div class="card">
        <div class="card-header">
            <div class="flex items-center gap-3">
                <span class="flex h-10 w-10 items-center justify-center rounded bg-navy-900 text-gold-500"><x-icon :name="$module['icon']" /></span>
                <div>
                    <h2 class="card-title">{{ $module['label'] }}</h2>
                    <p class="text-xs text-slate-500">{{ $module['description'] }}</p>
                </div>
            </div>
            @module($key, 'create')
                <button class="btn btn-primary" disabled title="Available once this module is built"><x-icon name="plus" class="h-4 w-4" /> Add New</button>
            @endmodule
        </div>

        <div class="p-5">
            <p class="text-sm font-semibold text-slate-700">Your access in this module</p>
            <div class="mt-3 flex flex-wrap gap-2">
                @foreach (['view' => 'View', 'create' => 'Create', 'edit' => 'Edit', 'delete' => 'Delete'] as $action => $label)
                    @module($key, $action)
                        <span class="badge gap-1 bg-emerald-50 px-2.5 py-1 text-emerald-700"><x-icon name="check" class="h-3.5 w-3.5" /> {{ $label }}</span>
                    @else
                        <span class="badge gap-1 bg-slate-100 px-2.5 py-1 text-slate-500 line-through"><x-icon name="x" class="h-3.5 w-3.5" /> {{ $label }}</span>
                    @endmodule
                @endforeach
            </div>

            <div class="mt-6 rounded border border-dashed border-slate-300 bg-slate-50 p-10 text-center">
                <x-icon name="cog" class="mx-auto h-10 w-10 text-slate-300" />
                <p class="mt-3 font-semibold text-slate-700">{{ $module['label'] }} screens are coming soon</p>
                <p class="mt-1 text-sm text-slate-500">Access to this module is already controlled by the rights shown above.</p>
            </div>
        </div>
    </div>
@endsection

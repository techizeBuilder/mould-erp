@extends('layouts.app')

@section('title', 'Add Sub-Admin')
@section('breadcrumb', 'User Management / Sub-Admins / Add')

@section('content')
    <div class="card max-w-4xl">
        <div class="card-header">
            <h2 class="card-title">Sub-admin details</h2>
            <span class="text-xs text-slate-500">Step 1 of 2 — all modules are switched on by default (Delete off); you can change this next</span>
        </div>
        <form method="POST" action="{{ route('admin.users.store') }}">
            @include('admin.users._form')
            <div class="flex justify-end gap-2 border-t border-slate-200 bg-slate-50/60 px-5 py-3">
                <a href="{{ route('admin.users.index') }}" class="btn btn-secondary">Cancel</a>
                <button class="btn btn-primary">Save &amp; set access rights</button>
            </div>
        </form>
    </div>
@endsection

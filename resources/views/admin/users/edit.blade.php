@extends('layouts.app')

@section('title', 'Edit Sub-Admin')
@section('breadcrumb', 'User Management / Sub-Admins / '.$user->name)

@section('content')
    <div class="card max-w-4xl">
        <div class="card-header">
            <h2 class="card-title">Edit {{ $user->name }}</h2>
            <a href="{{ route('admin.users.permissions.edit', $user) }}" class="btn btn-secondary btn-sm"><x-icon name="key" class="h-4 w-4" /> Access rights</a>
        </div>
        <form method="POST" action="{{ route('admin.users.update', $user) }}">
            @method('PUT')
            @include('admin.users._form')
            <div class="flex justify-end gap-2 border-t border-slate-200 bg-slate-50/60 px-5 py-3">
                <a href="{{ route('admin.users.index') }}" class="btn btn-secondary">Cancel</a>
                <button class="btn btn-primary">Save changes</button>
            </div>
        </form>
    </div>
@endsection

@extends('layouts.app')

@section('title', 'Designations')
@section('breadcrumb', 'User Management / Designations')

@section('content')
    <div class="grid gap-6 lg:grid-cols-3">
        <div class="card self-start">
            <div class="card-header">
                <h2 class="card-title">{{ $editing ? 'Edit designation' : 'Add designation' }}</h2>
                @if ($editing)
                    <a href="{{ route('admin.designations.index') }}" class="text-sm font-semibold text-slate-500 hover:text-slate-700">Cancel</a>
                @endif
            </div>
            <form method="POST" action="{{ $editing ? route('admin.designations.update', $editing) : route('admin.designations.store') }}" class="space-y-4 p-5">
                @csrf
                @if ($editing) @method('PUT') @endif
                <div>
                    <label for="name" class="form-label">Designation name <span class="text-red-600">*</span></label>
                    <input id="name" name="name" value="{{ old('name', $editing?->name) }}" required maxlength="100" class="form-input" placeholder="e.g. Accountant, Sales Manager">
                    @error('name') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="description" class="form-label">Description</label>
                    <textarea id="description" name="description" rows="3" maxlength="255" class="form-input" placeholder="What this role is responsible for">{{ old('description', $editing?->description) }}</textarea>
                    @error('description') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <button class="btn btn-primary w-full">{{ $editing ? 'Update designation' : 'Add designation' }}</button>
            </form>
        </div>

        <div class="card lg:col-span-2">
            <div class="card-header">
                <h2 class="card-title">All designations <span class="ml-1 text-sm font-normal text-slate-500">({{ $designations->count() }})</span></h2>
            </div>
            <div class="overflow-x-auto">
                <table class="table-classic">
                    <thead><tr><th>Name</th><th>Description</th><th class="text-center">Sub-admins</th><th class="text-right">Actions</th></tr></thead>
                    <tbody>
                    @forelse ($designations as $d)
                        <tr class="{{ $editing?->is($d) ? 'bg-brand-50' : '' }}">
                            <td class="font-semibold text-slate-800">{{ $d->name }}</td>
                            <td class="text-slate-500">{{ $d->description ?: '—' }}</td>
                            <td class="text-center">
                                <a href="{{ route('admin.users.index', ['designation' => $d->id]) }}" class="font-semibold text-brand-600 tabular-nums hover:underline">{{ $d->users_count }}</a>
                            </td>
                            <td>
                                <div class="flex justify-end gap-1.5">
                                    <a href="{{ route('admin.designations.edit', $d) }}" class="btn btn-secondary btn-sm" title="Edit"><x-icon name="pencil" class="h-4 w-4" /></a>
                                    <form method="POST" action="{{ route('admin.designations.destroy', $d) }}" data-confirm="Delete designation &quot;{{ $d->name }}&quot;?">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm border border-red-200 bg-white text-red-600 hover:bg-red-50" title="Delete"><x-icon name="trash" class="h-4 w-4" /></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="py-10 text-center text-slate-500">No designations yet. Add your first one on the left.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

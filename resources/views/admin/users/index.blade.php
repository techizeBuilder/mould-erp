@extends('layouts.app')

@section('title', 'Sub-Admins')
@section('breadcrumb', 'User Management / Sub-Admins')

@section('content')
    <div class="card">
        <div class="card-header flex-wrap">
            <h2 class="card-title">All sub-admins <span class="ml-1 text-sm font-normal text-slate-500">({{ $users->total() }})</span></h2>
            <a href="{{ route('admin.users.create') }}" class="btn btn-primary"><x-icon name="plus" class="h-4 w-4" /> Add Sub-Admin</a>
        </div>

        <form method="GET" class="flex flex-wrap items-end gap-3 border-b border-slate-200 bg-slate-50/60 px-5 py-3">
            <div class="min-w-[200px] flex-1">
                <div class="relative">
                    <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400" />
                    <input name="search" value="{{ request('search') }}" class="form-input pl-9" placeholder="Search name, email or phone">
                </div>
            </div>
            <select name="designation" class="form-input w-auto min-w-[170px]">
                <option value="">All designations</option>
                @foreach ($designations as $d)
                    <option value="{{ $d->id }}" @selected(request('designation') == $d->id)>{{ $d->name }}</option>
                @endforeach
            </select>
            <select name="status" class="form-input w-auto">
                <option value="">Any status</option>
                <option value="active" @selected(request('status') === 'active')>Active</option>
                <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
            </select>
            <button class="btn btn-secondary">Filter</button>
            @if (request()->hasAny(['search', 'designation', 'status']))
                <a href="{{ route('admin.users.index') }}" class="text-sm font-semibold text-slate-500 hover:text-slate-700">Reset</a>
            @endif
        </form>

        <div class="overflow-x-auto">
            <table class="table-classic">
                <thead>
                <tr>
                    <th>Sub-Admin</th>
                    <th>Designation</th>
                    <th>Phone</th>
                    <th>Modules</th>
                    <th>Status</th>
                    <th>Last login</th>
                    <th class="text-right">Actions</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($users as $u)
                    <tr>
                        <td>
                            <div class="flex items-center gap-3">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-navy-800 text-xs font-bold text-white">{{ $u->initials() }}</span>
                                <div>
                                    <div class="font-semibold text-slate-800">{{ $u->name }}</div>
                                    <div class="text-xs text-slate-500">{{ $u->email }}</div>
                                </div>
                            </div>
                        </td>
                        <td><span class="badge bg-brand-50 text-brand-700">{{ $u->designation?->name ?? 'Unassigned' }}</span></td>
                        <td class="whitespace-nowrap">{{ $u->phone ?: '—' }}</td>
                        <td class="tabular-nums">{{ $u->modules_count }} / {{ count(config('modules')) }}</td>
                        <td>
                            <form method="POST" action="{{ route('admin.users.toggle-status', $u) }}">
                                @csrf @method('PATCH')
                                <button title="Click to {{ $u->is_active ? 'deactivate' : 'activate' }}"
                                        class="badge cursor-pointer {{ $u->is_active ? 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                                    {{ $u->is_active ? 'Active' : 'Inactive' }}
                                </button>
                            </form>
                        </td>
                        <td class="text-xs whitespace-nowrap text-slate-500">{{ $u->last_login_at?->diffForHumans() ?? 'Never' }}</td>
                        <td>
                            <div class="flex justify-end gap-1.5">
                                <a href="{{ route('admin.users.permissions.edit', $u) }}" class="btn btn-secondary btn-sm" title="Access rights"><x-icon name="key" class="h-4 w-4" /> Rights</a>
                                <a href="{{ route('admin.users.edit', $u) }}" class="btn btn-secondary btn-sm" title="Edit"><x-icon name="pencil" class="h-4 w-4" /></a>
                                <form method="POST" action="{{ route('admin.users.destroy', $u) }}" data-confirm="Delete sub-admin &quot;{{ $u->name }}&quot;? This cannot be undone.">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm border border-red-200 bg-white text-red-600 hover:bg-red-50" title="Delete"><x-icon name="trash" class="h-4 w-4" /></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="py-12 text-center">
                            <x-icon name="users" class="mx-auto h-10 w-10 text-slate-300" />
                            <p class="mt-2 text-slate-500">No sub-admins found.</p>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        @if ($users->hasPages())
            <div class="border-t border-slate-200 px-5 py-3">{{ $users->links() }}</div>
        @endif
    </div>
@endsection

@extends('layouts.app')

@section('title', 'Dashboard')
@section('breadcrumb', 'Welcome back, '.auth()->user()->name)

@section('content')
    @superadmin
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ([
                ['Total Sub-Admins', $stats['sub_admins'], 'users', 'bg-brand-50 text-brand-600'],
                ['Active', $stats['active'], 'check', 'bg-emerald-50 text-emerald-600'],
                ['Inactive', $stats['inactive'], 'power', 'bg-amber-50 text-amber-600'],
                ['Designations', $stats['designations'], 'tag', 'bg-slate-100 text-slate-600'],
            ] as [$label, $value, $icon, $tone])
                <div class="card flex items-center gap-4 p-5">
                    <div class="flex h-12 w-12 items-center justify-center rounded {{ $tone }}"><x-icon :name="$icon" class="h-6 w-6" /></div>
                    <div>
                        <div class="text-2xl font-bold text-navy-900 tabular-nums">{{ $value }}</div>
                        <div class="text-sm text-slate-500">{{ $label }}</div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-6 grid gap-6 xl:grid-cols-3">
            <div class="card xl:col-span-2">
                <div class="card-header">
                    <h2 class="card-title">Recently added sub-admins</h2>
                    <a href="{{ route('admin.users.index') }}" class="text-sm font-semibold text-brand-600 hover:underline">View all</a>
                </div>
                <div class="overflow-x-auto">
                    <table class="table-classic">
                        <thead><tr><th>Name</th><th>Designation</th><th>Status</th><th class="text-right">Access</th></tr></thead>
                        <tbody>
                        @forelse ($recentUsers as $u)
                            <tr>
                                <td>
                                    <div class="font-semibold text-slate-800">{{ $u->name }}</div>
                                    <div class="text-xs text-slate-500">{{ $u->email }}</div>
                                </td>
                                <td>{{ $u->designation?->name ?? '—' }}</td>
                                <td>
                                    <span class="badge {{ $u->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">{{ $u->is_active ? 'Active' : 'Inactive' }}</span>
                                </td>
                                <td class="text-right">
                                    <a href="{{ route('admin.users.permissions.edit', $u) }}" class="text-sm font-semibold text-brand-600 hover:underline">Rights</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="py-8 text-center text-slate-500">No sub-admins yet. <a href="{{ route('admin.users.create') }}" class="font-semibold text-brand-600 hover:underline">Create the first one</a>.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h2 class="card-title">Quick actions</h2></div>
                <div class="space-y-2 p-5">
                    <a href="{{ route('admin.users.create') }}" class="btn btn-primary w-full"><x-icon name="user-plus" class="h-4 w-4" /> Add Sub-Admin</a>
                    <a href="{{ route('admin.designations.index') }}" class="btn btn-secondary w-full"><x-icon name="tag" class="h-4 w-4" /> Manage Designations</a>
                    <a href="{{ route('admin.users.index') }}" class="btn btn-secondary w-full"><x-icon name="key" class="h-4 w-4" /> Assign Access Rights</a>
                </div>
            </div>
        </div>
    @endsuperadmin

    <div class="{{ auth()->user()->isSuperAdmin() ? 'mt-6' : '' }}">
        <h2 class="mb-3 text-sm font-bold tracking-wide text-slate-600 uppercase">{{ auth()->user()->isSuperAdmin() ? 'All modules' : 'Your modules' }}</h2>
        @if (count($modules))
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($modules as $key => $mod)
                    <a href="{{ \App\Support\Modules::url($key) }}" class="card group flex items-start gap-4 p-5 transition hover:border-brand-500">
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded bg-navy-900 text-gold-500"><x-icon :name="$mod['icon']" /></div>
                        <div>
                            <div class="font-semibold text-navy-900 group-hover:text-brand-600">{{ $mod['label'] }}</div>
                            <div class="mt-0.5 text-sm text-slate-500">{{ $mod['description'] }}</div>
                        </div>
                    </a>
                @endforeach
            </div>
        @else
            <div class="card p-10 text-center">
                <x-icon name="lock" class="mx-auto h-10 w-10 text-slate-300" />
                <p class="mt-3 font-semibold text-slate-700">No modules assigned yet</p>
                <p class="mt-1 text-sm text-slate-500">Ask your administrator to grant you access to the modules you need.</p>
            </div>
        @endif
    </div>
@endsection

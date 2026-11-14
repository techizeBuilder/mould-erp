<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') · {{ config('app.name') }}</title>
    <link rel="icon" type="image/png" href="{{ asset('storage/logo.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Source+Sans+3:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
@php
    $authUser = auth()->user();
    $navLink = fn (bool $active) => $active
        ? 'flex items-center gap-3 rounded px-3 py-2 text-sm font-semibold bg-navy-700 text-white border-l-[3px] border-gold-500'
        : 'flex items-center gap-3 rounded px-3 py-2 text-sm font-medium text-navy-200 hover:bg-navy-800 hover:text-white border-l-[3px] border-transparent';
    $userMgmtOpen = request()->routeIs('admin.*');
@endphp
<body class="min-h-screen bg-page font-sans text-slate-800 antialiased">
<div class="flex min-h-screen">

    {{-- Sidebar --}}
    <aside id="sidebar" class="fixed inset-y-0 left-0 z-40 flex w-64 -translate-x-full flex-col bg-navy-900 transition-transform duration-200 lg:translate-x-0">
        <div class="flex h-16 items-center gap-3 border-b border-navy-800 px-5">
            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded bg-white p-1 shadow-sm">
                <img src="{{ asset('storage/logo.png') }}" alt="{{ config('app.name') }} logo" class="h-full w-full object-contain">
            </div>
            <div class="leading-tight">
                <div class="text-[15px] font-bold tracking-wide text-white">{{ config('app.name') }}</div>
                <div class="text-[11px] tracking-wider text-navy-300 uppercase">Admin Panel</div>
            </div>
        </div>

        <nav class="flex-1 space-y-6 overflow-y-auto px-3 py-5">
            <div class="space-y-1">
                <a href="{{ route('dashboard') }}" class="{{ $navLink(request()->routeIs('dashboard')) }}">
                    <x-icon name="home" /> Dashboard
                </a>
            </div>

            @superadmin
                <div>
                    <p class="mb-2 px-3 text-[11px] font-bold tracking-widest text-navy-300/70 uppercase">Administration</p>
                    <button type="button" data-toggle="user-mgmt"
                            class="flex w-full items-center gap-3 rounded border-l-[3px] border-transparent px-3 py-2 text-sm font-medium {{ $userMgmtOpen ? 'text-white' : 'text-navy-200' }} hover:bg-navy-800 hover:text-white">
                        <x-icon name="users" /> <span class="flex-1 text-left">User Management</span>
                        <x-icon name="chevron-down" class="h-4 w-4 transition-transform {{ $userMgmtOpen ? 'rotate-180' : '' }}" data-chevron />
                    </button>
                    <div id="user-mgmt" class="{{ $userMgmtOpen ? '' : 'hidden' }} mt-1 space-y-1 pl-4">
                        <a href="{{ route('admin.users.index') }}" class="{{ $navLink(request()->routeIs('admin.users.index', 'admin.users.edit', 'admin.users.permissions.*')) }}">
                            <x-icon name="users" class="h-4 w-4" /> Sub-Admins
                        </a>
                        <a href="{{ route('admin.users.create') }}" class="{{ $navLink(request()->routeIs('admin.users.create')) }}">
                            <x-icon name="user-plus" class="h-4 w-4" /> Add Sub-Admin
                        </a>
                        <a href="{{ route('admin.designations.index') }}" class="{{ $navLink(request()->routeIs('admin.designations.*')) }}">
                            <x-icon name="tag" class="h-4 w-4" /> Designations
                        </a>
                    </div>
                </div>
            @endsuperadmin

            @php $navGroups = \App\Support\Modules::grouped($authUser->visibleModules()); @endphp

            {{-- Expandable groups such as Masters --}}
            @foreach (\App\Support\Modules::GROUPS as $groupKey => $group)
                @continue(empty($navGroups[$groupKey]))
                @php $groupOpen = collect($navGroups[$groupKey])->keys()->contains(fn ($k) => \App\Support\Modules::isActive($k)); @endphp
                <div>
                    <button type="button" data-toggle="group-{{ $groupKey }}"
                            class="flex w-full items-center gap-3 rounded border-l-[3px] border-transparent px-3 py-2 text-sm font-medium {{ $groupOpen ? 'text-white' : 'text-navy-200' }} hover:bg-navy-800 hover:text-white">
                        <x-icon :name="$group['icon']" /> <span class="flex-1 text-left">{{ $group['label'] }}</span>
                        <x-icon name="chevron-down" class="h-4 w-4 transition-transform {{ $groupOpen ? 'rotate-180' : '' }}" data-chevron />
                    </button>
                    <div id="group-{{ $groupKey }}" class="{{ $groupOpen ? '' : 'hidden' }} mt-1 space-y-1 pl-4">
                        @foreach ($navGroups[$groupKey] as $key => $mod)
                            <a href="{{ \App\Support\Modules::url($key) }}" class="{{ $navLink(\App\Support\Modules::isActive($key)) }}">
                                <x-icon :name="$mod['icon']" class="h-4 w-4" /> {{ $mod['label'] }}
                            </a>
                        @endforeach
                    </div>
                </div>
            @endforeach

            @if (! empty($navGroups['']))
                <div>
                    <p class="mb-2 px-3 text-[11px] font-bold tracking-widest text-navy-300/70 uppercase">Modules</p>
                    <div class="space-y-1">
                        @foreach ($navGroups[''] as $key => $mod)
                            <a href="{{ \App\Support\Modules::url($key) }}" class="{{ $navLink(\App\Support\Modules::isActive($key)) }}">
                                <x-icon :name="$mod['icon']" /> {{ $mod['label'] }}
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            <div>
                <p class="mb-2 px-3 text-[11px] font-bold tracking-widest text-navy-300/70 uppercase">Account</p>
                <a href="{{ route('profile.edit') }}" class="{{ $navLink(request()->routeIs('profile.*')) }}">
                    <x-icon name="user" /> My Profile
                </a>
            </div>
        </nav>

        <div class="border-t border-navy-800 px-5 py-3 text-[11px] text-navy-300">
            &copy; {{ date('Y') }} {{ config('app.name') }}
        </div>
    </aside>
    <div id="sidebar-backdrop" class="fixed inset-0 z-30 hidden bg-navy-950/50 lg:hidden"></div>

    {{-- Main --}}
    <div class="flex min-w-0 flex-1 flex-col lg:pl-64">
        <header class="sticky top-0 z-20 flex h-16 items-center justify-between gap-4 border-b border-slate-200 bg-white px-4 shadow-sm sm:px-6">
            <div class="flex min-w-0 items-center gap-3">
                <button type="button" id="sidebar-open" class="rounded p-1.5 text-slate-600 hover:bg-slate-100 lg:hidden" aria-label="Open menu">
                    <x-icon name="menu" class="h-6 w-6" />
                </button>
                <div class="min-w-0">
                    <h1 class="truncate text-lg font-bold text-navy-900">@yield('title', 'Dashboard')</h1>
                    @hasSection('breadcrumb')
                        <div class="truncate text-xs text-slate-500">@yield('breadcrumb')</div>
                    @endif
                </div>
            </div>

            <details class="relative" id="user-menu">
                <summary class="flex cursor-pointer list-none items-center gap-3 rounded px-2 py-1.5 hover:bg-slate-100">
                    <span class="flex h-9 w-9 items-center justify-center rounded-full bg-brand-600 text-sm font-bold text-white">{{ $authUser->initials() }}</span>
                    <span class="hidden text-left leading-tight sm:block">
                        <span class="block text-sm font-semibold text-slate-800">{{ $authUser->name }}</span>
                        <span class="block text-xs text-slate-500">{{ $authUser->roleLabel() }}</span>
                    </span>
                    <x-icon name="chevron-down" class="hidden h-4 w-4 text-slate-500 sm:block" />
                </summary>
                <div class="absolute right-0 mt-2 w-56 rounded-md border border-slate-200 bg-white py-1 shadow-lg">
                    <div class="border-b border-slate-100 px-4 py-2.5">
                        <div class="truncate text-sm font-semibold">{{ $authUser->name }}</div>
                        <div class="truncate text-xs text-slate-500">{{ $authUser->email }}</div>
                    </div>
                    <a href="{{ route('profile.edit') }}" class="flex items-center gap-2 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">
                        <x-icon name="user" class="h-4 w-4" /> My Profile
                    </a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="flex w-full items-center gap-2 px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                            <x-icon name="logout" class="h-4 w-4" /> Sign out
                        </button>
                    </form>
                </div>
            </details>
        </header>

        <main class="flex-1 p-4 sm:p-6">
            @if (session('success'))
                <div class="mb-5 flex items-start gap-3 rounded border border-emerald-200 border-l-4 border-l-emerald-600 bg-emerald-50 px-4 py-3 text-sm text-emerald-800" data-flash>
                    <x-icon name="check" class="mt-0.5 h-4 w-4 shrink-0" /> <span>{{ session('success') }}</span>
                </div>
            @endif
            @if (session('error'))
                <div class="mb-5 flex items-start gap-3 rounded border border-red-200 border-l-4 border-l-red-600 bg-red-50 px-4 py-3 text-sm text-red-800">
                    <x-icon name="alert" class="mt-0.5 h-4 w-4 shrink-0" /> <span>{{ session('error') }}</span>
                </div>
            @endif

            @yield('content')
        </main>
    </div>
</div>

<script>
    (function () {
        const sidebar = document.getElementById('sidebar');
        const backdrop = document.getElementById('sidebar-backdrop');
        const setOpen = (open) => {
            sidebar.classList.toggle('-translate-x-full', !open);
            backdrop.classList.toggle('hidden', !open);
        };
        document.getElementById('sidebar-open')?.addEventListener('click', () => setOpen(true));
        backdrop.addEventListener('click', () => setOpen(false));

        document.querySelectorAll('[data-toggle]').forEach((btn) => {
            btn.addEventListener('click', () => {
                document.getElementById(btn.dataset.toggle).classList.toggle('hidden');
                btn.querySelector('[data-chevron]')?.classList.toggle('rotate-180');
            });
        });

        // Close the user dropdown when clicking elsewhere.
        const menu = document.getElementById('user-menu');
        document.addEventListener('click', (e) => { if (!menu.contains(e.target)) menu.removeAttribute('open'); });

        // Confirm destructive actions: <form data-confirm="Are you sure?">
        document.querySelectorAll('form[data-confirm]').forEach((form) => {
            form.addEventListener('submit', (e) => { if (!confirm(form.dataset.confirm)) e.preventDefault(); });
        });
    })();
</script>
@stack('scripts')
</body>
</html>

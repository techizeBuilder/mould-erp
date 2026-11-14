<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in · {{ config('app.name') }}</title>
    <link rel="icon" type="image/png" href="{{ asset('storage/logo.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Source+Sans+3:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-page font-sans text-slate-800 antialiased">
<div class="grid min-h-screen lg:grid-cols-2">

    <div class="relative hidden flex-col justify-between overflow-hidden bg-navy-900 p-12 text-white lg:flex">
        <div class="absolute inset-0 opacity-[0.07]" style="background-image: linear-gradient(#fff 1px, transparent 1px), linear-gradient(90deg, #fff 1px, transparent 1px); background-size: 40px 40px;"></div>
        <div class="relative flex items-center gap-3">
            <div class="flex h-14 w-14 items-center justify-center rounded bg-white p-1.5 shadow">
                <img src="{{ asset('storage/logo.png') }}" alt="{{ config('app.name') }} logo" class="h-full w-full object-contain">
            </div>
            <div>
                <div class="text-lg font-bold tracking-wide">{{ config('app.name') }}</div>
                <div class="text-xs tracking-widest text-navy-300 uppercase">Enterprise Resource Planning</div>
            </div>
        </div>
        <div class="relative max-w-md">
            <h2 class="text-3xl leading-snug font-bold">One place to run sales, production, quality and accounts.</h2>
            <div class="mt-4 h-1 w-16 rounded bg-gold-500"></div>
            <p class="mt-5 text-navy-200">Every team member sees exactly the modules and actions their role needs — nothing more.</p>
        </div>
        <div class="relative text-xs text-navy-300">&copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</div>
    </div>

    <div class="flex items-center justify-center px-4 py-12 sm:px-8">
        <div class="w-full max-w-sm">
            <img src="{{ asset('storage/logo.png') }}" alt="{{ config('app.name') }} logo" class="mb-6 h-20 w-auto lg:hidden">


            <h1 class="text-2xl font-bold text-navy-900">Sign in</h1>
            <p class="mt-1 text-sm text-slate-500">Enter your credentials to access the admin panel.</p>

            @if (session('status'))
                <div class="mt-6 rounded border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm text-emerald-800">{{ session('status') }}</div>
            @endif

            @if ($errors->any())
                <div class="mt-6 rounded border border-red-200 border-l-4 border-l-red-600 bg-red-50 px-4 py-2.5 text-sm text-red-800">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('login.attempt') }}" class="mt-6 space-y-5">
                @csrf
                <div>
                    <label for="email" class="form-label">Email address</label>
                    <div class="relative">
                        <x-icon name="mail" class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400" />
                        <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username" class="form-input pl-9" placeholder="you@company.com">
                    </div>
                </div>
                <div>
                    <label for="password" class="form-label">Password</label>
                    <div class="relative">
                        <x-icon name="lock" class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400" />
                        <input id="password" name="password" type="password" required autocomplete="current-password" class="form-input pr-16 pl-9" placeholder="••••••••">
                        <button type="button" onclick="const p=document.getElementById('password');p.type=p.type==='password'?'text':'password';this.textContent=p.type==='password'?'Show':'Hide';"
                                class="absolute top-1/2 right-2 -translate-y-1/2 rounded px-2 py-1 text-xs font-semibold text-brand-600 hover:bg-brand-50">Show</button>
                    </div>
                </div>
                <label class="flex items-center gap-2 text-sm text-slate-600">
                    <input type="checkbox" name="remember" class="h-4 w-4 rounded border-slate-300 accent-brand-600"> Keep me signed in
                </label>
                <button type="submit" class="btn btn-primary w-full py-2.5">Sign in</button>
            </form>
        </div>
    </div>
</div>
</body>
</html>

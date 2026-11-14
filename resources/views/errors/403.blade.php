<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Access denied · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="flex min-h-screen items-center justify-center bg-page px-4 font-sans text-slate-800">
    <div class="card max-w-md p-10 text-center">
        <img src="{{ asset('storage/logo.png') }}" alt="{{ config('app.name') }} logo" class="mx-auto mb-6 h-14 w-auto">
        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-red-50 text-red-600"><x-icon name="lock" class="h-7 w-7" /></div>
        <h1 class="mt-4 text-xl font-bold text-navy-900">Access denied</h1>
        <p class="mt-2 text-sm text-slate-500">{{ $exception->getMessage() ?: 'You do not have permission to view this page.' }}</p>
        <a href="{{ route('dashboard') }}" class="btn btn-primary mt-6">Back to dashboard</a>
    </div>
</body>
</html>

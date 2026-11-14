@extends('layouts.app')

@section('title', 'My Profile')
@section('breadcrumb', 'Account / My Profile')

@section('content')
    <div class="grid max-w-5xl gap-6 lg:grid-cols-2">
        <div class="card self-start">
            <div class="card-header"><h2 class="card-title">Profile information</h2></div>
            <form method="POST" action="{{ route('profile.update') }}" class="space-y-4 p-5">
                @csrf @method('PUT')
                <div>
                    <label for="name" class="form-label">Full name</label>
                    <input id="name" name="name" value="{{ old('name', $user->name) }}" required class="form-input">
                    @error('name') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="email" class="form-label">Email</label>
                    <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" required class="form-input">
                    @error('email') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="phone" class="form-label">Phone</label>
                    <input id="phone" name="phone" value="{{ old('phone', $user->phone) }}" class="form-input">
                    @error('phone') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <span class="form-label">Role</span>
                    <span class="badge bg-brand-50 text-brand-700">{{ $user->roleLabel() }}</span>
                </div>
                <button class="btn btn-primary">Save profile</button>
            </form>
        </div>

        <div class="card self-start">
            <div class="card-header"><h2 class="card-title">Change password</h2></div>
            <form method="POST" action="{{ route('profile.password') }}" class="space-y-4 p-5">
                @csrf @method('PUT')
                <div>
                    <label for="current_password" class="form-label">Current password</label>
                    <input id="current_password" name="current_password" type="password" required class="form-input" autocomplete="current-password">
                    @error('current_password') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="password" class="form-label">New password</label>
                    <input id="password" name="password" type="password" required class="form-input" autocomplete="new-password">
                    <p class="mt-1 text-xs text-slate-500">At least 8 characters with letters and numbers.</p>
                    @error('password') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="password_confirmation" class="form-label">Confirm new password</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" required class="form-input" autocomplete="new-password">
                </div>
                <button class="btn btn-primary">Change password</button>
            </form>
        </div>
    </div>
@endsection

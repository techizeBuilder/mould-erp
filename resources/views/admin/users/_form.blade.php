@csrf
<div class="grid gap-5 p-5 md:grid-cols-2">
    <div>
        <label for="name" class="form-label">Full name <span class="text-red-600">*</span></label>
        <input id="name" name="name" value="{{ old('name', $user->name) }}" required class="form-input">
        @error('name') <p class="form-error">{{ $message }}</p> @enderror
    </div>
    <div>
        <label for="designation_id" class="form-label">Designation <span class="text-red-600">*</span></label>
        <select id="designation_id" name="designation_id" required class="form-input">
            <option value="">— Select designation —</option>
            @foreach ($designations as $d)
                <option value="{{ $d->id }}" @selected(old('designation_id', $user->designation_id) == $d->id)>{{ $d->name }}</option>
            @endforeach
        </select>
        <p class="mt-1 text-xs text-slate-500">Need a new one? <a href="{{ route('admin.designations.index') }}" class="font-semibold text-brand-600 hover:underline">Add a custom designation</a>.</p>
        @error('designation_id') <p class="form-error">{{ $message }}</p> @enderror
    </div>
    <div>
        <label for="email" class="form-label">Email (login ID) <span class="text-red-600">*</span></label>
        <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" required class="form-input" autocomplete="off">
        @error('email') <p class="form-error">{{ $message }}</p> @enderror
    </div>
    <div>
        <label for="phone" class="form-label">Phone</label>
        <input id="phone" name="phone" value="{{ old('phone', $user->phone) }}" class="form-input">
        @error('phone') <p class="form-error">{{ $message }}</p> @enderror
    </div>
    <div>
        <label for="password" class="form-label">Password @unless ($user->exists)<span class="text-red-600">*</span>@endunless</label>
        <input id="password" name="password" type="password" @unless ($user->exists) required @endunless class="form-input" autocomplete="new-password">
        <p class="mt-1 text-xs text-slate-500">{{ $user->exists ? 'Leave blank to keep the current password. ' : '' }}At least 8 characters with letters and numbers.</p>
        @error('password') <p class="form-error">{{ $message }}</p> @enderror
    </div>
    <div>
        <label for="password_confirmation" class="form-label">Confirm password</label>
        <input id="password_confirmation" name="password_confirmation" type="password" class="form-input" autocomplete="new-password">
    </div>
    <div class="md:col-span-2">
        <label class="inline-flex cursor-pointer items-center gap-3">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1" class="peer sr-only" @checked(old('is_active', $user->is_active))>
            <span class="relative h-6 w-11 rounded-full bg-slate-300 transition peer-checked:bg-emerald-600 after:absolute after:top-0.5 after:left-0.5 after:h-5 after:w-5 after:rounded-full after:bg-white after:shadow after:transition peer-checked:after:translate-x-5"></span>
            <span class="text-sm font-semibold text-slate-700">Account active <span class="font-normal text-slate-500">— inactive users cannot sign in</span></span>
        </label>
    </div>
</div>

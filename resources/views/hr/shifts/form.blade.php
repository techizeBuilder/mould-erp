@extends('layouts.app')

@section('title', $shift->exists ? 'Edit Shift' : 'Add Shift')
@section('breadcrumb', 'HR & Payroll / Shifts & Rotation / '.($shift->exists ? $shift->name : 'Add'))

@section('content')
    <form method="POST" action="{{ $shift->exists ? route('hr.shifts.update', $shift) : route('hr.shifts.store') }}" class="max-w-3xl space-y-6">
        @csrf
        @if ($shift->exists) @method('PUT') @endif

        <div class="card">
            <div class="card-header"><h2 class="card-title">Shift details</h2></div>
            <div class="grid gap-5 p-5 md:grid-cols-3">
                <div class="md:col-span-2">
                    <label for="name" class="form-label">Shift name <span class="text-red-600">*</span></label>
                    <input id="name" name="name" value="{{ old('name', $shift->name) }}" required maxlength="60" class="form-input" placeholder="e.g. Day, Night, General">
                    @error('name') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <span class="form-label">Roster colour</span>
                    <div class="flex flex-wrap gap-2 pt-1">
                        @foreach (\App\Models\Shift::COLOURS as $value => $classes)
                            <label class="cursor-pointer">
                                <input type="radio" name="colour" value="{{ $value }}" class="peer sr-only" @checked(old('colour', $shift->colour) === $value)>
                                <span class="badge {{ $classes }} ring-brand-600 ring-offset-1 peer-checked:ring-2">{{ ucfirst($value) }}</span>
                            </label>
                        @endforeach
                    </div>
                    @error('colour') <p class="form-error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="start_time" class="form-label">Starts at <span class="text-red-600">*</span></label>
                    <input id="start_time" name="start_time" type="time" value="{{ old('start_time', $shift->start_time ? substr($shift->start_time, 0, 5) : '08:00') }}" required class="form-input">
                    @error('start_time') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="end_time" class="form-label">Ends at <span class="text-red-600">*</span></label>
                    <input id="end_time" name="end_time" type="time" value="{{ old('end_time', $shift->end_time ? substr($shift->end_time, 0, 5) : '20:00') }}" required class="form-input">
                    <p class="mt-1 text-xs text-slate-500">Earlier than the start = ends next day (night shift).</p>
                    @error('end_time') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="break_minutes" class="form-label">Break (minutes) <span class="text-red-600">*</span></label>
                    <input id="break_minutes" name="break_minutes" type="number" min="0" max="240" value="{{ old('break_minutes', $shift->break_minutes) }}" required class="form-input">
                    @error('break_minutes') <p class="form-error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="grace_minutes" class="form-label">Late grace (minutes) <span class="text-red-600">*</span></label>
                    <input id="grace_minutes" name="grace_minutes" type="number" min="0" max="120" value="{{ old('grace_minutes', $shift->grace_minutes) }}" required class="form-input">
                    @error('grace_minutes') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div class="pt-7">
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" class="h-4 w-4 accent-brand-600" @checked(old('is_active', $shift->is_active))> Active</label>
                </div>
                <div class="hidden md:block"></div>

                <div class="md:col-span-3">
                    <label for="remarks" class="form-label">Remarks</label>
                    <textarea id="remarks" name="remarks" rows="2" maxlength="1000" class="form-input">{{ old('remarks', $shift->remarks) }}</textarea>
                    @error('remarks') <p class="form-error">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        <div class="flex justify-end gap-2">
            <a href="{{ route('hr.shifts.index') }}" class="btn btn-secondary">Cancel</a>
            <button class="btn btn-primary">{{ $shift->exists ? 'Save changes' : 'Add shift' }}</button>
        </div>
    </form>
@endsection

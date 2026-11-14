@extends('layouts.app')

@section('title', 'Assign Shift')
@section('breadcrumb', 'HR & Payroll / Shifts & Rotation / Assign')

@php
    $employeeOptions = $employees->map(fn ($e) => [
        'id' => $e->id,
        'label' => "{$e->code} – {$e->name}",
        'meta' => collect([$e->department, $e->shift ? 'default: '.$e->shift->name : null])->filter()->join(' · '),
    ])->all();
@endphp

@section('content')
    <form method="POST" action="{{ route('hr.shifts.assign.store') }}" class="max-w-4xl space-y-6">
        @csrf
        <div class="card">
            <div class="card-header">
                <div>
                    <h2 class="card-title">Put employees on a shift</h2>
                    <p class="text-xs text-slate-500">For a date range — e.g. cover for a week. It replaces any other assignment in those dates. Choose "Default shift" to undo assignments.</p>
                </div>
            </div>
            <div class="grid gap-5 p-5 md:grid-cols-3">
                <div>
                    <label for="shift_id" class="form-label">Shift</label>
                    <select id="shift_id" name="shift_id" class="form-input">
                        <option value="">↺ Default shift (remove assignments)</option>
                        @foreach ($shifts as $shift)
                            <option value="{{ $shift->id }}" @selected((int) old('shift_id') === $shift->id)>{{ $shift->name }} ({{ $shift->timing() }})</option>
                        @endforeach
                    </select>
                    @error('shift_id') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="start_date" class="form-label">From <span class="text-red-600">*</span></label>
                    <input id="start_date" name="start_date" type="date" value="{{ old('start_date', now()->toDateString()) }}" required class="form-input">
                    @error('start_date') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="end_date" class="form-label">To <span class="text-red-600">*</span></label>
                    <input id="end_date" name="end_date" type="date" value="{{ old('end_date', now()->addDays(6)->toDateString()) }}" required class="form-input">
                    @error('end_date') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div class="md:col-span-3">
                    <span class="form-label">Employees <span class="text-red-600">*</span></span>
                    <x-checklist name="employee_ids[]" :options="$employeeOptions" :selected="$selected" empty="No active employees yet." />
                    @error('employee_ids') <p class="form-error">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        <div class="flex justify-end gap-2">
            <a href="{{ route('hr.shifts.index') }}" class="btn btn-secondary">Cancel</a>
            <button class="btn btn-primary">Assign</button>
        </div>
    </form>
@endsection

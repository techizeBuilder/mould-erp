@extends('layouts.app')

@section('title', 'Rotate Shifts')
@section('breadcrumb', 'HR & Payroll / Shifts & Rotation / Rotate')

@php
    $employeeOptions = $employees->map(fn ($e) => [
        'id' => $e->id,
        'label' => "{$e->code} – {$e->name}",
        'meta' => collect([$e->department, $e->shift ? 'default: '.$e->shift->name : null])->filter()->join(' · '),
    ])->all();
    $sequence = old('sequence', $shifts->take(2)->pluck('id')->all());
@endphp

@section('content')
    <form method="POST" action="{{ route('hr.shifts.rotate.store') }}" class="max-w-4xl space-y-6">
        @csrf
        <div class="card">
            <div class="card-header">
                <div>
                    <h2 class="card-title">Rotation plan</h2>
                    <p class="text-xs text-slate-500">Employees move through the shifts in order, changing every few days — e.g. Day → Night, switching weekly. This replaces their assignments in the chosen dates.</p>
                </div>
            </div>
            <div class="grid gap-5 p-5 md:grid-cols-4">
                @foreach (range(0, 3) as $i)
                    <div>
                        <label for="sequence_{{ $i }}" class="form-label">{{ $i === 0 ? '1st' : ($i === 1 ? '2nd' : ($i === 2 ? '3rd' : '4th')) }} shift @if ($i < 2)<span class="text-red-600">*</span>@endif</label>
                        <select id="sequence_{{ $i }}" name="sequence[]" class="form-input" @required($i < 2)>
                            <option value="">{{ $i < 2 ? '— Select —' : '— Not used —' }}</option>
                            @foreach ($shifts as $shift)
                                <option value="{{ $shift->id }}" @selected((int) ($sequence[$i] ?? 0) === $shift->id)>{{ $shift->name }} ({{ $shift->timing() }})</option>
                            @endforeach
                        </select>
                    </div>
                @endforeach
                @error('sequence') <p class="form-error md:col-span-4">{{ $message }}</p> @enderror

                <div>
                    <label for="every_days" class="form-label">Change every (days) <span class="text-red-600">*</span></label>
                    <input id="every_days" name="every_days" type="number" min="1" max="90" value="{{ old('every_days', 7) }}" required class="form-input">
                    @error('every_days') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="start_date" class="form-label">From <span class="text-red-600">*</span></label>
                    <input id="start_date" name="start_date" type="date" value="{{ old('start_date', now()->startOfWeek()->addWeek()->toDateString()) }}" required class="form-input">
                    @error('start_date') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="end_date" class="form-label">To <span class="text-red-600">*</span></label>
                    <input id="end_date" name="end_date" type="date" value="{{ old('end_date', now()->startOfWeek()->addWeek()->addWeeks(4)->subDay()->toDateString()) }}" required class="form-input">
                    @error('end_date') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div class="pt-7">
                    <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="stagger" value="1" class="mt-0.5 h-4 w-4 accent-brand-600" @checked(old('stagger'))> <span>Stagger — 1st employee starts on the 1st shift, 2nd on the 2nd shift, and so on</span></label>
                </div>

                <div class="md:col-span-4">
                    <span class="form-label">Employees <span class="text-red-600">*</span></span>
                    <x-checklist name="employee_ids[]" :options="$employeeOptions" :selected="[]" empty="No active employees yet." />
                    @error('employee_ids') <p class="form-error">{{ $message }}</p> @enderror
                    <p class="mt-2 text-xs text-slate-500">Tip: for two crews swapping Day / Night, rotate crew A with Day → Night, then crew B with Night → Day for the same dates.</p>
                </div>
            </div>
        </div>

        <div class="flex justify-end gap-2">
            <a href="{{ route('hr.shifts.index') }}" class="btn btn-secondary">Cancel</a>
            <button class="btn btn-primary"><x-icon name="refresh" class="h-4 w-4" /> Create rotation</button>
        </div>
    </form>
@endsection

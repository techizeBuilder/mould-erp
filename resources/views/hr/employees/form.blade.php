@extends('layouts.app')

@section('title', $employee->exists ? 'Edit Employee' : 'Add Employee')
@section('breadcrumb', 'HR & Payroll / Employees / '.($employee->exists ? $employee->code : 'Add'))

@use('App\Models\Employee')
@php
    $date = fn ($field) => old($field, $employee->{$field}?->format('Y-m-d'));
    $amount = fn ($field) => old($field, $employee->{$field} !== null && (float) $employee->{$field} != 0 ? rtrim(rtrim((string) $employee->{$field}, '0'), '.') : null);
    $checked = fn ($field) => (bool) old($field, $employee->exists || ! old() ? $employee->{$field} : false);
@endphp

@section('content')
    <form method="POST" enctype="multipart/form-data" action="{{ $employee->exists ? route('hr.employees.update', $employee) : route('hr.employees.store') }}" class="max-w-5xl space-y-6">
        @csrf
        @if ($employee->exists) @method('PUT') @endif

        <div class="rounded border border-sky-200 border-l-4 border-l-sky-600 bg-sky-50 px-4 py-3 text-sm text-sky-800">
            Employees are HR records only — they do not get a login to this software.
        </div>

        <div class="card">
            <div class="card-header"><h2 class="card-title">Personal details</h2></div>
            <div class="grid gap-5 p-5 md:grid-cols-3">
                <div>
                    <span class="form-label">Employee code</span>
                    <div class="form-input bg-slate-50 font-mono text-slate-600">{{ $employee->code ?? $nextCode }}</div>
                    @unless ($employee->exists)<p class="mt-1 text-xs text-slate-500">Assigned automatically when saved.</p>@endunless
                </div>
                <div class="md:col-span-2">
                    <label for="name" class="form-label">Full name <span class="text-red-600">*</span></label>
                    <input id="name" name="name" value="{{ old('name', $employee->name) }}" required maxlength="255" class="form-input">
                    @error('name') <p class="form-error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="father_name" class="form-label">Father's / spouse's name</label>
                    <input id="father_name" name="father_name" value="{{ old('father_name', $employee->father_name) }}" maxlength="255" class="form-input">
                    @error('father_name') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="gender" class="form-label">Gender</label>
                    <select id="gender" name="gender" class="form-input">
                        <option value="">— Select —</option>
                        @foreach (Employee::GENDERS as $value => $label)
                            <option value="{{ $value }}" @selected(old('gender', $employee->gender) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('gender') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="date_of_birth" class="form-label">Date of birth</label>
                    <input id="date_of_birth" name="date_of_birth" type="date" value="{{ $date('date_of_birth') }}" class="form-input">
                    @error('date_of_birth') <p class="form-error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="phone" class="form-label">Mobile</label>
                    <input id="phone" name="phone" value="{{ old('phone', $employee->phone) }}" maxlength="20" class="form-input">
                    @error('phone') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="email" class="form-label">Email</label>
                    <input id="email" name="email" type="email" value="{{ old('email', $employee->email) }}" maxlength="255" class="form-input">
                    @error('email') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="aadhaar" class="form-label">Aadhaar no.</label>
                    <input id="aadhaar" name="aadhaar" value="{{ old('aadhaar', $employee->aadhaar) }}" maxlength="12" inputmode="numeric" class="form-input" placeholder="12 digits">
                    @error('aadhaar') <p class="form-error">{{ $message }}</p> @enderror
                </div>

                <div class="md:col-span-3">
                    <label for="address" class="form-label">Address</label>
                    <textarea id="address" name="address" rows="2" maxlength="1000" class="form-input">{{ old('address', $employee->address) }}</textarea>
                    @error('address') <p class="form-error">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h2 class="card-title">Employment</h2></div>
            <div class="grid gap-5 p-5 md:grid-cols-3">
                <div>
                    <label for="department" class="form-label">Department</label>
                    <input id="department" name="department" list="departments" value="{{ old('department', $employee->department) }}" maxlength="100" class="form-input" placeholder="e.g. Production">
                    <datalist id="departments">@foreach ($departments->merge(['Production', 'Quality', 'Stores', 'Maintenance', 'Dispatch', 'Accounts', 'Administration'])->unique() as $d)<option value="{{ $d }}">@endforeach</datalist>
                    @error('department') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="designation" class="form-label">Designation</label>
                    <input id="designation" name="designation" list="designations" value="{{ old('designation', $employee->designation) }}" maxlength="100" class="form-input" placeholder="e.g. Machine Operator">
                    <datalist id="designations">@foreach ($designations->merge(['Machine Operator', 'Helper', 'Supervisor', 'QC Inspector', 'Store Keeper', 'Driver'])->unique() as $d)<option value="{{ $d }}">@endforeach</datalist>
                    @error('designation') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="employment_type" class="form-label">Employment type <span class="text-red-600">*</span></label>
                    <select id="employment_type" name="employment_type" required class="form-input">
                        @foreach (Employee::EMPLOYMENT_TYPES as $value => $label)
                            <option value="{{ $value }}" @selected(old('employment_type', $employee->employment_type) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('employment_type') <p class="form-error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="joining_date" class="form-label">Date of joining <span class="text-red-600">*</span></label>
                    <input id="joining_date" name="joining_date" type="date" value="{{ $date('joining_date') }}" required class="form-input">
                    @error('joining_date') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="status" class="form-label">Status <span class="text-red-600">*</span></label>
                    <select id="status" name="status" required class="form-input">
                        @foreach (Employee::STATUSES as $value => $label)
                            <option value="{{ $value }}" @selected(old('status', $employee->status) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('status') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div data-leaving>
                    <label for="leaving_date" class="form-label">Last working day <span class="text-red-600">*</span></label>
                    <input id="leaving_date" name="leaving_date" type="date" value="{{ $date('leaving_date') }}" class="form-input">
                    @error('leaving_date') <p class="form-error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="shift_id" class="form-label">Default shift</label>
                    <select id="shift_id" name="shift_id" class="form-input">
                        <option value="">— None —</option>
                        @foreach ($shifts as $shift)
                            <option value="{{ $shift->id }}" @selected((int) old('shift_id', $employee->shift_id) === $shift->id)>{{ $shift->name }} ({{ $shift->timing() }})</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-slate-500">Used unless a rotation or assignment says otherwise.</p>
                    @error('shift_id') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="weekly_off" class="form-label">Weekly off <span class="text-red-600">*</span></label>
                    <select id="weekly_off" name="weekly_off" required class="form-input">
                        @foreach (Employee::WEEKDAYS as $value => $label)
                            <option value="{{ $value }}" @selected((int) old('weekly_off', $employee->weekly_off) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('weekly_off') <p class="form-error">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <div>
                    <h2 class="card-title">Monthly salary structure (₹)</h2>
                    <p class="text-xs text-slate-500">Full-month amounts. Payroll pays them pro rata to the days paid.</p>
                </div>
                <div class="text-right">
                    <div class="text-xs font-semibold text-slate-500 uppercase">Gross / month</div>
                    <div class="text-lg font-bold text-navy-900" data-gross>₹0</div>
                </div>
            </div>
            <div class="grid gap-5 p-5 md:grid-cols-3">
                @foreach (Employee::SALARY_COMPONENTS as $field => $label)
                    <div>
                        <label for="{{ $field }}" class="form-label">{{ $label }} @if ($field === 'basic')<span class="text-red-600">*</span>@endif</label>
                        <input id="{{ $field }}" name="{{ $field }}" type="number" step="0.01" min="0" value="{{ $amount($field) }}" class="form-input" data-salary @if ($field === 'basic') required @endif placeholder="0">
                        @error($field) <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                @endforeach
                <div>
                    <label for="ot_rate_per_hour" class="form-label">Overtime rate (₹ / hour)</label>
                    <input id="ot_rate_per_hour" name="ot_rate_per_hour" type="number" step="0.01" min="0" value="{{ old('ot_rate_per_hour', $employee->ot_rate_per_hour) }}" class="form-input" placeholder="Auto">
                    <p class="mt-1 text-xs text-slate-500">Leave blank for {{ config('payroll.overtime_multiplier') }}× the hourly rate (gross ÷ days ÷ {{ config('payroll.standard_hours_per_day') }} h).</p>
                    @error('ot_rate_per_hour') <p class="form-error">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h2 class="card-title">Statutory &amp; bank details</h2></div>
            <div class="grid gap-5 p-5 md:grid-cols-3">
                <div class="space-y-2 pt-1">
                    <label class="flex items-center gap-2 text-sm"><input type="hidden" name="pf_applicable" value="0"><input type="checkbox" name="pf_applicable" value="1" class="h-4 w-4 accent-brand-600" @checked($checked('pf_applicable'))> PF applicable <span class="text-xs text-slate-500">({{ config('payroll.pf.employee_rate') }}% of Basic)</span></label>
                    <label class="flex items-center gap-2 text-sm"><input type="hidden" name="esi_applicable" value="0"><input type="checkbox" name="esi_applicable" value="1" class="h-4 w-4 accent-brand-600" @checked($checked('esi_applicable'))> ESI applicable <span class="text-xs text-slate-500">(gross ≤ ₹{{ \App\Support\Money::format(config('payroll.esi.wage_limit'), 0) }})</span></label>
                </div>
                <div>
                    <label for="professional_tax" class="form-label">Professional tax (₹ / month)</label>
                    <input id="professional_tax" name="professional_tax" type="number" step="0.01" min="0" value="{{ old('professional_tax', $employee->professional_tax !== null ? (float) $employee->professional_tax : null) }}" class="form-input">
                    @error('professional_tax') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="pan" class="form-label">PAN</label>
                    <input id="pan" name="pan" value="{{ old('pan', $employee->pan) }}" maxlength="10" class="form-input uppercase" placeholder="ABCDE1234F">
                    @error('pan') <p class="form-error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="uan" class="form-label">UAN</label>
                    <input id="uan" name="uan" value="{{ old('uan', $employee->uan) }}" maxlength="12" inputmode="numeric" class="form-input" placeholder="12 digits">
                    @error('uan') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="pf_number" class="form-label">PF account no.</label>
                    <input id="pf_number" name="pf_number" value="{{ old('pf_number', $employee->pf_number) }}" maxlength="30" class="form-input">
                    @error('pf_number') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="esi_number" class="form-label">ESI IP no.</label>
                    <input id="esi_number" name="esi_number" value="{{ old('esi_number', $employee->esi_number) }}" maxlength="20" class="form-input">
                    @error('esi_number') <p class="form-error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="bank_name" class="form-label">Bank name</label>
                    <input id="bank_name" name="bank_name" value="{{ old('bank_name', $employee->bank_name) }}" maxlength="100" class="form-input">
                    @error('bank_name') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="bank_account" class="form-label">Account number</label>
                    <input id="bank_account" name="bank_account" value="{{ old('bank_account', $employee->bank_account) }}" maxlength="30" class="form-input">
                    @error('bank_account') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="ifsc" class="form-label">IFSC</label>
                    <input id="ifsc" name="ifsc" value="{{ old('ifsc', $employee->ifsc) }}" maxlength="11" class="form-input uppercase" placeholder="SBIN0001234">
                    @error('ifsc') <p class="form-error">{{ $message }}</p> @enderror
                </div>

                <x-image-input label="Photo" :url="$employee->imageUrl()" class="md:col-span-2" />
                <div class="md:col-span-3">
                    <label for="remarks" class="form-label">Remarks</label>
                    <textarea id="remarks" name="remarks" rows="2" maxlength="2000" class="form-input">{{ old('remarks', $employee->remarks) }}</textarea>
                    @error('remarks') <p class="form-error">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        <div class="flex justify-end gap-2">
            <a href="{{ $employee->exists ? route('hr.employees.show', $employee) : route('hr.employees.index') }}" class="btn btn-secondary">Cancel</a>
            <button class="btn btn-primary">{{ $employee->exists ? 'Save changes' : 'Add employee' }}</button>
        </div>
    </form>
@endsection

@push('scripts')
<script>
    (() => {
        const status = document.getElementById('status');
        const leaving = document.querySelector('[data-leaving]');
        const gross = document.querySelector('[data-gross]');
        const inr = new Intl.NumberFormat('en-IN', { maximumFractionDigits: 2 });

        const sync = () => {
            const left = status.value === 'left';
            leaving.classList.toggle('invisible', !left);
            leaving.querySelector('input').required = left;
            const total = [...document.querySelectorAll('[data-salary]')].reduce((s, i) => s + (parseFloat(i.value) || 0), 0);
            gross.textContent = '₹' + inr.format(total);
        };
        document.addEventListener('input', sync);
        status.addEventListener('change', sync);
        sync();
    })();
</script>
@endpush

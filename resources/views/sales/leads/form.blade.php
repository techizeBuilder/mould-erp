@extends('layouts.app')

@section('title', $lead->exists ? 'Edit Lead' : 'Add Lead')
@section('breadcrumb', 'Sales & CRM / Leads / '.($lead->exists ? $lead->code : 'Add'))

@use('App\Models\Lead')

@section('content')
    <form method="POST" action="{{ $lead->exists ? route('sales.leads.update', $lead) : route('sales.leads.store') }}" class="max-w-5xl space-y-6">
        @csrf
        @if ($lead->exists) @method('PUT') @endif

        <div class="card">
            <div class="card-header"><h2 class="card-title">Enquiry source</h2></div>
            <div class="grid gap-5 p-5 md:grid-cols-3">
                <div>
                    <span class="form-label">Lead code</span>
                    <div class="form-input bg-slate-50 font-mono text-slate-600">{{ $lead->code ?? $nextCode }}</div>
                    @unless ($lead->exists)<p class="mt-1 text-xs text-slate-500">Assigned automatically when saved.</p>@endunless
                </div>
                <x-form.select name="source" label="Lead source" :options="Lead::SOURCES" :value="$lead->source" required placeholder="— Select source —" />
                <x-form.input name="source_detail" label="Source details" :value="$lead->source_detail" maxlength="150" placeholder="e.g. PlastIndia 2026, referred by Sharma Oils" />

                <x-form.input name="enquiry_date" label="Enquiry date" type="date" :value="$lead->enquiry_date?->format('Y-m-d')" required />
                <x-form.input name="expected_close_date" label="Expected order date" type="date" :value="$lead->expected_close_date?->format('Y-m-d')" />
                <div>
                    <label for="assigned_to" class="form-label">Assigned to</label>
                    <select id="assigned_to" name="assigned_to" class="form-input">
                        <option value="">— Unassigned —</option>
                        @foreach ($assignees as $user)
                            <option value="{{ $user->id }}" @selected((int) old('assigned_to', $lead->assigned_to) === $user->id)>{{ $user->name }}</option>
                        @endforeach
                    </select>
                </div>

                <x-form.select name="status" label="Status" :options="collect($statuses)->mapWithKeys(fn ($s) => [$s => Lead::STATUSES[$s]])->all()" :value="$lead->status" required
                               hint="Qualified, Quoted and Won are set automatically from qualification and quotations." />
                <div data-lost class="md:col-span-2">
                    <x-form.select name="lost_reason" label="Reason lost" :options="Lead::LOST_REASONS" :value="$lead->lost_reason" placeholder="— Select —" />
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h2 class="card-title">Buyer details</h2></div>
            <div class="grid gap-5 p-5 md:grid-cols-3">
                <x-form.input class="md:col-span-2" name="company_name" label="Company name" :value="$lead->company_name" required maxlength="255" />
                <x-form.select name="industry" label="Industry" :options="Lead::INDUSTRIES" :value="$lead->industry" required placeholder="— Select —" hint="Decides material, neck and compliance needs." />

                <x-form.input name="contact_person" label="Contact person" :value="$lead->contact_person" maxlength="100" />
                <x-form.input name="phone" label="Mobile" :value="$lead->phone" required maxlength="20" />
                <x-form.input name="email" label="Email" type="email" :value="$lead->email" maxlength="255" />

                <x-form.input name="city" label="City" :value="$lead->city" maxlength="100" />
                <x-form.input name="state" label="State" :value="$lead->state" maxlength="100" />
                <x-form.input name="gstin" label="GSTIN" :value="$lead->gstin" maxlength="15" input-class="uppercase" placeholder="27ABCDE1234F1Z5" />

                <div class="md:col-span-3">
                    <label for="address" class="form-label">Address</label>
                    <textarea id="address" name="address" rows="2" maxlength="1000" class="form-input">{{ old('address', $lead->address) }}</textarea>
                    @error('address') <p class="form-error">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        @include('sales._product-lines', ['mode' => 'lead'])

        <div class="card">
            <div class="p-5">
                <label for="notes" class="form-label">Notes</label>
                <textarea id="notes" name="notes" rows="3" maxlength="2000" class="form-input" placeholder="Anything else the buyer mentioned">{{ old('notes', $lead->notes) }}</textarea>
                @error('notes') <p class="form-error">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="flex justify-end gap-2">
            <a href="{{ $lead->exists ? route('sales.leads.show', $lead) : route('sales.leads.index') }}" class="btn btn-secondary">Cancel</a>
            <button class="btn btn-primary">{{ $lead->exists ? 'Save changes' : 'Add lead' }}</button>
        </div>
    </form>
@endsection

@push('scripts')
<script>
    (() => {
        const status = document.getElementById('status');
        const lost = document.querySelector('[data-lost]');
        const sync = () => {
            const isLost = status.value === 'lost';
            lost.classList.toggle('hidden', !isLost);
            lost.querySelector('select').required = isLost;
        };
        status.addEventListener('change', sync);
        sync();
    })();
</script>
@endpush

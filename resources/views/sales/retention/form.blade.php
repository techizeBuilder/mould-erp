@extends('layouts.app')

@section('title', $customer->exists ? 'Edit Customer' : 'Add Customer')
@section('breadcrumb', 'Sales & CRM / Customer Retention / '.($customer->exists ? $customer->code : 'Add'))

@use('App\Models\Customer')
@use('App\Models\Lead')
@use('App\Support\BuyerScore')
@use('App\Support\RetentionHealth')

@section('content')
    <form method="POST" action="{{ $customer->exists ? route('sales.retention.update', $customer) : route('sales.retention.store') }}" class="max-w-5xl space-y-6">
        @csrf
        @if ($customer->exists) @method('PUT') @endif

        <div class="card">
            <div class="card-header"><h2 class="card-title">Customer details</h2></div>
            <div class="grid gap-5 p-5 md:grid-cols-3">
                <div>
                    <span class="form-label">Customer code</span>
                    <div class="form-input bg-slate-50 font-mono text-slate-600">{{ $customer->code ?? $nextCode }}</div>
                </div>
                <x-form.input class="md:col-span-2" name="company_name" label="Company name" :value="$customer->company_name" required maxlength="255" />

                <x-form.input name="contact_person" label="Contact person" :value="$customer->contact_person" maxlength="100" />
                <x-form.input name="phone" label="Mobile" :value="$customer->phone" required maxlength="20" />
                <x-form.input name="email" label="Email" type="email" :value="$customer->email" maxlength="255" />

                <x-form.input name="city" label="City" :value="$customer->city" maxlength="100" />
                <x-form.input name="state" label="State" :value="$customer->state" maxlength="100" />
                <x-form.input name="gstin" label="GSTIN" :value="$customer->gstin" maxlength="15" input-class="uppercase" placeholder="27ABCDE1234F1Z5" />

                <div class="md:col-span-3">
                    <label for="address" class="form-label">Address</label>
                    <textarea id="address" name="address" rows="2" maxlength="1000" class="form-input">{{ old('address', $customer->address) }}</textarea>
                    @error('address') <p class="form-error">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h2 class="card-title">Business &amp; retention</h2></div>
            <div class="grid gap-5 p-5 md:grid-cols-3">
                <x-form.select name="industry" label="Industry" :options="Lead::INDUSTRIES" :value="$customer->industry" placeholder="— Select —" />
                <x-form.select name="buyer_type" label="Buyer type" :options="BuyerScore::options('buyer_type')" :value="$customer->buyer_type" placeholder="— Select —" />
                <x-form.select name="payment_terms" label="Payment terms" :options="BuyerScore::options('payment_terms')" :value="$customer->payment_terms" placeholder="— Select —" />

                <x-form.select name="source" label="How they came to us" :options="Lead::SOURCES" :value="$customer->source" placeholder="— Select —" />
                <x-form.input name="reorder_cycle_days" label="Reorder cycle (days)" type="number" min="1" step="1" :value="$customer->reorder_cycle_days"
                              hint="Leave blank to use their average gap between orders ({{ RetentionHealth::DEFAULT_CYCLE_DAYS }} days until there are two orders)." />
                <div>
                    <label for="assigned_to" class="form-label">Account owner</label>
                    <select id="assigned_to" name="assigned_to" class="form-input">
                        <option value="">— Unassigned —</option>
                        @foreach ($assignees as $user)
                            <option value="{{ $user->id }}" @selected((int) old('assigned_to', $customer->assigned_to) === $user->id)>{{ $user->name }}</option>
                        @endforeach
                    </select>
                </div>

                <x-form.select name="status" label="Status" :options="Customer::STATUSES" :value="$customer->status" required />
                <div class="md:col-span-2">
                    <label for="remarks" class="form-label">Remarks</label>
                    <textarea id="remarks" name="remarks" rows="2" maxlength="2000" class="form-input">{{ old('remarks', $customer->remarks) }}</textarea>
                    @error('remarks') <p class="form-error">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        <div class="flex justify-end gap-2">
            <a href="{{ $customer->exists ? route('sales.retention.show', $customer) : route('sales.retention.index') }}" class="btn btn-secondary">Cancel</a>
            <button class="btn btn-primary">{{ $customer->exists ? 'Save changes' : 'Add customer' }}</button>
        </div>
    </form>
@endsection

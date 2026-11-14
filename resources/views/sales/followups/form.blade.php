@extends('layouts.app')

@section('title', $followUp->exists ? 'Edit Follow-up' : 'Schedule Follow-up')
@section('breadcrumb', 'Sales & CRM / Follow-ups / '.($followUp->exists ? 'Edit' : 'Schedule'))

@use('App\Models\FollowUp')

@php
    $selectedKind = old('related_type', $kind);
    $selectedId = (int) old('related_id', $relatedId);
    $groups = [
        'lead' => $leads->mapWithKeys(fn ($l) => [$l->id => "{$l->code} – {$l->company_name}"]),
        'quotation' => $quotations->mapWithKeys(fn ($q) => [$q->id => $q->number().' – '.$q->buyerName()]),
        'customer' => $customers->mapWithKeys(fn ($c) => [$c->id => "{$c->code} – {$c->company_name}"]),
    ];
@endphp

@section('content')
    <form method="POST" action="{{ $followUp->exists ? route('sales.followups.update', $followUp) : route('sales.followups.store') }}" class="max-w-3xl space-y-6">
        @csrf
        @if ($followUp->exists) @method('PUT') @endif

        <div class="card">
            <div class="grid gap-5 p-5 md:grid-cols-2">
                <x-form.select name="related_type" label="Follow-up on" :options="['lead' => 'A lead', 'quotation' => 'A quotation', 'customer' => 'A customer']" :value="$kind" required />
                <div>
                    <label for="related_id" class="form-label">Record <span class="text-red-600">*</span></label>
                    <select id="related_id" name="related_id" required class="form-input">
                        <option value="">— Select —</option>
                        @foreach ($groups as $group => $options)
                            @foreach ($options as $id => $label)
                                <option value="{{ $id }}" data-group="{{ $group }}" @selected($selectedKind === $group && $selectedId === $id)>{{ $label }}</option>
                            @endforeach
                        @endforeach
                    </select>
                    @error('related_id') <p class="form-error">{{ $message }}</p> @enderror
                </div>

                <x-form.select name="type" label="How" :options="FollowUp::TYPES" :value="$followUp->type" required />
                <x-form.select name="purpose" label="Purpose" :options="FollowUp::PURPOSES" :value="$followUp->purpose" required />

                <x-form.input name="due_at" label="Date & time" type="datetime-local" :value="$followUp->due_at?->format('Y-m-d\TH:i')" required />
                <div>
                    <label for="assigned_to" class="form-label">Assigned to</label>
                    <select id="assigned_to" name="assigned_to" class="form-input">
                        <option value="">— Unassigned —</option>
                        @foreach ($assignees as $user)
                            <option value="{{ $user->id }}" @selected((int) old('assigned_to', $followUp->assigned_to) === $user->id)>{{ $user->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="md:col-span-2">
                    <label for="product_id" class="form-label">Product discussed</label>
                    <select id="product_id" name="product_id" class="form-input">
                        <option value="">— Not about one product —</option>
                        @foreach ($products as $product)
                            <option value="{{ $product->id }}" @selected((int) old('product_id', $followUp->product_id) === $product->id)>{{ $product->code }} – {{ $product->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label for="agenda" class="form-label">Agenda</label>
                    <textarea id="agenda" name="agenda" rows="3" maxlength="1000" class="form-input" placeholder="e.g. Check whether the 500 ml sample passed the leak test">{{ old('agenda', $followUp->agenda) }}</textarea>
                    @error('agenda') <p class="form-error">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        <div class="flex justify-end gap-2">
            <a href="{{ route('sales.followups.index') }}" class="btn btn-secondary">Cancel</a>
            <button class="btn btn-primary">{{ $followUp->exists ? 'Save changes' : 'Schedule' }}</button>
        </div>
    </form>
@endsection

@push('scripts')
<script>
    (() => {
        const type = document.getElementById('related_type');
        const record = document.getElementById('related_id');
        const options = [...record.querySelectorAll('option[data-group]')];

        // Only offer records of the chosen kind.
        const sync = () => {
            options.forEach((o) => { o.hidden = o.disabled = o.dataset.group !== type.value; });
            if (record.selectedOptions[0]?.disabled) record.value = '';
        };
        type.addEventListener('change', sync);
        sync();
    })();
</script>
@endpush

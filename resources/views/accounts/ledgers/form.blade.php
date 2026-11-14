@extends('layouts.app')

@section('title', $ledger->exists ? 'Edit Ledger' : 'Add Ledger')
@section('breadcrumb', 'Accounts / Ledgers / '.($ledger->exists ? $ledger->code : 'Add'))

@use('App\Models\Ledger')

@php $currentGroup = old('ledger_group', $ledger->ledger_group); @endphp

@section('content')
    <form method="POST" action="{{ $ledger->exists ? route('accounts.ledgers.update', $ledger) : route('accounts.ledgers.store') }}" class="max-w-4xl space-y-6">
        @csrf
        @if ($ledger->exists) @method('PUT') @endif

        @if ($ledger->isCustomerLedger())
            <div class="rounded border border-sky-200 border-l-4 border-l-sky-600 bg-sky-50 px-4 py-3 text-sm text-sky-800">
                This ledger belongs to a customer. Their name and contact details come from Customer Retention; only the opening balance, PAN, status and remarks are set here.
            </div>
        @elseif ($ledger->isSystem())
            <div class="rounded border border-sky-200 border-l-4 border-l-sky-600 bg-sky-50 px-4 py-3 text-sm text-sky-800">
                This is a built-in ledger that vouchers post to, so its name and type can't change.
            </div>
        @endif

        <div class="card">
            <div class="card-header"><h2 class="card-title">Ledger</h2></div>
            <div class="grid gap-5 p-5 md:grid-cols-3">
                <div>
                    <span class="form-label">Ledger code</span>
                    <div class="form-input bg-slate-50 font-mono text-slate-600">{{ $ledger->code ?? $nextCode }}</div>
                </div>
                <div class="md:col-span-2">
                    @if ($ledger->isLocked())
                        <span class="form-label">Ledger name</span>
                        <div class="form-input bg-slate-50 text-slate-600">{{ $ledger->name }}</div>
                    @else
                        <x-form.input name="name" label="Ledger name" :value="$ledger->name" required maxlength="255" placeholder="e.g. HDFC Current A/c, Electricity Charges, ABC Resins" />
                    @endif
                </div>

                <div class="md:col-span-2">
                    @if ($ledger->isLocked())
                        <span class="form-label">Ledger type</span>
                        <div class="form-input bg-slate-50 text-slate-600">{{ $ledger->groupLabel() }}</div>
                        <input type="hidden" id="ledger_group" value="{{ $ledger->ledger_group }}">
                    @else
                        <label for="ledger_group" class="form-label">Ledger type <span class="text-red-600">*</span></label>
                        <select id="ledger_group" name="ledger_group" required class="form-input">
                            <option value="">— Select type —</option>
                            @foreach (Ledger::groupOptions() as $nature => $groups)
                                <optgroup label="{{ $nature }}">
                                    @foreach ($groups as $value => $label)
                                        <option value="{{ $value }}" @selected($currentGroup === $value)>{{ $label }}</option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                        @error('ledger_group') <p class="form-error">{{ $message }}</p> @enderror
                    @endif
                </div>
                @unless ($ledger->isSystem())
                    <x-form.select name="status" label="Status" :options="Ledger::STATUSES" :value="$ledger->status" required />
                @else
                    <input type="hidden" name="status" value="{{ $ledger->status }}">
                @endunless

                <div>
                    <label for="opening_balance" class="form-label">Opening balance (₹)</label>
                    <div class="flex">
                        <input id="opening_balance" name="opening_balance" type="number" min="0" step="0.01" value="{{ old('opening_balance', (float) $ledger->opening_balance ?: null) }}" class="form-input rounded-r-none" placeholder="0">
                        <select name="opening_type" aria-label="Debit or credit" class="form-input w-20 rounded-l-none border-l-0 bg-slate-50">
                            <option value="dr" @selected(old('opening_type', $ledger->opening_type) === 'dr')>Dr</option>
                            <option value="cr" @selected(old('opening_type', $ledger->opening_type) === 'cr')>Cr</option>
                        </select>
                    </div>
                    @error('opening_balance') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div class="md:col-span-2">
                    <p class="pt-7 text-xs text-slate-500">Balance when you start using this software. Dr = money with you or owed to you. Cr = money you owe.</p>
                </div>
            </div>
        </div>

        <div class="card" data-section="party">
            <div class="card-header"><h2 class="card-title">Party details</h2></div>
            <div class="grid gap-5 p-5 md:grid-cols-3">
                @unless ($ledger->isCustomerLedger())
                    <x-form.input name="contact_person" label="Contact person" :value="$ledger->contact_person" maxlength="100" />
                    <x-form.input name="phone" label="Phone" :value="$ledger->phone" maxlength="20" />
                    <x-form.input name="email" label="Email" type="email" :value="$ledger->email" maxlength="255" />
                    <x-form.input name="state" label="State" :value="$ledger->state" maxlength="100" />
                    <x-form.input name="gstin" label="GSTIN" :value="$ledger->gstin" maxlength="15" input-class="uppercase" placeholder="27ABCDE1234F1Z5" />
                @endunless
                <x-form.input name="pan" label="PAN" :value="$ledger->pan" maxlength="10" input-class="uppercase" placeholder="ABCDE1234F" />
                @unless ($ledger->isCustomerLedger())
                    <div class="md:col-span-3">
                        <label for="address" class="form-label">Address</label>
                        <textarea id="address" name="address" rows="2" maxlength="1000" class="form-input">{{ old('address', $ledger->address) }}</textarea>
                        @error('address') <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                @endunless
            </div>
        </div>

        <div class="card" data-section="bank">
            <div class="card-header"><h2 class="card-title">Bank details</h2></div>
            <div class="grid gap-5 p-5 md:grid-cols-3">
                <x-form.input name="bank_name" label="Bank name" :value="$ledger->bank_name" maxlength="100" />
                <x-form.input name="account_number" label="Account number" :value="$ledger->account_number" maxlength="30" />
                <x-form.input name="ifsc" label="IFSC" :value="$ledger->ifsc" maxlength="11" input-class="uppercase" placeholder="SBIN0001234" />
            </div>
        </div>

        <div class="card">
            <div class="p-5">
                <label for="remarks" class="form-label">Remarks</label>
                <textarea id="remarks" name="remarks" rows="2" maxlength="2000" class="form-input">{{ old('remarks', $ledger->remarks) }}</textarea>
                @error('remarks') <p class="form-error">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="flex justify-end gap-2">
            <a href="{{ $ledger->exists ? route('accounts.ledgers.show', $ledger) : route('accounts.ledgers.index') }}" class="btn btn-secondary">Cancel</a>
            <button class="btn btn-primary">{{ $ledger->exists ? 'Save changes' : 'Add ledger' }}</button>
        </div>
    </form>
@endsection

@push('scripts')
<script>
    (() => {
        const group = document.getElementById('ledger_group');
        const party = @json(Ledger::PARTY_GROUPS);
        const bank = @json(Ledger::BANK_GROUPS);
        const show = (name, on) => document.querySelector(`[data-section="${name}"]`).classList.toggle('hidden', !on);
        const sync = () => { show('party', party.includes(group.value)); show('bank', bank.includes(group.value)); };
        group.addEventListener('change', sync);
        sync();
    })();
</script>
@endpush

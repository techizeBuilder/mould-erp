{{-- A required ledger picker. Expects $name, $label, $options (ledgers), $value. --}}
<div>
    <label for="{{ $name }}" class="form-label">{{ $label }} <span class="text-red-600">*</span></label>
    <select id="{{ $name }}" name="{{ $name }}" required class="form-input">
        <option value="">— Select ledger —</option>
        @foreach ($options as $ledger)
            <option value="{{ $ledger->id }}" @selected((int) old($name, $value) === $ledger->id)>{{ $ledger->name }}</option>
        @endforeach
    </select>
    @error($name) <p class="form-error">{{ $message }}</p> @enderror
</div>

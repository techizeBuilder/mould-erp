{{-- One journal line. Expects $i ("__I__" in the template), $row, $ledgers. --}}
<div class="grid items-start gap-3 md:grid-cols-[1fr_9rem_9rem_auto]" data-line>
    <div>
        <select name="lines[{{ $i }}][ledger_id]" aria-label="Ledger" class="form-input">
            <option value="">— Select ledger —</option>
            @foreach ($ledgers as $ledger)
                <option value="{{ $ledger->id }}" @selected((int) ($row['ledger_id'] ?? 0) === $ledger->id)>{{ $ledger->name }}</option>
            @endforeach
        </select>
        @if ($errors->has("lines.$i.ledger_id"))<p class="form-error">{{ $errors->first("lines.$i.ledger_id") }}</p>@endif
    </div>
    <div>
        <input name="lines[{{ $i }}][debit]" type="number" min="0" step="0.01" value="{{ $row['debit'] ?? '' }}" aria-label="Debit" placeholder="Debit" class="form-input text-right" data-debit>
        @if ($errors->has("lines.$i.debit"))<p class="form-error">{{ $errors->first("lines.$i.debit") }}</p>@endif
    </div>
    <input name="lines[{{ $i }}][credit]" type="number" min="0" step="0.01" value="{{ $row['credit'] ?? '' }}" aria-label="Credit" placeholder="Credit" class="form-input text-right" data-credit>
    <button type="button" class="btn btn-secondary btn-sm mt-1 text-red-600" data-line-remove title="Remove line" aria-label="Remove line"><x-icon name="trash" class="h-4 w-4" /></button>
</div>

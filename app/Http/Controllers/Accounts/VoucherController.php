<?php

namespace App\Http\Controllers\Accounts;

use App\Http\Controllers\Controller;
use App\Models\Ledger;
use App\Models\Voucher;
use App\Support\VoucherLines;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class VoucherController extends Controller
{
    public function index(Request $request)
    {
        $filtered = Voucher::query()
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->type))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('voucher_date', '>=', $request->from))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('voucher_date', '<=', $request->to))
            ->when($request->filled('ledger_id'), fn ($q) => $q->whereHas('lines', fn ($l) => $l->where('ledger_id', $request->ledger_id)))
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%'.$request->search.'%';
                $q->where(fn ($q) => $q->where('code', 'like', $term)->orWhere('reference', 'like', $term)->orWhere('narration', 'like', $term));
            });

        return view('accounts.vouchers.index', [
            'vouchers' => (clone $filtered)->with(['mainLedger:id,name', 'counterLedger:id,name', 'lines.ledger:id,name'])->latest('voucher_date')->latest('id')->paginate(25)->withQueryString(),
            'total' => (float) (clone $filtered)->sum('amount'),
            'ledgers' => Ledger::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function show(Voucher $voucher)
    {
        return view('accounts.vouchers.show', ['voucher' => $voucher->load(['lines.ledger', 'creator:id,name'])]);
    }

    public function create(Request $request)
    {
        $type = $request->input('type');
        abort_unless(array_key_exists($type, Voucher::TYPES), 404);

        $voucher = new Voucher(['voucher_date' => today()]);
        $voucher->type = $type;

        return view('accounts.vouchers.form', $this->formData($request, $voucher) + ['nextCode' => Voucher::nextCode($type)]);
    }

    public function store(Request $request)
    {
        $request->validate(['type' => ['required', Rule::in(array_keys(Voucher::TYPES))]]);

        $voucher = new Voucher;
        $voucher->type = $request->input('type');
        $voucher->created_by = $request->user()->id;
        $this->save($request, $voucher);

        return redirect()->route('accounts.vouchers.show', $voucher)->with('success', "{$voucher->typeLabel()} voucher {$voucher->code} saved.");
    }

    public function edit(Request $request, Voucher $voucher)
    {
        return view('accounts.vouchers.form', $this->formData($request, $voucher) + ['nextCode' => null]);
    }

    public function update(Request $request, Voucher $voucher)
    {
        $this->save($request, $voucher);

        return redirect()->route('accounts.vouchers.show', $voucher)->with('success', "Voucher {$voucher->code} updated.");
    }

    public function destroy(Voucher $voucher)
    {
        $voucher->delete();

        return redirect()->route('accounts.vouchers.index')->with('success', "Voucher {$voucher->code} deleted.");
    }

    protected function formData(Request $request, Voucher $voucher): array
    {
        // Inactive ledgers aren't offered, except one this voucher already uses.
        $used = $voucher->exists ? $voucher->lines()->pluck('ledger_id')->all() : [];
        $ledgers = Ledger::where(fn ($q) => $q->where('status', 'active')->orWhereIn('id', $used))->orderBy('name')->get(['id', 'name', 'ledger_group']);
        $forField = fn (string $field) => $ledgers->whereIn('ledger_group', Voucher::allowedGroups($voucher->type, $field));

        return [
            'voucher' => $voucher,
            'ledgers' => $ledgers,
            'mainLedgers' => $forField('main'),
            'counterLedgers' => $forField('counter'),
            'rows' => $request->old('lines', $voucher->exists && $voucher->type === 'journal'
                ? $voucher->lines->map(fn ($l) => ['ledger_id' => $l->ledger_id, 'debit' => (float) $l->debit ?: null, 'credit' => (float) $l->credit ?: null])->all()
                : [[], []]),
        ];
    }

    protected function save(Request $request, Voucher $voucher): void
    {
        $request->validate([
            'voucher_date' => ['required', 'date'],
            'reference' => ['nullable', 'string', 'max:60'],
            'narration' => ['nullable', 'string', 'max:500'],
        ], [], ['voucher_date' => 'date']);

        $voucher->fill($request->only(['voucher_date', 'reference', 'narration']));

        $lines = match ($voucher->type) {
            'journal' => $this->journalLines($request, $voucher),
            'sales', 'purchase' => $this->gstLines($request, $voucher),
            default => $this->simpleLines($request, $voucher),
        };

        $voucher->post($lines);
    }

    /** Receipt: Dr money account, Cr party. Payment: Dr party, Cr money account. Contra: Dr to, Cr from. */
    protected function simpleLines(Request $request, Voucher $voucher): array
    {
        $this->validateLedgers($request, $voucher);
        $request->validate(['amount' => ['required', 'numeric', 'gt:0', 'max:999999999999']]);

        $main = $request->integer('main_ledger_id');
        $counter = $request->integer('counter_ledger_id');
        $voucher->fill(['main_ledger_id' => $main, 'counter_ledger_id' => $counter, 'taxable_amount' => null, 'gst_rate' => null, 'is_interstate' => false]);

        return $voucher->type === 'payment'
            ? VoucherLines::simple($counter, $main, $request->input('amount'))
            : VoucherLines::simple($main, $counter, $request->input('amount'));
    }

    protected function gstLines(Request $request, Voucher $voucher): array
    {
        $this->validateLedgers($request, $voucher);
        $request->validate([
            'taxable_amount' => ['required', 'numeric', 'gt:0', 'max:999999999999'],
            'gst_rate' => ['required', 'numeric', Rule::in(Voucher::GST_RATES)],
            'is_interstate' => ['required', 'boolean'],
        ], [], ['taxable_amount' => 'amount before GST', 'gst_rate' => 'GST rate']);

        $interstate = $request->boolean('is_interstate');
        $voucher->fill([
            'main_ledger_id' => $request->integer('main_ledger_id'),
            'counter_ledger_id' => $request->integer('counter_ledger_id'),
            'taxable_amount' => $request->input('taxable_amount'),
            'gst_rate' => $request->input('gst_rate'),
            'is_interstate' => $interstate,
        ]);

        $side = $voucher->type === 'sales' ? 'output' : 'input';
        $gst = collect(['cgst', 'sgst', 'igst'])->mapWithKeys(fn ($part) => [$part => Ledger::system("{$side}_{$part}")->id])->all();
        $build = $voucher->type === 'sales' ? 'sale' : 'purchase';

        return VoucherLines::$build(
            $voucher->main_ledger_id, $voucher->counter_ledger_id, $request->input('taxable_amount'), $request->input('gst_rate'), $interstate, $gst
        );
    }

    protected function journalLines(Request $request, Voucher $voucher): array
    {
        $request->validate([
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.ledger_id' => ['required', 'integer', 'exists:ledgers,id'],
            'lines.*.debit' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'lines.*.credit' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
        ], [
            'lines.*.ledger_id.required' => 'Pick a ledger.',
        ]);

        $lines = collect($request->input('lines'))->values()->map(fn ($row) => [
            'ledger_id' => (int) $row['ledger_id'],
            'debit' => round((float) ($row['debit'] ?? 0), 2),
            'credit' => round((float) ($row['credit'] ?? 0), 2),
        ]);

        foreach ($lines as $i => $line) {
            if (($line['debit'] > 0) === ($line['credit'] > 0)) {
                throw ValidationException::withMessages(["lines.$i.debit" => 'Enter either a debit or a credit on this line.']);
            }
        }

        $this->assertActive($lines->pluck('ledger_id')->unique()->all(), $voucher, 'lines');

        if ($lines->pluck('ledger_id')->unique()->count() < 2) {
            throw ValidationException::withMessages(['lines' => 'A journal needs at least two different ledgers.']);
        }
        if (! VoucherLines::isBalanced($lines->all())) {
            $debit = VoucherLines::total($lines->all(), 'debit');
            $credit = VoucherLines::total($lines->all(), 'credit');
            throw ValidationException::withMessages(['lines' => "Debits (₹{$debit}) and credits (₹{$credit}) must be equal."]);
        }

        $voucher->fill(['main_ledger_id' => null, 'counter_ledger_id' => null, 'taxable_amount' => null, 'gst_rate' => null, 'is_interstate' => false]);

        return $lines->all();
    }

    /** Both ledgers exist, differ, are active and belong to a group this voucher type can use. */
    protected function validateLedgers(Request $request, Voucher $voucher): void
    {
        $definition = Voucher::TYPES[$voucher->type];
        $inGroups = fn (string $field) => Rule::exists('ledgers', 'id')->whereIn('ledger_group', Voucher::allowedGroups($voucher->type, $field));

        $request->validate([
            'main_ledger_id' => ['required', 'integer', $inGroups('main')],
            'counter_ledger_id' => ['required', 'integer', 'different:main_ledger_id', $inGroups('counter')],
        ], [
            'main_ledger_id.exists' => "That ledger can't be used for \"{$definition['main']}\".",
            'counter_ledger_id.exists' => "That ledger can't be used for \"{$definition['counter']}\".",
            'counter_ledger_id.different' => 'The two ledgers must be different.',
        ], [
            'main_ledger_id' => strtolower($definition['main']),
            'counter_ledger_id' => strtolower($definition['counter']),
        ]);

        $this->assertActive([$request->integer('main_ledger_id')], $voucher, 'main_ledger_id');
        $this->assertActive([$request->integer('counter_ledger_id')], $voucher, 'counter_ledger_id');
    }

    /** An inactive ledger can stay on a voucher that already uses it, but can't be newly posted to. */
    protected function assertActive(array $ids, Voucher $voucher, string $field): void
    {
        $used = $voucher->exists ? $voucher->lines()->pluck('ledger_id')->all() : [];
        $names = Ledger::whereIn('id', array_diff($ids, $used))->where('status', 'inactive')->pluck('name');

        if ($names->isNotEmpty()) {
            throw ValidationException::withMessages([$field => $names->join(', ').' is inactive.']);
        }
    }
}

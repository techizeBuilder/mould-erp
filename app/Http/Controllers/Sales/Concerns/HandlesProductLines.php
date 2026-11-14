<?php

namespace App\Http\Controllers\Sales\Concerns;

use App\Models\Concerns\HasProductSpec;
use App\Models\LeadItem;
use App\Models\Mould;
use App\Models\Product;
use App\Models\Shape;
use App\Models\User;
use App\Support\Capacity;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The product lines on lead and quotation forms: pick a Product Master record, or spell
 * out a custom spec (product type, volume and material are then required).
 */
trait HandlesProductLines
{
    /** Line rules shared by leads and quotations; each adds its own commercial fields. */
    protected function productLineRules(): array
    {
        $custom = 'required_without:items.*.product_id';

        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['nullable', 'integer', 'exists:products,id'],
            'items.*.product_type' => ['nullable', $custom, Rule::in(array_keys(Product::TYPES))],
            'items.*.capacity_value' => ['nullable', $custom, 'numeric', 'gt:0', 'max:100000'],
            'items.*.capacity_unit' => ['nullable', 'in:ml,l'],
            'items.*.shape' => ['nullable', Rule::in(array_keys(Shape::options()))],
            'items.*.material' => ['nullable', $custom, Rule::in(Product::MATERIALS)],
            'items.*.neck_size_mm' => ['nullable', 'numeric', 'gt:0', 'max:1000'],
            'items.*.neck_type' => ['nullable', 'string', 'max:50'],
            'items.*.weight_grams' => ['nullable', 'numeric', 'gt:0', 'max:100000'],
            'items.*.colour' => ['nullable', 'string', 'max:50'],
            'items.*.closure' => ['required', Rule::in(array_keys(LeadItem::CLOSURES))],
            'items.*.decoration' => ['required', Rule::in(array_keys(LeadItem::DECORATIONS))],
            'items.*.fill_product' => ['nullable', 'string', 'max:100'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:1000000000'],
        ];
    }

    protected function productLineMessages(): array
    {
        return [
            'items.required' => 'Add at least one product.',
            'items.*.product_type.required_without' => 'Pick a product, or enter the type for a custom spec.',
            'items.*.capacity_value.required_without' => 'Enter the volume for a custom spec.',
            'items.*.material.required_without' => 'Enter the material for a custom spec.',
        ];
    }

    /** One validated row as line attributes. A catalogue line's specs come from the product when saved. */
    protected function productLineAttributes(array $row): array
    {
        return [
            'product_id' => $row['product_id'] ?? null,
            'product_type' => $row['product_type'] ?? 'other',
            'capacity_ml' => Capacity::toMl($row['capacity_value'] ?? null, $row['capacity_unit'] ?? 'ml'),
            'shape' => $row['shape'] ?? null,
            'material' => $row['material'] ?? null,
            'neck_size_mm' => $row['neck_size_mm'] ?? null,
            'neck_type' => $row['neck_type'] ?? null,
            'weight_grams' => $row['weight_grams'] ?? null,
            'colour' => $row['colour'] ?? null,
            'closure' => $row['closure'],
            'decoration' => $row['decoration'],
            'fill_product' => $row['fill_product'] ?? null,
            'quantity' => $row['quantity'],
        ];
    }

    /** Everything the shared product-line form needs. */
    protected function productLineOptions(): array
    {
        return [
            'products' => Product::where('status', 'active')->orderBy('code')->get(),
            'shapes' => Shape::options(),
            'neckTypes' => Mould::COMMON_NECK_TYPES,
        ];
    }

    protected function assignees()
    {
        return User::where('is_active', true)->orderBy('name')->get(['id', 'name']);
    }

    /** Blank-out-safe rows for the form: what was just submitted, else the given defaults. */
    protected function formRows(Request $request, iterable $lines): array
    {
        $rows = collect($lines)->map(fn ($line) => is_array($line) ? $line : $line->toFormRow())->values()->all();

        return $request->old('items', $rows ?: [[]]);
    }
}

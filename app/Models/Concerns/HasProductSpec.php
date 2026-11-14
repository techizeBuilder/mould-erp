<?php

namespace App\Models\Concerns;

use App\Models\Mould;
use App\Models\Product;
use App\Models\Shape;
use App\Support\Capacity;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A lead or quotation line describing a bottle / jar / preform: either a Product Master
 * record (specs copied from it) or a custom spec the buyer asked for. On save it stores
 * a readable description and whether we can make it: existing mould, new mould or trading.
 * These are snapshots, so later master changes don't alter a past enquiry or quotation.
 */
trait HasProductSpec
{
    public const CLOSURES = [
        'none' => 'Without cap',
        'cap' => 'With cap',
        'pump' => 'With pump / dispenser',
        'flip_top' => 'With flip-top cap',
    ];

    public const DECORATIONS = [
        'plain' => 'Plain',
        'screen_print' => 'Screen printing',
        'label' => 'Labelling',
        'shrink_sleeve' => 'Shrink sleeve',
        'embossed' => 'Embossed logo',
    ];

    public const MOULD_STATUSES = [
        'existing' => ['label' => 'Existing mould', 'tone' => 'bg-emerald-50 text-emerald-700'],
        'new' => ['label' => 'New mould needed', 'tone' => 'bg-amber-50 text-amber-700'],
        'trading' => ['label' => 'Trading item', 'tone' => 'bg-sky-50 text-sky-700'],
    ];

    protected const PRODUCT_SPEC_FIELDS = ['product_type', 'capacity_ml', 'shape', 'material', 'neck_size_mm', 'neck_type', 'weight_grams', 'colour'];

    protected static function bootHasProductSpec(): void
    {
        static::saving(function ($line) {
            $line->applyProductSpec();
        });
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function mould(): BelongsTo
    {
        return $this->belongsTo(Mould::class);
    }

    public function applyProductSpec(): static
    {
        $product = $this->product_id ? Product::find($this->product_id) : null;

        if ($product) {
            foreach (self::PRODUCT_SPEC_FIELDS as $field) {
                $this->{$field} = $product->{$field};
            }
            $this->description = $product->name;
            $this->mould_id = $product->mould_id;
            $this->mould_status = match (true) {
                (bool) $product->mould_id => 'existing',
                $product->business_type === 'manufacturing' => 'new',
                default => 'trading',
            };

            return $this;
        }

        $mould = $this->findMatchingMould();
        $this->description = $this->composeDescription();
        $this->mould_id = $mould?->id;
        $this->mould_status = $mould ? 'existing' : 'new';

        return $this;
    }

    /** A usable mould of the right kind with the same volume (and neck / shape, when the buyer gave them). */
    public function findMatchingMould(): ?Mould
    {
        if (! $this->capacity_ml) {
            return null;
        }

        return Mould::query()
            ->whereIn('mould_type', Product::MOULD_TYPES_FOR[$this->product_type] ?? [])
            ->where('status', '!=', 'inactive')
            ->where('capacity_ml', $this->capacity_ml)
            ->when($this->neck_size_mm, fn ($q) => $q->where('neck_size_mm', $this->neck_size_mm))
            ->when($this->shape, fn ($q) => $q->where('shape', $this->shape))
            ->orderBy('code')
            ->first();
    }

    protected function composeDescription(): string
    {
        return Product::composeName([
            'volume' => Capacity::format($this->capacity_ml),
            'shape' => Shape::label($this->shape),
            'material' => $this->material,
            'type' => Product::NAME_TYPES[$this->product_type] ?? 'Product',
            'neck_size' => $this->neck_size_mm,
            'neck_type' => $this->neck_type,
            'weight' => $this->weight_grams,
            'colour' => $this->colour,
        ]);
    }

    public function isCustomSpec(): bool
    {
        return ! $this->product_id;
    }

    public function mouldStatusLabel(): string
    {
        return self::MOULD_STATUSES[$this->mould_status]['label'] ?? '—';
    }

    public function mouldStatusTone(): string
    {
        return self::MOULD_STATUSES[$this->mould_status]['tone'] ?? 'bg-slate-100 text-slate-600';
    }

    /** "With cap · Screen printing · For hair oil" — what the buyer wants beyond the bottle itself. */
    public function requirementNote(): string
    {
        return collect([
            $this->closure !== 'none' ? (self::CLOSURES[$this->closure] ?? null) : null,
            $this->decoration !== 'plain' ? (self::DECORATIONS[$this->decoration] ?? null) : null,
            $this->fill_product ? "For {$this->fill_product}" : null,
        ])->filter()->join(' · ');
    }

    /** The line's fields as the shared product-line form expects them. */
    public function toFormRow(): array
    {
        [$value, $unit] = Capacity::forInput($this->capacity_ml);

        return [
            'product_id' => $this->product_id,
            'product_type' => $this->product_type,
            'capacity_value' => $value,
            'capacity_unit' => $unit,
            'shape' => $this->shape,
            'material' => $this->material,
            'neck_size_mm' => $this->neck_size_mm !== null ? Capacity::trim($this->neck_size_mm) : null,
            'neck_type' => $this->neck_type,
            'weight_grams' => $this->weight_grams !== null ? Capacity::trim($this->weight_grams) : null,
            'colour' => $this->colour,
            'closure' => $this->closure,
            'decoration' => $this->decoration,
            'fill_product' => $this->fill_product,
            'quantity' => $this->quantity,
        ];
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\HasProductSpec;
use App\Support\QuotationTotals;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuotationItem extends Model
{
    use HasProductSpec {
        toFormRow as specFormRow;
    }

    protected $fillable = [
        'product_id', 'product_type', 'capacity_ml', 'shape', 'material', 'neck_size_mm', 'neck_type', 'weight_grams',
        'colour', 'closure', 'decoration', 'fill_product', 'quantity', 'unit', 'rate', 'moq', 'mould_charge',
    ];

    protected function casts(): array
    {
        return [
            'capacity_ml' => 'decimal:2',
            'neck_size_mm' => 'decimal:2',
            'weight_grams' => 'decimal:2',
            'quantity' => 'integer',
            'moq' => 'integer',
            'rate' => 'decimal:3',
            'mould_charge' => 'decimal:2',
            'amount' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (QuotationItem $item) {
            $item->amount = QuotationTotals::lineAmount($item->quantity, $item->rate);
        });
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function toFormRow(): array
    {
        return $this->specFormRow() + [
            'unit' => $this->unit,
            'rate' => $this->rate !== null ? rtrim(rtrim((string) $this->rate, '0'), '.') : null,
            'moq' => $this->moq,
            'mould_charge' => (float) $this->mould_charge ?: null,
        ];
    }

    public function unitLabel(): string
    {
        return Product::UNITS[$this->unit] ?? (string) $this->unit;
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\HasProductSpec;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One product a lead is asking for. */
class LeadItem extends Model
{
    use HasProductSpec;

    public const QTY_BASES = [
        'one_time' => 'One-time order',
        'per_month' => 'Per month',
        'per_quarter' => 'Per quarter',
        'per_year' => 'Per year',
    ];

    protected $fillable = [
        'product_id', 'product_type', 'capacity_ml', 'shape', 'material', 'neck_size_mm', 'neck_type', 'weight_grams',
        'colour', 'closure', 'decoration', 'fill_product', 'quantity', 'qty_basis', 'target_price',
    ];

    protected function casts(): array
    {
        return [
            'capacity_ml' => 'decimal:2',
            'neck_size_mm' => 'decimal:2',
            'weight_grams' => 'decimal:2',
            'quantity' => 'integer',
            'target_price' => 'decimal:3',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /** Pieces per month this line works out to; a one-time order is spread over a year. */
    public function monthlyQuantity(): int
    {
        return (int) round($this->quantity / match ($this->qty_basis) {
            'per_quarter' => 3,
            'per_year', 'one_time' => 12,
            default => 1,
        });
    }

    public function qtyBasisLabel(): string
    {
        return self::QTY_BASES[$this->qty_basis] ?? '';
    }
}

<?php

namespace App\Models;

use App\Support\QuotationTotals;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One product on a sales order. Keeps the serial and name it was ordered under. */
class SalesOrderItem extends Model
{
    protected $fillable = ['product_id', 'quantity', 'unit', 'rate', 'mould_charge'];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'rate' => 'decimal:3',
            'mould_charge' => 'decimal:2',
            'amount' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (SalesOrderItem $item) {
            $product = Product::findOrFail($item->product_id);
            $item->product_code = $product->code;
            $item->description = $product->name;
        });

        static::saving(function (SalesOrderItem $item) {
            $item->amount = QuotationTotals::lineAmount($item->quantity, $item->rate);
        });
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function unitLabel(): string
    {
        return Product::UNITS[$this->unit] ?? (string) $this->unit;
    }
}

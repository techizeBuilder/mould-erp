<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One product a customer ordered, the basis of reorder tracking. Won quotations add these automatically. */
class CustomerOrder extends Model
{
    protected $fillable = ['customer_id', 'product_id', 'description', 'order_date', 'po_number', 'quantity', 'unit', 'rate', 'amount', 'remarks'];

    protected function casts(): array
    {
        return [
            'order_date' => 'date',
            'quantity' => 'integer',
            'rate' => 'decimal:3',
            'amount' => 'decimal:2',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function unitLabel(): string
    {
        return Product::UNITS[$this->unit] ?? (string) $this->unit;
    }
}

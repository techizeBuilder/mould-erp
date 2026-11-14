<?php

namespace App\Models;

use App\Models\Concerns\HasGeneratedName;
use App\Models\Concerns\HasMasterCode;
use App\Models\Concerns\HasNeck;
use App\Support\Capacity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends Model
{
    use HasGeneratedName, HasMasterCode, HasNeck;

    public const CODE_PREFIX = 'PRD';

    public const TYPES = [
        'bottle' => 'Bottle',
        'jar' => 'Jar',
        'preform' => 'Preform',
        'other' => 'Other',
    ];

    /** Type as written in a generated name ("Other" reads as "Product"). */
    public const NAME_TYPES = ['other' => 'Product'] + self::TYPES;

    public const BUSINESS_TYPES = [
        'manufacturing' => 'Manufacturing',
        'trading' => 'Trading',
        'both' => 'Both',
    ];

    public const OWNER_TYPES = [
        'company' => 'VR Product (Company)',
        'client' => 'Client Product',
    ];

    public const MATERIALS = ['PET', 'HDPE', 'PP', 'Other'];

    public const UNITS = [
        'pcs' => 'Pieces',
        'pack' => 'Pack',
        'carton' => 'Carton',
    ];

    public const STATUSES = [
        'active' => 'Active',
        'inactive' => 'Inactive',
    ];

    /** Which mould type each product type is made with (one mould per product). */
    public const MOULD_TYPES_FOR = [
        'bottle' => ['blow'],
        'jar' => ['blow'],
        'preform' => ['preform'],
        'other' => ['preform', 'blow'],
    ];

    /** Specs fixed by the mould; any the mould leaves blank are entered on the product. */
    public const MOULD_SPECS = ['capacity_ml', 'shape'];

    protected $fillable = [
        'name', 'bill_name', 'owner_type', 'client_name', 'product_type', 'business_type', 'mould_id', 'neck_id',
        'capacity_ml', 'shape', 'neck_size_mm', 'neck_height_mm', 'neck_type', 'weight_grams',
        'material', 'colour', 'unit', 'pieces_per_pack', 'hsn_code', 'status', 'remarks',
    ];

    protected function casts(): array
    {
        return [
            'capacity_ml' => 'decimal:2',
            'neck_size_mm' => 'decimal:2',
            'neck_height_mm' => 'decimal:2',
            'weight_grams' => 'decimal:2',
            'pieces_per_pack' => 'integer',
        ];
    }

    public function mould(): BelongsTo
    {
        return $this->belongsTo(Mould::class);
    }

    public function isManufactured(): bool
    {
        return $this->business_type !== 'trading';
    }

    public function isClientProduct(): bool
    {
        return $this->owner_type === 'client';
    }

    public function basedOn(): BelongsTo
    {
        return $this->belongsTo(self::class, 'based_on_id');
    }

    /** Takes the mould's fixed specs, and its neck and weight as defaults where the product has none. */
    public function applyMouldSpecs(?Mould $mould = null): static
    {
        $mould ??= $this->mould;
        if (! $mould) {
            return $this;
        }

        $this->applyLockedMouldSpecs($mould);

        if (! $this->neck_id && $this->neck_size_mm === null && $this->neck_type === null) {
            $this->neck_id = $mould->neck_id;
            $this->neck_size_mm = $mould->neck_size_mm;
            $this->neck_height_mm = $mould->neck_height_mm;
            $this->neck_type = $mould->neck_type;
        }
        $this->weight_grams ??= $mould->weight_grams;

        return $this;
    }

    public function applyLockedMouldSpecs(Mould $mould): static
    {
        foreach (self::MOULD_SPECS as $field) {
            if ($mould->{$field} !== null) {
                $this->{$field} = $mould->{$field};
            }
        }

        return $this;
    }

    /** Neck and weight decide what the product physically is, so a change makes it a different product. */
    public function identityChanged(): bool
    {
        return (int) $this->neck_id !== (int) $this->getOriginal('neck_id')
            || round((float) $this->weight_grams, 2) !== round((float) $this->getOriginal('weight_grams'), 2);
    }

    public function usesMouldNeck(): bool
    {
        return ! $this->mould || (int) $this->neck_id === (int) $this->mould->neck_id;
    }

    public function usesMouldWeight(): bool
    {
        return ! $this->mould || $this->mould->weight_grams === null || round((float) $this->weight_grams, 2) === round((float) $this->mould->weight_grams, 2);
    }

    /** Another product identical in every way that defines it, if one exists. */
    public function twin(): ?self
    {
        $query = self::query()->when($this->exists, fn ($q) => $q->whereKeyNot($this->getKey()));

        foreach (['product_type', 'mould_id', 'neck_id', 'capacity_ml', 'shape', 'weight_grams', 'material', 'colour', 'client_name'] as $field) {
            $value = $this->{$field};
            $value === null || $value === '' ? $query->whereNull($field) : $query->where($field, $value);
        }

        return $query->orderBy('id')->first();
    }

    /** e.g. "1 L Round PET Bottle – 28 mm PCO 1881 – 32 g – Clear", plus "– Client: X" for client products. */
    public function baseName(): string
    {
        return self::composeName([
            'volume' => Capacity::format($this->capacity_ml),
            'shape' => Shape::label($this->shape),
            'material' => $this->material,
            'type' => self::NAME_TYPES[$this->product_type] ?? 'Product',
            'neck_size' => $this->neck_size_mm,
            'neck_type' => $this->neck_type,
            'weight' => $this->weight_grams,
            'colour' => $this->colour,
            'client' => $this->isClientProduct() ? $this->client_name : null,
        ]);
    }

    /** Mirrored in the product form's live preview (masters/products/form). */
    public static function composeName(array $p): string
    {
        $num = fn ($v) => $v === null || $v === '' ? null : Capacity::trim($v);
        $text = fn ($k) => trim((string) ($p[$k] ?? '')) ?: null;

        return collect([
            collect([$p['volume'] ?? null, $p['shape'] ?? null, $text('material'), $p['type'] ?? null])->filter()->join(' '),
            collect([($s = $num($p['neck_size'] ?? null)) ? "{$s} mm" : null, $text('neck_type')])->filter()->join(' '),
            ($w = $num($p['weight'] ?? null)) ? "{$w} g" : null,
            $text('colour'),
            ($c = $text('client')) ? "Client: {$c}" : null,
        ])->filter()->join(' – ');
    }

    public function typeLabel(): ?string
    {
        return self::TYPES[$this->product_type] ?? null;
    }

    public function businessTypeLabel(): ?string
    {
        return self::BUSINESS_TYPES[$this->business_type] ?? null;
    }

    public function ownerLabel(): string
    {
        return $this->isClientProduct()
            ? 'Client'.($this->client_name ? ": {$this->client_name}" : '')
            : self::OWNER_TYPES['company'];
    }

    public function shapeLabel(): ?string
    {
        return Shape::label($this->shape);
    }

    public function unitLabel(): string
    {
        $label = self::UNITS[$this->unit] ?? $this->unit;

        return $this->pieces_per_pack && $this->unit !== 'pcs' ? "$label of {$this->pieces_per_pack}" : $label;
    }
}

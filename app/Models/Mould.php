<?php

namespace App\Models;

use App\Models\Concerns\HasGeneratedName;
use App\Models\Concerns\HasMasterCode;
use App\Models\Concerns\HasNeck;
use App\Support\Capacity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Mould extends Model
{
    use HasGeneratedName, HasMasterCode, HasNeck;

    public const CODE_PREFIX = 'MLD';

    public const TYPES = [
        'preform' => 'Preform Mould',
        'blow' => 'Blow Mould',
    ];

    public const STATUSES = [
        'active' => 'Active',
        'maintenance' => 'Under Maintenance',
        'inactive' => 'Inactive',
    ];

    /** Common PET/HDPE neck finishes (mm), offered as suggestions. */
    public const COMMON_NECK_SIZES = [18, 20, 22, 24, 25, 28, 30, 38, 45, 48, 53, 58, 63, 70, 83, 89, 100, 110, 120];

    /** Common neck finishes, offered as suggestions (any other value can be typed). */
    public const COMMON_NECK_TYPES = [
        'PCO 1881', 'PCO 1810', 'Alaska', '3-Start', 'BPF', 'ROPP', 'Screw (threaded)', 'Snap-on', 'Wide mouth', 'Crown',
    ];

    /**
     * Which machine types each mould type can be fitted on.
     * A preform mould runs on a preform machine; a blow mould on a blow moulding machine.
     */
    public const MACHINE_TYPES_FOR = [
        'preform' => ['preform', 'other'],
        'blow' => ['blow', 'other'],
    ];

    protected $fillable = [
        'name', 'mould_type', 'shape', 'neck_id', 'capacity_ml', 'neck_size_mm', 'neck_height_mm',
        'neck_type', 'weight_grams', 'cavities', 'location', 'status', 'remarks',
    ];

    protected function casts(): array
    {
        return [
            'cavities' => 'integer',
            'capacity_ml' => 'decimal:2',
            'neck_size_mm' => 'decimal:2',
            'neck_height_mm' => 'decimal:2',
            'weight_grams' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        // Volume and shape belong to the mould, so products follow them. Neck and weight are only
        // defaults for new products: an existing product keeps its own, and so its serial number.
        static::saved(function (Mould $mould) {
            if ($mould->wasChanged(Product::MOULD_SPECS)) {
                $mould->products()->get()->each(fn (Product $p) => $p->applyLockedMouldSpecs($mould)->save());
            }
        });
    }

    /** e.g. "500 ml Round Blow Mould – 28 mm PCO 1881 – 18 g – 2 Cav". */
    public function baseName(): string
    {
        return self::composeName([
            'volume' => Capacity::format($this->capacity_ml),
            'shape' => Shape::label($this->shape),
            'type' => $this->typeLabel() ?? 'Mould',
            'neck_size' => $this->neck_size_mm,
            'neck_type' => $this->neck_type,
            'weight' => $this->weight_grams,
            'cavities' => $this->cavities,
        ]);
    }

    /** Mirrored in the mould form's live preview (masters/moulds/form). */
    public static function composeName(array $p): string
    {
        $num = fn ($v) => $v === null || $v === '' ? null : Capacity::trim($v);

        return collect([
            collect([$p['volume'] ?? null, $p['shape'] ?? null, $p['type'] ?? null])->filter()->join(' '),
            collect([($s = $num($p['neck_size'] ?? null)) ? "{$s} mm" : null, $p['neck_type'] ?? null])->filter()->join(' '),
            ($w = $num($p['weight'] ?? null)) ? "{$w} g" : null,
            ($c = $p['cavities'] ?? null) ? "{$c} Cav" : null,
        ])->filter()->join(' – ');
    }

    public function machines(): BelongsToMany
    {
        return $this->belongsToMany(Machine::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function typeLabel(): ?string
    {
        return self::TYPES[$this->mould_type] ?? null;
    }

    public function shapeLabel(): ?string
    {
        return Shape::label($this->shape);
    }

    public function fitsMachine(Machine $machine): bool
    {
        return in_array($machine->machine_type, self::MACHINE_TYPES_FOR[$this->mould_type] ?? [], true);
    }
}

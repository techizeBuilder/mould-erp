<?php

namespace App\Models;

use App\Models\Concerns\HasGeneratedName;
use App\Models\Concerns\HasMasterCode;
use App\Support\Capacity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Machine extends Model
{
    use HasGeneratedName, HasMasterCode;

    public const CODE_PREFIX = 'MCH';

    public const TYPES = [
        'preform' => 'Preform',
        'blow' => 'Blow Moulding',
        'other' => 'Other',
    ];

    /** Type as written at the start of a generated name. */
    public const NAME_TYPES = [
        'preform' => 'Preform Machine',
        'blow' => 'Blow Moulding Machine',
        'other' => 'Machine',
    ];

    public const STATUSES = [
        'active' => 'Active',
        'maintenance' => 'Under Maintenance',
        'inactive' => 'Inactive',
    ];

    protected $fillable = [
        'name', 'machine_type', 'make', 'model_no', 'tonnage', 'cavities',
        'min_capacity_ml', 'max_capacity_ml', 'location', 'status', 'remarks',
    ];

    protected function casts(): array
    {
        return [
            'tonnage' => 'decimal:2',
            'cavities' => 'integer',
            'min_capacity_ml' => 'decimal:2',
            'max_capacity_ml' => 'decimal:2',
        ];
    }

    /** e.g. "Preform Machine – 250 T – Haitian MA2500/1000 – 16 Cav – 200 ml – 2 L". */
    public function baseName(): string
    {
        return self::composeName([
            'type' => $this->machine_type,
            'tonnage' => $this->tonnage,
            'make' => $this->make,
            'model_no' => $this->model_no,
            'cavities' => $this->cavities,
            'range' => Capacity::range($this->min_capacity_ml, $this->max_capacity_ml),
        ]);
    }

    /** Mirrored in the machine form's live preview (masters/machines/form). */
    public static function composeName(array $p): string
    {
        $tonnage = ($p['tonnage'] ?? null) !== null && $p['tonnage'] !== '' ? Capacity::trim($p['tonnage']) : null;

        return collect([
            self::NAME_TYPES[$p['type'] ?? ''] ?? 'Machine',
            $tonnage ? "{$tonnage} T" : null,
            collect([$p['make'] ?? null, $p['model_no'] ?? null])->map(fn ($v) => trim((string) $v))->filter()->join(' '),
            ($c = $p['cavities'] ?? null) ? "{$c} Cav" : null,
            $p['range'] ?? null,
        ])->filter()->join(' – ');
    }

    public function moulds(): BelongsToMany
    {
        return $this->belongsToMany(Mould::class);
    }

    public function typeLabel(): ?string
    {
        return self::TYPES[$this->machine_type] ?? null;
    }

    /** Whether a bottle of $ml fits the machine's supported size range (unknown range = fits). */
    public function supportsCapacity(float|string|null $ml): bool
    {
        if ($ml === null) {
            return true;
        }

        return ($this->min_capacity_ml === null || $ml >= $this->min_capacity_ml)
            && ($this->max_capacity_ml === null || $ml <= $this->max_capacity_ml);
    }
}

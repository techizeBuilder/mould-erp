<?php

namespace App\Models;

use App\Models\Concerns\HasGeneratedName;
use App\Models\Concerns\HasMasterCode;
use App\Support\Capacity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A neck finish. One serial number means one exact set of parameters: identical necks are
 * refused, and a neck already on a mould or product can't change, so a product's neck can
 * never shift underneath its serial number. A different neck is a new record.
 */
class Neck extends Model
{
    use HasGeneratedName, HasMasterCode;

    public const CODE_PREFIX = 'NCK';

    /** Form value meaning "add a new neck". */
    public const NEW = '__new';

    public const STATUSES = [
        'active' => 'Active',
        'inactive' => 'Inactive',
    ];

    /** The parameters that make a neck what it is. */
    public const SPEC_FIELDS = ['neck_size_mm', 'neck_type', 'neck_height_mm', 'inner_dia_mm', 'outer_dia_mm'];

    protected $fillable = ['neck_size_mm', 'neck_type', 'neck_height_mm', 'inner_dia_mm', 'outer_dia_mm', 'status', 'remarks'];

    protected function casts(): array
    {
        return [
            'neck_size_mm' => 'decimal:2',
            'neck_height_mm' => 'decimal:2',
            'inner_dia_mm' => 'decimal:2',
            'outer_dia_mm' => 'decimal:2',
        ];
    }

    public function moulds(): HasMany
    {
        return $this->hasMany(Mould::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function baseName(): string
    {
        return self::composeName($this->only(self::SPEC_FIELDS)) ?: 'Neck';
    }

    /** e.g. "28 mm PCO 1881 – H 21 mm – ID 21.7 mm – OD 27.4 mm". Mirrored by the preview in masters/necks/form. */
    public static function composeName(array $p): string
    {
        $num = fn ($key) => (float) ($p[$key] ?? 0) > 0 ? Capacity::trim($p[$key]).' mm' : null;

        return collect([
            collect([$num('neck_size_mm'), trim((string) ($p['neck_type'] ?? '')) ?: null])->filter()->join(' '),
            ($v = $num('neck_height_mm')) ? "H $v" : null,
            ($v = $num('inner_dia_mm')) ? "ID $v" : null,
            ($v = $num('outer_dia_mm')) ? "OD $v" : null,
        ])->filter()->join(' – ');
    }

    /** The neck with exactly these parameters, if there is one. */
    public static function findMatching(array $spec, ?int $exceptId = null): ?self
    {
        $query = self::query()->when($exceptId, fn ($q) => $q->whereKeyNot($exceptId));

        foreach (self::SPEC_FIELDS as $field) {
            $value = $spec[$field] ?? null;
            $value === null || $value === '' ? $query->whereNull($field) : $query->where($field, $value);
        }

        return $query->orderBy('id')->first();
    }

    public static function findOrCreateBySpec(array $spec, ?int $createdBy = null): self
    {
        $spec = array_intersect_key($spec, array_flip(self::SPEC_FIELDS));
        $neck = self::findMatching($spec);

        if (! $neck) {
            $neck = new self($spec + ['status' => 'active']);
            $neck->created_by = $createdBy;
            $neck->save();
        }

        return $neck;
    }

    public function isUsed(): bool
    {
        return $this->moulds()->exists() || $this->products()->exists();
    }
}

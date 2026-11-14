<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Shared behaviour for master records: an auto-generated unique code
 * (e.g. MLD-0007), a status, an optional image, and who created the record.
 * The using model defines CODE_PREFIX and STATUSES.
 */
trait HasMasterCode
{
    protected static function bootHasMasterCode(): void
    {
        static::creating(function ($model) {
            $model->code ??= static::nextCode();
        });

        static::deleted(function ($model) {
            if ($model->image_path ?? null) {
                Storage::disk('public')->delete($model->image_path);
            }
        });
    }

    public static function nextCode(): string
    {
        $prefix = static::CODE_PREFIX.'-';
        $number = 1;

        $last = static::where('code', 'like', $prefix.'%')->orderByDesc('id')->value('code');
        if ($last && preg_match('/(\d+)$/', $last, $m)) {
            $number = (int) $m[1] + 1;
        }

        do {
            $code = $prefix.str_pad((string) $number++, 4, '0', STR_PAD_LEFT);
        } while (static::where('code', $code)->exists());

        return $code;
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function statusLabel(): string
    {
        return static::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }

    /** Tailwind classes for the status badge. */
    public function statusTone(): string
    {
        return match ($this->status) {
            'active' => 'bg-emerald-50 text-emerald-700',
            'maintenance' => 'bg-amber-50 text-amber-700',
            default => 'bg-slate-100 text-slate-600',
        };
    }

    public function imageUrl(): ?string
    {
        return ($this->image_path ?? null) ? asset('storage/'.ltrim(str_replace('\\', '/', $this->image_path), '/')) : null;
    }
}

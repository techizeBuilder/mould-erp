<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Bottle / jar shapes shared by moulds and products. Records store the shape's key
 * (e.g. "round"); admins can add new shapes from the mould and product forms.
 */
class Shape extends Model
{
    /** Form value meaning "add a new shape". */
    public const NEW = '__new';

    protected $fillable = ['key', 'name'];

    protected static ?array $options = null;

    protected static function booted(): void
    {
        static::saved(fn () => static::$options = null);
        static::deleted(fn () => static::$options = null);
    }

    /** key => name, alphabetical, with "Other" last. */
    public static function options(): array
    {
        return static::$options ??= static::orderByRaw("`key` = 'other'")->orderBy('name')->pluck('name', 'key')->all();
    }

    public static function label(?string $key): ?string
    {
        return $key === null ? null : (static::options()[$key] ?? Str::headline($key));
    }

    /** Finds a shape by name (case-insensitive) or creates it, returning its key. */
    public static function findOrCreateByName(string $name): string
    {
        $name = Str::of($name)->squish()->title()->toString();
        $key = Str::slug($name, '_');

        return static::firstOrCreate(['key' => $key], ['name' => $name])->key;
    }
}

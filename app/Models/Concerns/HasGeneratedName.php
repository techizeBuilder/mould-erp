<?php

namespace App\Models\Concerns;

/**
 * The record's name is never typed: it is derived from its specs on every save, so the
 * record can be identified by its name alone. Identical specs get a " #2", " #3"… suffix
 * so every name is unique. The using model implements baseName().
 */
trait HasGeneratedName
{
    abstract public function baseName(): string;

    protected static function bootHasGeneratedName(): void
    {
        static::saving(function ($model) {
            if (method_exists($model, 'syncSpecs')) {
                $model->syncSpecs();
            }
            $model->name = $model->generateName();
        });
    }

    public function generateName(): string
    {
        $base = $this->baseName();

        $taken = fn (string $name) => static::where('name', $name)->when($this->exists, fn ($q) => $q->whereKeyNot($this->getKey()))->exists();

        // Keep an existing suffix if the specs haven't changed.
        if ($this->name && preg_match('/^'.preg_quote($base, '/').' #\d+$/u', $this->name) && ! $taken($this->name)) {
            return $this->name;
        }

        $name = $base;
        for ($n = 2; $taken($name); $n++) {
            $name = "{$base} #{$n}";
        }

        return $name;
    }
}

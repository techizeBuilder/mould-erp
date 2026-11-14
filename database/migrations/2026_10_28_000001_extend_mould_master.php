<?php

use App\Models\Mould;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Mould master v3:
 * - shapes become an editable list (shapes table) instead of a fixed set;
 * - moulds get neck height, neck type and weight;
 * - mould names are generated from the specs and unique; old names are kept in remarks.
 */
return new class extends Migration
{
    protected const DEFAULT_SHAPES = [
        'round' => 'Round', 'square' => 'Square', 'oval' => 'Oval', 'rectangular' => 'Rectangular',
        'cylindrical' => 'Cylindrical', 'flat' => 'Flat', 'other' => 'Other',
    ];

    public function up(): void
    {
        Schema::create('shapes', function (Blueprint $table) {
            $table->id();
            $table->string('key', 60)->unique();
            $table->string('name', 60);
            $table->timestamps();
        });

        $used = DB::table('moulds')->whereNotNull('shape')->pluck('shape')
            ->merge(DB::table('products')->whereNotNull('shape')->pluck('shape'))
            ->unique()
            ->mapWithKeys(fn ($key) => [$key => Str::headline($key)]);
        $now = now();
        DB::table('shapes')->insert(collect(self::DEFAULT_SHAPES + $used->all())
            ->map(fn ($name, $key) => ['key' => $key, 'name' => $name, 'created_at' => $now, 'updated_at' => $now])
            ->values()->all());

        Schema::table('moulds', function (Blueprint $table) {
            $table->decimal('neck_height_mm', 6, 2)->nullable()->after('neck_size_mm');
            $table->string('neck_type', 50)->nullable()->after('neck_height_mm');
            $table->decimal('weight_grams', 10, 2)->nullable()->after('neck_type');
        });

        // Regenerate names from specs, picking up weight / neck type written into the old names.
        foreach (Mould::orderBy('id')->get() as $mould) {
            $old = $mould->name;
            if (preg_match('/(\d+(?:\.\d+)?)\s*g\b/i', $old, $m)) {
                $mould->weight_grams = $m[1];
            }
            if (preg_match('/\bPCO(?:\s*\d{4})?\b/i', $old, $m)) {
                $mould->neck_type = strtoupper($m[0]);
            }
            $mould->name = $mould->generateName();
            if ($mould->name !== $old) {
                $mould->remarks = trim(($mould->remarks ? $mould->remarks."\n" : '')."Previous name: {$old}");
            }
            $mould->saveQuietly();
        }

        Schema::table('moulds', function (Blueprint $table) {
            $table->unique('name');
        });
    }

    public function down(): void
    {
        Schema::table('moulds', function (Blueprint $table) {
            $table->dropUnique(['name']);
            $table->dropColumn(['neck_height_mm', 'neck_type', 'weight_grams']);
        });

        Schema::dropIfExists('shapes');
    }
};

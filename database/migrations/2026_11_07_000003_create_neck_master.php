<?php

use App\Models\Neck;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Neck Master: each distinct neck gets its own serial number (NCK-0001). Moulds carry a default
 * neck; products point at the neck they are actually made with. Necks are created from the values
 * already on moulds and products, which keep those values (and their names) unchanged.
 * Whoever manages moulds also gets the same rights on necks.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('necks', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name')->unique();
            $table->decimal('neck_size_mm', 6, 2)->nullable();
            $table->string('neck_type', 50)->nullable();
            $table->decimal('neck_height_mm', 6, 2)->nullable();
            $table->decimal('inner_dia_mm', 6, 2)->nullable();
            $table->decimal('outer_dia_mm', 6, 2)->nullable();
            $table->string('status', 20)->default('active');
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('moulds', function (Blueprint $table) {
            $table->foreignId('neck_id')->nullable()->after('shape')->constrained()->restrictOnDelete();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('neck_id')->nullable()->after('mould_id')->constrained()->restrictOnDelete();
            $table->foreignId('based_on_id')->nullable()->after('neck_id')->constrained('products')->nullOnDelete();
        });

        $number = 0;
        $necks = [];
        $now = now();

        foreach (['moulds', 'products'] as $table) {
            foreach (DB::table($table)->orderBy('code')->get(['id', 'neck_size_mm', 'neck_type', 'neck_height_mm']) as $row) {
                if ($row->neck_size_mm === null && $row->neck_type === null && $row->neck_height_mm === null) {
                    continue;
                }

                $key = implode('|', [(float) $row->neck_size_mm, mb_strtolower(trim((string) $row->neck_type)), (float) $row->neck_height_mm]);
                $necks[$key] ??= DB::table('necks')->insertGetId([
                    'code' => 'NCK-'.str_pad((string) ++$number, 4, '0', STR_PAD_LEFT),
                    'name' => Neck::composeName(['neck_size_mm' => $row->neck_size_mm, 'neck_type' => $row->neck_type, 'neck_height_mm' => $row->neck_height_mm]) ?: 'Neck',
                    'neck_size_mm' => $row->neck_size_mm,
                    'neck_type' => $row->neck_type,
                    'neck_height_mm' => $row->neck_height_mm,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                DB::table($table)->where('id', $row->id)->update(['neck_id' => $necks[$key]]);
            }
        }

        foreach (DB::table('user_permissions')->where('module', 'mould_master')->get() as $row) {
            DB::table('user_permissions')->updateOrInsert(
                ['user_id' => $row->user_id, 'module' => 'neck_master'],
                collect((array) $row)->only(['can_show', 'can_view', 'can_create', 'can_edit', 'can_delete', 'created_at', 'updated_at'])->all()
            );
        }
    }

    public function down(): void
    {
        DB::table('user_permissions')->where('module', 'neck_master')->delete();

        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('based_on_id');
            $table->dropConstrainedForeignId('neck_id');
        });
        Schema::table('moulds', function (Blueprint $table) {
            $table->dropConstrainedForeignId('neck_id');
        });
        Schema::dropIfExists('necks');
    }
};

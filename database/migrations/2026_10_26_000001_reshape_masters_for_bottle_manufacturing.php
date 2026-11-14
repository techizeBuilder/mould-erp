<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reshapes the generic masters for a PET/HDPE bottle & jar business:
 * preform machine + preform mould → blow moulding machine + blow mould.
 *
 * - machines ↔ moulds and moulds ↔ products become many-to-many.
 * - Mould fields that no longer apply are folded into remarks, so no data is lost.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ---- Moulds -------------------------------------------------------
        Schema::table('moulds', function (Blueprint $table) {
            $table->string('shape', 30)->nullable()->after('mould_type');
            $table->decimal('capacity_ml', 10, 2)->nullable()->after('shape');
            $table->decimal('neck_size_mm', 6, 2)->nullable()->after('capacity_ml');
            $table->string('image_path')->nullable()->after('location');
        });

        foreach (DB::table('moulds')->get() as $mould) {
            $old = array_filter([
                'Old type' => in_array($mould->mould_type, ['Injection', 'Blow'], true) ? null : $mould->mould_type,
                'Material' => $mould->material,
                'Size' => $mould->dimensions,
                'Weight (kg)' => $mould->weight_kg,
                'Shot life' => $mould->shot_life,
                'Customer' => $mould->customer,
            ], fn ($v) => $v !== null && $v !== '');

            DB::table('moulds')->where('id', $mould->id)->update([
                'mould_type' => match ($mould->mould_type) {
                    'Injection' => 'preform',
                    'Blow' => 'blow',
                    default => null,
                },
                'status' => match ($mould->status) {
                    'scrapped' => 'inactive',
                    default => $mould->status,
                },
                'remarks' => trim(($mould->remarks ? $mould->remarks."\n" : '').($old
                    ? 'Previous details: '.collect($old)->map(fn ($v, $k) => "$k: $v")->join(', ')
                    : '')) ?: null,
            ]);
        }

        Schema::table('moulds', function (Blueprint $table) {
            $table->dropColumn(['material', 'dimensions', 'weight_kg', 'shot_life', 'customer']);
        });

        // ---- Machines -----------------------------------------------------
        Schema::table('machines', function (Blueprint $table) {
            $table->unsignedInteger('capacity_per_hour')->nullable()->after('model_no');
            $table->unsignedSmallInteger('cavities')->nullable()->after('capacity_per_hour');
            $table->decimal('min_capacity_ml', 10, 2)->nullable()->after('cavities');
            $table->decimal('max_capacity_ml', 10, 2)->nullable()->after('min_capacity_ml');
        });

        foreach (DB::table('machines')->get() as $machine) {
            DB::table('machines')->where('id', $machine->id)->update([
                'machine_type' => match ($machine->machine_type) {
                    'Injection Moulding' => 'preform',
                    'Blow Moulding' => 'blow',
                    null => null,
                    default => 'other',
                },
                'status' => match ($machine->status) {
                    'idle' => 'active',
                    'breakdown' => 'maintenance',
                    default => $machine->status,
                },
                'remarks' => trim(($machine->remarks ?? '')."\n".collect([
                    'Capacity' => $machine->capacity, 'Serial no.' => $machine->serial_no, 'Purchased' => $machine->purchase_date,
                ])->filter()->map(fn ($v, $k) => "$k: $v")->join(', ')) ?: null,
            ]);
        }

        Schema::table('machines', function (Blueprint $table) {
            $table->dropColumn(['capacity', 'serial_no', 'purchase_date']);
        });

        // ---- Products -----------------------------------------------------
        Schema::table('products', function (Blueprint $table) {
            $table->string('product_type', 20)->default('bottle')->after('name');
            $table->string('business_type', 20)->default('manufacturing')->after('product_type');
            $table->decimal('capacity_ml', 10, 2)->nullable()->after('business_type');
            $table->string('shape', 30)->nullable()->after('capacity_ml');
            $table->decimal('neck_size_mm', 6, 2)->nullable()->after('shape');
            $table->unsignedInteger('pieces_per_pack')->nullable()->after('unit');
            $table->string('image_path')->nullable()->after('hsn_code');
            $table->text('remarks')->nullable()->after('status');
        });

        // ---- Relationships ------------------------------------------------
        Schema::create('machine_mould', function (Blueprint $table) {
            $table->foreignId('machine_id')->constrained()->cascadeOnDelete();
            $table->foreignId('mould_id')->constrained()->cascadeOnDelete();
            $table->primary(['machine_id', 'mould_id']);
        });

        Schema::create('mould_product', function (Blueprint $table) {
            $table->foreignId('mould_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->primary(['mould_id', 'product_id']);
        });

        foreach (DB::table('products')->get() as $product) {
            if ($product->mould_id) {
                DB::table('mould_product')->insert(['mould_id' => $product->mould_id, 'product_id' => $product->id]);
            }
            DB::table('products')->where('id', $product->id)->update([
                'unit' => match ($product->unit) {
                    'Set', 'Box' => 'pack',
                    default => 'pcs',
                },
                'remarks' => trim(($product->description ?? '')."\n".($product->category ? "Category: {$product->category}" : '')) ?: null,
            ]);
        }

        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('mould_id');
            $table->dropColumn(['category', 'description']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mould_product');
        Schema::dropIfExists('machine_mould');

        Schema::table('products', function (Blueprint $table) {
            $table->string('category', 100)->nullable();
            $table->text('description')->nullable();
            $table->foreignId('mould_id')->nullable()->constrained()->nullOnDelete();
            $table->dropColumn(['product_type', 'business_type', 'capacity_ml', 'shape', 'neck_size_mm', 'pieces_per_pack', 'image_path', 'remarks']);
        });

        Schema::table('machines', function (Blueprint $table) {
            $table->string('capacity', 100)->nullable();
            $table->string('serial_no', 100)->nullable();
            $table->date('purchase_date')->nullable();
            $table->dropColumn(['capacity_per_hour', 'cavities', 'min_capacity_ml', 'max_capacity_ml']);
        });

        Schema::table('moulds', function (Blueprint $table) {
            $table->string('material', 100)->nullable();
            $table->string('dimensions', 100)->nullable();
            $table->decimal('weight_kg', 10, 2)->nullable();
            $table->unsignedInteger('shot_life')->nullable();
            $table->string('customer', 150)->nullable();
            $table->dropColumn(['shape', 'capacity_ml', 'neck_size_mm', 'image_path']);
        });
    }
};

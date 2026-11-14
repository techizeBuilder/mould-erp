<?php

use App\Models\Product;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Product master v3:
 * - two names: `name` generated from the specs (unique), `bill_name` typed for invoices
 *   (existing names become bill names);
 * - owner: VR (company) product or client product;
 * - exactly one mould per product (products.mould_id), whose specs the product inherits.
 *   Where a product had several moulds, the one matching its type is kept and the others noted in remarks.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('bill_name')->nullable()->after('name');
            $table->string('owner_type', 20)->default('company')->after('bill_name');
            $table->string('client_name', 150)->nullable()->after('owner_type');
            $table->foreignId('mould_id')->nullable()->after('business_type')->constrained()->nullOnDelete();
            $table->decimal('neck_height_mm', 6, 2)->nullable()->after('neck_size_mm');
            $table->string('neck_type', 50)->nullable()->after('neck_height_mm');
        });

        DB::table('products')->update(['bill_name' => DB::raw('name')]);

        foreach (Product::orderBy('id')->get() as $product) {
            $moulds = DB::table('mould_product')->join('moulds', 'moulds.id', '=', 'mould_product.mould_id')
                ->where('product_id', $product->id)->orderBy('moulds.id')->get(['moulds.id', 'moulds.code', 'moulds.mould_type']);

            if ($product->business_type !== 'trading' && $moulds->isNotEmpty()) {
                $want = $product->product_type === 'preform' ? 'preform' : 'blow';
                $keep = $moulds->firstWhere('mould_type', $want) ?? $moulds->first();
                $product->mould_id = $keep->id;
                $dropped = $moulds->where('id', '!=', $keep->id)->pluck('code');
                if ($dropped->isNotEmpty()) {
                    $product->remarks = trim(($product->remarks ? $product->remarks."\n" : '')
                        .'Also used mould(s): '.$dropped->join(', ').' (one mould per product now).');
                }
                $product->applyMouldSpecs();
            }

            $product->name = $product->generateName();
            $product->saveQuietly();
        }

        Schema::dropIfExists('mould_product');

        Schema::table('products', function (Blueprint $table) {
            $table->unique('name');
        });
    }

    public function down(): void
    {
        Schema::create('mould_product', function (Blueprint $table) {
            $table->foreignId('mould_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->primary(['mould_id', 'product_id']);
        });

        foreach (DB::table('products')->whereNotNull('mould_id')->get(['id', 'mould_id']) as $row) {
            DB::table('mould_product')->insert(['mould_id' => $row->mould_id, 'product_id' => $row->id]);
        }
        DB::table('products')->whereNotNull('bill_name')->update(['name' => DB::raw('bill_name')]);

        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique(['name']);
            $table->dropConstrainedForeignId('mould_id');
            $table->dropColumn(['bill_name', 'owner_type', 'client_name', 'neck_height_mm', 'neck_type']);
        });
    }
};

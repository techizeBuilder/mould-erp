<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Order Management: customer master details, sales orders and their approval.
 * Whoever already manages customers under Customer Retention gets the same rights on Customer Master;
 * Sales Orders and Order Approval rights are granted by the super admin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('pan', 10)->nullable()->after('gstin');
            $table->string('shipping_address', 1000)->nullable()->after('address');
            $table->decimal('credit_limit', 14, 2)->nullable()->after('payment_terms');
            $table->unsignedSmallInteger('credit_days')->nullable()->after('credit_limit');
        });

        Schema::create('sales_orders', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('quotation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->default('draft');
            $table->date('order_date');
            $table->date('required_by')->nullable();
            $table->string('po_number', 60)->nullable();
            $table->date('po_date')->nullable();
            $table->string('shipping_address', 1000)->nullable();
            $table->string('price_basis', 20)->default('ex_works');
            $table->string('payment_terms', 20)->nullable();
            $table->string('packing', 150)->nullable();
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->decimal('gst_percent', 5, 2)->default(18);
            $table->decimal('freight_amount', 14, 2)->default(0);
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('discount_amount', 14, 2)->default(0);
            $table->decimal('mould_total', 14, 2)->default(0);
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->text('remarks')->nullable();
            $table->string('decision_note', 500)->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('decided_at')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index('status');
            $table->index(['customer_id', 'po_number']);
        });

        Schema::create('sales_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_order_id')->constrained()->cascadeOnDelete();
            // Snapshot of the product's serial and name, so a past order always reads as it was placed.
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_code', 50);
            $table->string('description');
            $table->unsignedBigInteger('quantity');
            $table->string('unit', 20)->default('pcs');
            $table->decimal('rate', 12, 3);
            $table->decimal('mould_charge', 14, 2)->default(0);
            $table->decimal('amount', 14, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('sales_order_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_order_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->string('note', 500)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('customer_orders', function (Blueprint $table) {
            $table->foreignId('sales_order_id')->nullable()->after('quotation_id')->constrained()->nullOnDelete();
        });

        foreach (DB::table('user_permissions')->where('module', 'sales_retention')->get() as $row) {
            DB::table('user_permissions')->updateOrInsert(
                ['user_id' => $row->user_id, 'module' => 'orders_customers'],
                collect((array) $row)->only(['can_show', 'can_view', 'can_create', 'can_edit', 'can_delete', 'created_at', 'updated_at'])->all()
            );
        }
    }

    public function down(): void
    {
        DB::table('user_permissions')->whereIn('module', ['orders_customers', 'orders_sales', 'orders_approval'])->delete();

        Schema::table('customer_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sales_order_id');
        });
        Schema::dropIfExists('sales_order_logs');
        Schema::dropIfExists('sales_order_items');
        Schema::dropIfExists('sales_orders');
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['pan', 'shipping_address', 'credit_limit', 'credit_days']);
        });
    }
};

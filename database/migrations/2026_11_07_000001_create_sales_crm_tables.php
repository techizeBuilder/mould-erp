<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sales & CRM: leads → buyer qualification → quotations → customers and repeat orders,
 * with follow-ups across all of them. The single "sales" permission module is split into
 * one module per screen; each user's existing rights are copied to all of them.
 */
return new class extends Migration
{
    protected const MODULES = ['sales_leads', 'sales_qualification', 'sales_quotations', 'sales_followups', 'sales_retention'];

    public function up(): void
    {
        // The product a line is about: a Product Master record, or a custom spec the buyer asked for.
        $productSpec = function (Blueprint $table) {
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description');
            $table->string('product_type', 20);
            $table->decimal('capacity_ml', 10, 2)->nullable();
            $table->string('shape', 50)->nullable();
            $table->string('material', 20)->nullable();
            $table->decimal('neck_size_mm', 6, 2)->nullable();
            $table->string('neck_type', 50)->nullable();
            $table->decimal('weight_grams', 8, 2)->nullable();
            $table->string('colour', 50)->nullable();
            $table->string('closure', 20)->default('none');
            $table->string('decoration', 20)->default('plain');
            $table->string('fill_product', 100)->nullable();
            $table->foreignId('mould_id')->nullable()->constrained()->nullOnDelete();
            $table->string('mould_status', 20)->default('new');
        };

        $party = function (Blueprint $table) {
            $table->string('company_name');
            $table->string('contact_person', 100)->nullable();
            $table->string('phone', 20);
            $table->string('email')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('address', 1000)->nullable();
            $table->string('gstin', 15)->nullable();
            $table->string('industry', 30)->nullable();
        };

        Schema::create('customers', function (Blueprint $table) use ($party) {
            $table->id();
            $table->string('code', 20)->unique();
            $party($table);
            $table->string('buyer_type', 20)->nullable();
            $table->string('payment_terms', 20)->nullable();
            $table->string('source', 30)->nullable();
            $table->unsignedSmallInteger('reorder_cycle_days')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('active');
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index('phone');
        });

        Schema::create('leads', function (Blueprint $table) use ($party) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->date('enquiry_date');
            $party($table);
            $table->string('source', 30);
            $table->string('source_detail', 150)->nullable();
            $table->string('status', 20)->default('new');
            $table->string('lost_reason', 30)->nullable();
            $table->date('expected_close_date')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index('source');
            $table->index('status');
            $table->index('phone');
        });

        Schema::create('lead_items', function (Blueprint $table) use ($productSpec) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $productSpec($table);
            $table->unsignedBigInteger('quantity');
            $table->string('qty_basis', 20)->default('per_month');
            $table->decimal('target_price', 12, 3)->nullable();
            $table->timestamps();
        });

        Schema::create('buyer_qualifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('buyer_type', 20);
            $table->unsignedBigInteger('monthly_volume');
            $table->string('price_fit', 20);
            $table->string('mould_fit', 20);
            $table->string('decision_authority', 20);
            $table->string('purchase_timeline', 20);
            $table->string('payment_terms', 20);
            $table->boolean('gst_verified')->default(false);
            $table->boolean('sample_required')->default(false);
            $table->string('compliance_required', 150)->nullable();
            $table->string('current_supplier', 150)->nullable();
            $table->unsignedTinyInteger('score');
            $table->string('grade', 1);
            $table->string('decision', 20)->default('pending');
            $table->string('decision_reason', 500)->nullable();
            $table->foreignId('qualified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index('decision');
        });

        Schema::create('quotations', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20);
            $table->unsignedSmallInteger('revision')->default(0);
            $table->foreignId('parent_id')->nullable()->constrained('quotations')->nullOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('draft');
            $table->date('quote_date');
            $table->date('valid_until');
            $table->date('sent_on')->nullable();
            $table->string('price_basis', 20)->default('ex_works');
            $table->string('payment_terms', 20)->nullable();
            $table->unsignedSmallInteger('delivery_days')->nullable();
            $table->unsignedSmallInteger('mould_lead_days')->nullable();
            $table->string('packing', 150)->nullable();
            $table->string('sample_status', 20)->default('not_required');
            $table->date('sample_sent_on')->nullable();
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->decimal('gst_percent', 5, 2)->default(18);
            $table->decimal('freight_amount', 14, 2)->default(0);
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('discount_amount', 14, 2)->default(0);
            $table->decimal('mould_total', 14, 2)->default(0);
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->string('lost_reason', 30)->nullable();
            $table->string('competitor', 150)->nullable();
            $table->string('po_number', 60)->nullable();
            $table->date('won_on')->nullable();
            $table->text('terms')->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['code', 'revision']);
            $table->index('status');
        });

        Schema::create('quotation_items', function (Blueprint $table) use ($productSpec) {
            $table->id();
            $table->foreignId('quotation_id')->constrained()->cascadeOnDelete();
            $productSpec($table);
            $table->unsignedBigInteger('quantity');
            $table->string('unit', 20)->default('pcs');
            $table->decimal('rate', 12, 3);
            $table->unsignedBigInteger('moq')->nullable();
            $table->decimal('mould_charge', 14, 2)->default(0);
            $table->decimal('amount', 14, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('quotation_status_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->string('note', 500)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('follow_ups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('quotation_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20);
            $table->string('purpose', 30);
            $table->dateTime('due_at');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('agenda', 1000)->nullable();
            $table->string('status', 20)->default('pending');
            $table->string('outcome', 1000)->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['status', 'due_at']);
        });

        Schema::create('customer_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quotation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description');
            $table->date('order_date');
            $table->string('po_number', 60)->nullable();
            $table->unsignedBigInteger('quantity');
            $table->string('unit', 20)->default('pcs');
            $table->decimal('rate', 12, 3)->default(0);
            $table->decimal('amount', 14, 2)->default(0);
            $table->string('remarks', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['customer_id', 'order_date']);
        });

        foreach (DB::table('user_permissions')->where('module', 'sales')->get() as $row) {
            foreach (self::MODULES as $module) {
                DB::table('user_permissions')->updateOrInsert(
                    ['user_id' => $row->user_id, 'module' => $module],
                    collect((array) $row)->only(['can_show', 'can_view', 'can_create', 'can_edit', 'can_delete', 'created_at', 'updated_at'])->all()
                );
            }
        }
        DB::table('user_permissions')->where('module', 'sales')->delete();
    }

    public function down(): void
    {
        foreach (DB::table('user_permissions')->where('module', 'sales_leads')->get() as $row) {
            DB::table('user_permissions')->insert(['module' => 'sales'] + collect((array) $row)->except(['id', 'module'])->all());
        }
        DB::table('user_permissions')->whereIn('module', self::MODULES)->delete();

        Schema::dropIfExists('customer_orders');
        Schema::dropIfExists('follow_ups');
        Schema::dropIfExists('quotation_status_logs');
        Schema::dropIfExists('quotation_items');
        Schema::dropIfExists('quotations');
        Schema::dropIfExists('buyer_qualifications');
        Schema::dropIfExists('lead_items');
        Schema::dropIfExists('leads');
        Schema::dropIfExists('customers');
    }
};

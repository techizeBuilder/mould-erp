<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('moulds', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->string('mould_type', 50)->nullable();
            $table->unsignedSmallInteger('cavities')->default(1);
            $table->string('material', 100)->nullable();
            $table->string('dimensions', 100)->nullable();
            $table->decimal('weight_kg', 10, 2)->nullable();
            $table->unsignedInteger('shot_life')->nullable();
            $table->string('customer', 150)->nullable();
            $table->string('location', 100)->nullable();
            $table->string('status', 30)->default('active');
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('machines', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->string('machine_type', 50)->nullable();
            $table->string('make', 100)->nullable();
            $table->string('model_no', 100)->nullable();
            $table->string('serial_no', 100)->nullable();
            $table->string('capacity', 100)->nullable();
            $table->date('purchase_date')->nullable();
            $table->string('location', 100)->nullable();
            $table->string('status', 30)->default('active');
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->string('category', 100)->nullable();
            $table->string('unit', 20)->default('Nos');
            $table->string('material', 100)->nullable();
            $table->decimal('weight_grams', 10, 2)->nullable();
            $table->string('colour', 50)->nullable();
            $table->string('hsn_code', 20)->nullable();
            $table->foreignId('mould_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 30)->default('active');
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
        Schema::dropIfExists('machines');
        Schema::dropIfExists('moulds');
    }
};

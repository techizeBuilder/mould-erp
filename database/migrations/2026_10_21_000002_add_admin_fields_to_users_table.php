<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 20)->nullable()->after('email');
            $table->boolean('is_super_admin')->default(false)->after('password');
            $table->foreignId('designation_id')->nullable()->after('is_super_admin')
                ->constrained()->nullOnDelete();
            $table->boolean('is_active')->default(true)->after('designation_id');
            $table->timestamp('last_login_at')->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('designation_id');
            $table->dropColumn(['phone', 'is_super_admin', 'is_active', 'last_login_at']);
        });
    }
};

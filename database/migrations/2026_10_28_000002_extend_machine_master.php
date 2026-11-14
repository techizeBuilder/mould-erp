<?php

use App\Models\Machine;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Machine master v3: output (pcs/hour) is replaced by tonnage, and machine names are
 * generated from the specs and unique. Old output and names are kept in remarks.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('machines', function (Blueprint $table) {
            $table->decimal('tonnage', 10, 2)->nullable()->after('model_no');
        });

        foreach (DB::table('machines')->whereNotNull('capacity_per_hour')->get() as $row) {
            DB::table('machines')->where('id', $row->id)->update([
                'remarks' => trim(($row->remarks ? $row->remarks."\n" : '')."Output: {$row->capacity_per_hour} pcs / hour"),
            ]);
        }

        Schema::table('machines', function (Blueprint $table) {
            $table->dropColumn('capacity_per_hour');
        });

        foreach (Machine::orderBy('id')->get() as $machine) {
            $old = $machine->name;
            $machine->name = $machine->generateName();
            if ($machine->name !== $old) {
                $machine->remarks = trim(($machine->remarks ? $machine->remarks."\n" : '')."Previous name: {$old}");
            }
            $machine->saveQuietly();
        }

        Schema::table('machines', function (Blueprint $table) {
            $table->unique('name');
        });
    }

    public function down(): void
    {
        Schema::table('machines', function (Blueprint $table) {
            $table->dropUnique(['name']);
            $table->unsignedInteger('capacity_per_hour')->nullable()->after('model_no');
            $table->dropColumn('tonnage');
        });
    }
};

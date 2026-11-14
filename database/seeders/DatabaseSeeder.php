<?php

namespace Database\Seeders;

use App\Models\Designation;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $superAdmin = User::firstOrNew(['email' => env('SUPER_ADMIN_EMAIL', 'admin@erp.local')]);

        if (! $superAdmin->exists) {
            $superAdmin->fill([
                'name' => env('SUPER_ADMIN_NAME', 'Super Admin'),
                'password' => env('SUPER_ADMIN_PASSWORD', 'Admin@12345'),
                'is_active' => true,
            ]);
            $superAdmin->forceFill(['is_super_admin' => true, 'email_verified_at' => now()])->save();
        }

        foreach ([
            'Accountant' => 'Handles accounts, payments and receipts.',
            'Sales Executive' => 'Manages enquiries, quotations and sales orders.',
            'Purchase Officer' => 'Raises and tracks purchase orders.',
            'Store Keeper' => 'Maintains stock and material movements.',
            'Production Supervisor' => 'Oversees job cards and production.',
            'Quality Inspector' => 'Performs inspections and QC approvals.',
            'HR Admin' => 'Manages employees, shifts, attendance and payroll.',
        ] as $name => $description) {
            Designation::firstOrCreate(['name' => $name], ['description' => $description]);
        }
    }
}

<?php

namespace Database\Seeders;

use App\Models\Association;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        Association::firstOrCreate(
            ['id' => 1],
            [
                'name' => 'Default Association',
                'currency_code' => 'GHS',
                'timezone' => 'Africa/Accra',
                'status' => 'active',
            ]
        );

        $roleNames = [
            'Administrator' => 'Full system access',
            'Treasurer' => 'Finance operations',
            'Secretary' => 'Member operations',
            'Auditor' => 'Read-only oversight',
            'Member' => 'Member self-service access',
        ];

        $rolesByName = [];
        foreach ($roleNames as $name => $description) {
            $role = Role::firstOrCreate(
                ['association_id' => 1, 'name' => $name],
                ['description' => $description, 'is_system' => true, 'created_at' => now()]
            );
            $rolesByName[$name] = $role->id;
        }

        $admin = User::updateOrCreate(
            ['association_id' => 1, 'email' => 'admin@gpspayments.local'],
            [
                'username' => 'admin',
                'password_hash' => Hash::make('Admin123!'),
                'first_name' => 'System',
                'last_name' => 'Admin',
                'status' => 'active',
            ]
        );

        if (!empty($rolesByName['Administrator'])) {
            DB::table('user_roles')->updateOrInsert(
                ['user_id' => $admin->id, 'role_id' => $rolesByName['Administrator']],
                ['assigned_by' => $admin->id, 'assigned_at' => now()]
            );
        }
    }
}

<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Default super-admin — change password immediately after first login
        User::firstOrCreate(
            ['email' => 'admin@posmeister.local'],
            [
                'name'      => 'System Administrator',
                'phone'     => null,
                'role'      => 'admin',
                'branch_id' => null,
                'is_active' => true,
                'password'  => Hash::make('Admin@1234'),
            ]
        );

        $this->command->info('Default admin → admin@posmeister.local / Admin@1234');

        $this->call([
            RolePermissionSeeder::class,
            UnitSeeder::class,
            HrmSeeder::class,
        ]);

        

        $this->call([
            ProductSeeder::class,
        ]);
    }
}

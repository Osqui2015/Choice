<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // Roles
        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'usuario', 'guard_name' => 'web']);

        // Admin
        $adminUser = User::firstOrCreate(
            ['email' => 'admin@choice.test'],
            [
                'name' => 'Administrador',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
        $adminUser->assignRole($admin);

        // Usuario normal
        $regularUser = User::firstOrCreate(
            ['email' => 'usuario@choice.test'],
            [
                'name' => 'Usuario Demo',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
        $regularUser->assignRole('usuario');
    }
}

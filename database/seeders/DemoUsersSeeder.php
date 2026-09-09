<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class DemoUsersSeeder extends Seeder
{
    public function run(): void
    {
        if (! env('SEED_DEMO_USERS', true)) {
            $this->command->warn('⏭️  DemoUsersSeeder deshabilitado por SEED_DEMO_USERS=false');
            return;
        }

        $demoUsers = [
            [
                'name'     => 'Administrador',
                'email'    => 'admin@choice.test',
                'password' => 'password',
                'role'     => 'admin',
            ],
            [
                'name'     => 'Usuario Demo',
                'email'    => 'usuario@choice.test',
                'password' => 'password',
                'role'     => 'usuario',
            ],
        ];

        foreach ($demoUsers as $u) {
            $user = User::updateOrCreate(
                ['email' => $u['email']],
                [
                    'name'              => $u['name'],
                    'password'          => Hash::make($u['password']),
                    'is_active'         => true,
                    'email_verified_at' => now(),
                ],
            );

            // Asegurar que el rol exista y asignarlo
            $role = Role::firstOrCreate(
                ['name' => $u['role'], 'guard_name' => 'web'],
            );

            if (! $user->hasRole($role->name)) {
                $user->assignRole($role->name);
            }

            $this->command->info("  ✓ {$u['email']} / {$u['password']} (rol: {$u['role']})");
        }
    }
}

<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeder que crea los usuarios de demo que se muestran en la pantalla
 * de login (admin@choice.test y usuario@choice.test, ambos con password "password").
 *
 * Es idempotente: si el user ya existe, no hace nada.
 *
 * En producción real probablemente NO quieras correr este seeder
 * (porque expone credenciales conocidas). Para eso, dejá
 * SEED_DEMO_USERS=false en .env, o borralo del DatabaseSeeder.
 */
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
                'name'      => 'Administrador',
                'email'     => 'admin@choice.test',
                'password'  => 'password',
                'is_admin'  => true,
            ],
            [
                'name'      => 'Usuario Demo',
                'email'     => 'usuario@choice.test',
                'password'  => 'password',
                'is_admin'  => false,
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

            if ($u['is_admin'] && ! $user->hasRole('admin')) {
                $user->assignRole('admin');
            }

            $this->command->info("  ✓ {$u['email']} / {$u['password']}" . ($u['is_admin'] ? ' (admin)' : ''));
        }
    }
}

<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RolesSeeder extends Seeder
{
    /**
     * Crea los roles del sistema. Idempotente.
     */
    public function run(): void
    {
        $roles = [
            ['name' => 'admin',   'guard_name' => 'web'],
            ['name' => 'usuario', 'guard_name' => 'web'],
        ];

        foreach ($roles as $role) {
            $existing = Role::where('name', $role['name'])
                ->where('guard_name', $role['guard_name'])
                ->first();

            if (! $existing) {
                Role::create($role);
                $this->command->info("  ✓ Rol '{$role['name']}' creado.");
            } else {
                $this->command->line("  - Rol '{$role['name']}' ya existe, ok.");
            }
        }
    }
}

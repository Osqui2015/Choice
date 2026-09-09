<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name'                => 'Básico',
                'slug'                => 'basico',
                'description'         => 'Acceso ilimitado a 1 especialidad a elección + flashcards clínicas.',
                'price'               => 4990,         // ARS
                'currency'            => 'ARS',
                'duration_days'       => 30,
                'max_specialties'     => 1,
                'includes_flashcards' => true,
                'is_active'           => true,
                'sort_order'          => 1,
            ],
            [
                'name'                => 'Estudiante',
                'slug'                => 'estudiante',
                'description'         => 'Acceso ilimitado a 3 especialidades a elección + flashcards clínicas.',
                'price'               => 9990,
                'currency'            => 'ARS',
                'duration_days'       => 30,
                'max_specialties'     => 3,
                'includes_flashcards' => true,
                'is_active'           => true,
                'sort_order'          => 2,
            ],
            [
                'name'                => 'Full',
                'slug'                => 'full',
                'description'         => 'Acceso ilimitado a TODAS las especialidades + flashcards clínicas.',
                'price'               => 14990,
                'currency'            => 'ARS',
                'duration_days'       => 30,
                'max_specialties'     => null,         // null = ilimitadas
                'includes_flashcards' => true,
                'is_active'           => true,
                'sort_order'          => 3,
            ],
        ];

        foreach ($plans as $data) {
            Plan::updateOrCreate(['slug' => $data['slug']], $data);
        }
    }
}

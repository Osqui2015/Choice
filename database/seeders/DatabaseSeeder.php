<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed inicial para deploy en producción.
     *
     * Orden:
     *  1) PlanSeeder     → planes (Básico, Estudiante, Full)
     *  2) QuestionsSeeder → banco de preguntas + flashcards desde xlsx
     */
    public function run(): void
    {
        $this->call([
            PlanSeeder::class,
            QuestionsSeeder::class,
        ]);
    }
}

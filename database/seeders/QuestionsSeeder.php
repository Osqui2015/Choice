<?php

namespace Database\Seeders;

use App\Models\Question;
use App\Models\Flashcard;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

/**
 * Seeder que importa el banco de preguntas MCQ + flashcards
 * desde `database/imports/MCQ_CLNICA_1.xlsx`.
 *
 * Wrapper del comando `php artisan questions:import` pensado
 * para deploys automatizados:
 *
 *   php artisan db:seed --class=QuestionsSeeder
 *
 * El comando es idempotente: si ya hay preguntas en la DB, el
 * import se reanuda desde la última fila procesada.
 *
 * Si querés forzar reimport completo, pasale --fresh:
 *   php artisan db:seed --class=QuestionsSeeder --fresh
 */
class QuestionsSeeder extends Seeder
{
    public function run(): void
    {
        $xlsx = base_path('database/imports/MCQ_CLNICA_1.xlsx');
        $legacy = base_path('MCQ_CLNICA_1.xlsx');

        $source = file_exists($xlsx) ? $xlsx : (file_exists($legacy) ? $legacy : null);

        if (! $source) {
            $this->command->warn("⚠️  No se encontró el archivo de banco de preguntas.");
            $this->command->line("  Esperado en: database/imports/MCQ_CLNICA_1.xlsx");
            $this->command->line("  (también acepta: raíz del proyecto MCQ_CLNICA_1.xlsx)");
            $this->command->line("  Si necesitás el banco, contactate con el admin.");
            return;
        }

        $this->command->info("📥 Importando banco de preguntas desde " . basename($source));

        $existing = Question::count() + Flashcard::count();
        if ($existing > 0) {
            $this->command->info("   La DB ya tiene {$existing} registros. Reanudando import incremental.");
        }

        // Llamamos al comando Artisan de forma programática
        $exitCode = Artisan::call('questions:import', [
            'file' => $source,
        ]);

        $output = Artisan::output();
        $this->command->line($output);

        if ($exitCode !== 0) {
            throw new \RuntimeException("Falló el import de preguntas. Exit code: {$exitCode}");
        }

        $this->command->info("✅ Banco de preguntas listo.");
    }
}

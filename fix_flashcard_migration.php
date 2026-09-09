<?php
/**
 * Fix para limpiar la tabla user_flashcard_progress creada parcialmente
 * y resetear la migración fallida.
 *
 * Ejecutar una sola vez:
 *   php fix_flashcard_migration.php
 *
 * Después correr:
 *   php artisan migrate --force
 *   php artisan db:seed --force
 */

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo "🔧 Arreglando migración fallida de user_flashcard_progress\n\n";

// 1) Borrar la tabla user_flashcard_progress (si existe)
if (Schema::hasTable('user_flashcard_progress')) {
    Schema::drop('user_flashcard_progress');
    echo "  ✓ Tabla user_flashcard_progress eliminada.\n";
} else {
    echo "  - Tabla user_flashcard_progress no existe, ok.\n";
}

// 2) Borrar la entrada de la migración fallida en la tabla migrations
$deleted = DB::table('migrations')
    ->where('migration', '2026_09_06_231659_create_user_flashcard_progress_table')
    ->delete();

if ($deleted > 0) {
    echo "  ✓ Entrada de migración fallida eliminada de 'migrations'.\n";
} else {
    echo "  - Entrada de migración no estaba en 'migrations', ok.\n";
}

// 3) Verificar que la tabla flashcards existe
if (Schema::hasTable('flashcards')) {
    echo "  ✓ Tabla flashcards existe (la nueva migración la creó).\n";
} else {
    echo "  ✗ Tabla flashcards NO existe. Algo falló. Corré: php artisan migrate --force\n";
    exit(1);
}

echo "\n✅ Listo. Ahora corré:\n";
echo "   php artisan migrate --force\n";
echo "   php artisan db:seed --force\n";

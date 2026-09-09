<?php
/**
 * Fix para limpiar las migraciones fallidas de user_flashcard_progress
 * y add_is_active_to_admin_tables.
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

echo "🔧 Arreglando migraciones fallidas\n\n";

// 1) Borrar la tabla user_flashcard_progress (si existe, para que la migración la recree limpia)
if (Schema::hasTable('user_flashcard_progress')) {
    Schema::drop('user_flashcard_progress');
    echo "  ✓ Tabla user_flashcard_progress eliminada.\n";
} else {
    echo "  - Tabla user_flashcard_progress no existe, ok.\n";
}

// 2) Limpiar las entradas de migraciones fallidas en la tabla migrations
$failedMigrations = [
    '2026_09_06_231659_create_user_flashcard_progress_table',
    '2026_09_07_015825_add_is_active_to_admin_tables',
];

foreach ($failedMigrations as $migration) {
    $deleted = DB::table('migrations')
        ->where('migration', $migration)
        ->delete();

    if ($deleted > 0) {
        echo "  ✓ Entrada '{$migration}' eliminada de 'migrations'.\n";
    } else {
        echo "  - Entrada '{$migration}' no estaba en 'migrations', ok.\n";
    }
}

// 3) Verificar que la tabla flashcards existe
if (Schema::hasTable('flashcards')) {
    echo "  ✓ Tabla flashcards existe (la nueva migración la creó).\n";
} else {
    echo "  ✗ Tabla flashcards NO existe. Corré: php artisan migrate --force primero\n";
    exit(1);
}

echo "\n✅ Listo. Ahora corré:\n";
echo "   php artisan migrate --force\n";
echo "   php artisan db:seed --force\n";

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_daily_quotas', function (Blueprint $table) {
            // Materia que el user eligió para consumir sus 5 preguntas diarias free.
            // Si tiene plan pago, este campo queda en null y el acceso se valida
            // por el plan activo.
            $table->foreignId('daily_specialty_id')
                ->nullable()
                ->after('daily_limit')
                ->constrained('specialties')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('user_daily_quotas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('daily_specialty_id');
        });
    }
};

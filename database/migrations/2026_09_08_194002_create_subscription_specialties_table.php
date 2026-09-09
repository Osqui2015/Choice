<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pivote: qué especialidades específicas eligió el user dentro de su plan.
        // Para el plan "Full" (max_specialties = null) este pivote queda vacío
        // y el acceso a TODAS las especialidades se resuelve en el servicio.
        Schema::create('subscription_specialties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_subscription_id')
                ->constrained('user_subscriptions')
                ->cascadeOnDelete();
            $table->foreignId('specialty_id')
                ->constrained('specialties')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(
                ['user_subscription_id', 'specialty_id'],
                'uniq_sub_specialty'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_specialties');
    }
};

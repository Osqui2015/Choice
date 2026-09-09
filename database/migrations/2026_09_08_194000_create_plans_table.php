<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');                     // "Básico", "Estudiante", "Full"
            $table->string('slug')->unique();           // "basico", "estudiante", "full"
            $table->string('description')->nullable();
            $table->unsignedInteger('price');           // en centavos/pesos (entero)
            $table->string('currency', 3)->default('ARS');
            $table->unsignedSmallInteger('duration_days')->default(30);
            // Cantidad máxima de especialidades que el user puede elegir.
            // null = ilimitadas (plan Full).
            $table->unsignedTinyInteger('max_specialties')->nullable();
            $table->boolean('includes_flashcards')->default(true);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};

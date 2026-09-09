<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('specialties', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique(); // ej: "03_NEFROLOGIA"
            $table->string('name');                 // ej: "Nefrología"
            $table->string('slug')->unique();       // ej: "nefrologia"
            $table->string('icon')->nullable();     // ej: "kidney"
            $table->string('color', 16)->nullable();// ej: "#8b5cf6"
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('specialties');
    }
};

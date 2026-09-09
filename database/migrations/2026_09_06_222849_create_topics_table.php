<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('topics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('specialty_id')->constrained()->cascadeOnDelete();
            $table->string('code', 64);             // ej: "06"
            $table->string('name');                 // ej: "Síndrome nefrítico"
            $table->string('slug');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['specialty_id', 'code']);
            $table->index('slug');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('topics');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flashcards', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('code_number')->unique(); // ID del banco (mismo dominio que questions)

            $table->foreignId('specialty_id')->constrained()->restrictOnDelete();
            $table->foreignId('topic_id')->constrained()->restrictOnDelete();
            $table->foreignId('section_id')->nullable()->constrained()->nullOnDelete();

            $table->text('front');                    // Pregunta / anverso
            $table->text('back');                     // Respuesta / reverso
            $table->text('justification')->nullable();

            $table->string('source_file')->nullable();
            $table->string('catedra')->nullable();
            $table->string('subtopic')->nullable();
            $table->date('source_date')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['specialty_id', 'topic_id']);
            $table->index('section_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flashcards');
    }
};

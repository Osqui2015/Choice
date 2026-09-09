<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('code_number')->unique(); // ID del banco (1..3602)

            $table->foreignId('specialty_id')->constrained()->restrictOnDelete();
            $table->foreignId('topic_id')->constrained()->restrictOnDelete();
            $table->foreignId('section_id')->nullable()->constrained()->nullOnDelete();

            $table->text('statement');                    // Enunciado
            $table->json('options');                      // [ {key:'a',text:'...'}, ... ]
            $table->char('correct_answer', 1);            // a | b | c | d

            $table->text('correct_justification')->nullable();
            $table->text('incorrect_justification')->nullable();

            $table->string('source_file')->nullable();    // PDF original
            $table->text('source_justification')->nullable();
            $table->text('other_sources')->nullable();
            $table->string('catedra')->nullable();        // ej "04_CAT3_Asma Bronquial"
            $table->string('subtopic')->nullable();
            $table->date('source_date')->nullable();

            $table->timestamps();

            $table->index(['specialty_id', 'topic_id']);
            $table->index('section_id');
            $table->index('code_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('questions');
    }
};

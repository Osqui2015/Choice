<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->char('selected_answer', 1);
            $table->boolean('is_correct');
            $table->unsignedInteger('time_spent_seconds')->default(0);
            $table->string('mode', 32)->default('free'); // free | errors | review
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['user_id', 'question_id']);
            $table->index(['user_id', 'is_correct']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_answers');
    }
};

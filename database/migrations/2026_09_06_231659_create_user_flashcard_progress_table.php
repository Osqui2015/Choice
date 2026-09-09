<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_flashcard_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('flashcard_id')->constrained()->cascadeOnDelete();
            $table->boolean('knew_it')->default(false);
            $table->unsignedInteger('review_count')->default(0);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'flashcard_id']);
            $table->index(['user_id', 'knew_it']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_flashcard_progress');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_streaks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('current_daily_streak')->default(0);
            $table->unsignedInteger('max_daily_streak')->default(0);
            $table->date('last_activity_date')->nullable();
            $table->unsignedInteger('current_correct_streak')->default(0);
            $table->unsignedInteger('max_correct_streak')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_streaks');
    }
};

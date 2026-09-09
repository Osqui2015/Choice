<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_daily_quotas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('questions_answered_today')->default(0);
            $table->date('quota_date')->nullable();         // día al que aplica el contador
            $table->timestamp('quota_reset_at')->nullable();// cuando se desbloquea si alcanzó 10
            $table->unsignedTinyInteger('daily_limit')->default(10);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_daily_quotas');
    }
};

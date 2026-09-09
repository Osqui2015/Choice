<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_subscriptions', function (Blueprint $table) {
            // Cuándo el user pidió el plan (status='pending')
            $table->timestamp('requested_at')->nullable()->after('started_at');
            // Cuándo el admin aprobó
            $table->timestamp('approved_at')->nullable()->after('expires_at');
            // Quién aprobó
            $table->foreignId('approved_by_user_id')->nullable()->after('activated_by_user_id')
                ->constrained('users')->nullOnDelete();
            // Si fue rechazada, motivo
            $table->text('rejection_reason')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('user_subscriptions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_by_user_id');
            $table->dropColumn(['requested_at', 'approved_at', 'rejection_reason']);
        });
    }
};

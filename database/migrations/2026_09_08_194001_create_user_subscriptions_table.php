<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained('plans')->restrictOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('expires_at');
            $table->string('status')->default('active'); // active | cancelled | expired
            // Datos del pago (por ahora manuales, listos para MP/Stripe después)
            $table->string('payment_provider')->nullable();   // 'mercadopago' | 'stripe' | 'manual'
            $table->string('payment_reference')->nullable();  // id de transacción / cupón
            $table->unsignedInteger('amount_paid')->nullable();
            $table->string('currency', 3)->default('ARS');
            // Auditoría
            $table->foreignId('activated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_subscriptions');
    }
};

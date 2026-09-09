<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Teléfono en formato libre: 5491112345678, +5491112345678, etc.
            $table->string('phone', 32)->nullable()->after('email');
            // Consentimiento para recibir promociones por WhatsApp
            $table->boolean('accepts_promotions')->default(false)->after('phone');

            $table->index('phone');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['phone']);
            $table->dropColumn(['phone', 'accepts_promotions']);
        });
    }
};

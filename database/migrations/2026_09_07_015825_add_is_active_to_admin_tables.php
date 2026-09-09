<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agrega la columna is_active a las tablas administrativas.
     *
     * Idempotente: si la columna ya existe (por ejemplo, porque la
     * tabla flashcards ahora se crea con is_active desde su migración
     * original), la deja estar sin error.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'is_active')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('is_active')->default(true)->after('premium_until');
            });
        }

        if (! Schema::hasColumn('specialties', 'is_active')) {
            Schema::table('specialties', function (Blueprint $table) {
                $table->boolean('is_active')->default(true)->after('sort_order');
            });
        }

        if (! Schema::hasColumn('questions', 'is_active')) {
            Schema::table('questions', function (Blueprint $table) {
                $table->boolean('is_active')->default(true)->after('source_date');
                $table->index('is_active');
            });
        }

        if (! Schema::hasColumn('flashcards', 'is_active')) {
            Schema::table('flashcards', function (Blueprint $table) {
                $table->boolean('is_active')->default(true)->after('source_date');
                $table->index('is_active');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'is_active')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('is_active');
            });
        }
        if (Schema::hasColumn('specialties', 'is_active')) {
            Schema::table('specialties', function (Blueprint $table) {
                $table->dropColumn('is_active');
            });
        }
        if (Schema::hasColumn('questions', 'is_active')) {
            Schema::table('questions', function (Blueprint $table) {
                $table->dropIndex(['is_active']);
                $table->dropColumn('is_active');
            });
        }
        if (Schema::hasColumn('flashcards', 'is_active')) {
            Schema::table('flashcards', function (Blueprint $table) {
                $table->dropIndex(['is_active']);
                $table->dropColumn('is_active');
            });
        }
    }
};

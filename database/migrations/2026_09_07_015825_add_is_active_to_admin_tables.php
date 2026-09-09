<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('premium_until');
        });

        Schema::table('specialties', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('sort_order');
        });

        Schema::table('questions', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('source_date');
            $table->index('is_active');
        });

        Schema::table('flashcards', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('source_date');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
        Schema::table('specialties', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
        Schema::table('questions', function (Blueprint $table) {
            $table->dropIndex(['is_active']);
            $table->dropColumn('is_active');
        });
        Schema::table('flashcards', function (Blueprint $table) {
            $table->dropIndex(['is_active']);
            $table->dropColumn('is_active');
        });
    }
};

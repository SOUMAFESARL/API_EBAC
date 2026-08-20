<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('etudiants', 'sexe')) {
            Schema::table('etudiants', fn (Blueprint $table) => $table->string('sexe', 20)->nullable());
        }
        if (! Schema::hasColumn('etudiants', 'user_id')) {
            Schema::table('etudiants', fn (Blueprint $table) => $table->foreignId('user_id')->nullable()->unique()->constrained('users')->nullOnDelete());
        }
        if (! Schema::hasColumn('etudiants', 'eglise_id')) {
            Schema::table('etudiants', fn (Blueprint $table) => $table->foreignId('eglise_id')->nullable()->constrained('eglises')->nullOnDelete());
        }
        foreach (['created_by', 'updated_by', 'deleted_by'] as $colonne) {
            if (! Schema::hasColumn('etudiants', $colonne)) {
                Schema::table('etudiants', fn (Blueprint $table) => $table->foreignId($colonne)->nullable()->constrained('users')->nullOnDelete());
            }
        }

        foreach (['user_id', 'created_by', 'updated_by', 'deleted_by'] as $colonne) {
            if (! Schema::hasColumn('dossiers_etudiants', $colonne)) {
                Schema::table('dossiers_etudiants', fn (Blueprint $table) => $table->foreignId($colonne)->nullable()->constrained('users')->nullOnDelete());
            }
        }
        if (! Schema::hasColumn('dossiers_etudiants', 'deleted_at')) {
            Schema::table('dossiers_etudiants', fn (Blueprint $table) => $table->softDeletes());
        }
    }

    public function down(): void
    {
        // Les colonnes sont conservées pour éviter une perte de données.
    }
};

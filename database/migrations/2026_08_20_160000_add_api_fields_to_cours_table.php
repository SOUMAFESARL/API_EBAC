<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('cours', 'code')) {
            Schema::table('cours', fn (Blueprint $table) => $table->string('code', 50)->nullable());
        }
        if (! Schema::hasColumn('cours', 'actif')) {
            Schema::table('cours', fn (Blueprint $table) => $table->boolean('actif')->default(true));
        }
        foreach (['user_id', 'created_by', 'updated_by', 'deleted_by'] as $colonne) {
            if (! Schema::hasColumn('cours', $colonne)) {
                Schema::table('cours', fn (Blueprint $table) => $table->foreignId($colonne)->nullable()->constrained('users')->nullOnDelete());
            }
        }
        if (! Schema::hasColumn('cours', 'deleted_at')) {
            Schema::table('cours', fn (Blueprint $table) => $table->softDeletes());
        }
    }

    public function down(): void
    {
        // Les colonnes sont conservées pour éviter une perte de données.
    }
};

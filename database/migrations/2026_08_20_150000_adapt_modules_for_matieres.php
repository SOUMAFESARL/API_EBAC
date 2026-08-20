<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $relationMatiereAjoutee = ! Schema::hasColumn('modules', 'id_matiere');
        if ($relationMatiereAjoutee) {
            Schema::table('modules', fn (Blueprint $table) => $table->foreignId('id_matiere')->nullable()->constrained('matieres')->cascadeOnDelete());
        }
        if (! Schema::hasColumn('modules', 'code')) {
            Schema::table('modules', fn (Blueprint $table) => $table->string('code', 50)->nullable());
        }
        if (! Schema::hasColumn('modules', 'description')) {
            Schema::table('modules', fn (Blueprint $table) => $table->text('description')->nullable());
        }
        foreach (['user_id', 'created_by', 'updated_by', 'deleted_by'] as $colonne) {
            if (! Schema::hasColumn('modules', $colonne)) {
                Schema::table('modules', fn (Blueprint $table) => $table->foreignId($colonne)->nullable()->constrained('users')->nullOnDelete());
            }
        }
        if (! Schema::hasColumn('modules', 'deleted_at')) {
            Schema::table('modules', fn (Blueprint $table) => $table->softDeletes());
        }

        if ($relationMatiereAjoutee) {
            Schema::table('modules', function (Blueprint $table) {
                $table->unique(['id_matiere', 'libelle'], 'uk_module_matiere_libelle');
            });
        }

        if (Schema::hasColumn('modules', 'id_unite_valeur')) {
            Schema::table('modules', fn (Blueprint $table) => $table->foreignId('id_unite_valeur')->nullable()->change());
        }
    }

    public function down(): void
    {
        // La colonne héritée id_unite_valeur est volontairement préservée.
    }
};

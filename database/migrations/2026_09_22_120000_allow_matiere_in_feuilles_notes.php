<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('feuilles_notes', function (Blueprint $table) {
            $table->unsignedBigInteger('id_cours')->nullable()->change();
            $table->foreignId('id_matiere')->nullable()->constrained('matieres');
            $table->unique(['id_annee_academique', 'id_promotion', 'id_matiere'], 'feuille_notes_matiere_unique');
        });
    }

    public function down(): void
    {
        if (DB::table('feuilles_notes')->whereNull('id_cours')->exists()) {
            throw new RuntimeException('Des notes par matière existent : le retour arrière supprimerait leur contexte.');
        }
        Schema::table('feuilles_notes', function (Blueprint $table) {
            $table->dropUnique('feuille_notes_matiere_unique');
            $table->dropForeign(['id_matiere']);
            $table->dropColumn('id_matiere');
            $table->unsignedBigInteger('id_cours')->nullable(false)->change();
        });
    }
};

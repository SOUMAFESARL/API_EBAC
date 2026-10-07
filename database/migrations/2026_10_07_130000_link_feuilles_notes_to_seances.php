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
            $table->foreignId('id_seance')->nullable()->constrained('seances_cahier_texte');
            $table->unique(['id_annee_academique', 'id_promotion', 'id_cours', 'id_seance'], 'feuille_notes_cours_seance_unique');
            $table->unique(['id_annee_academique', 'id_promotion', 'id_matiere', 'id_seance'], 'feuille_notes_matiere_seance_unique');
        });
        Schema::table('feuilles_notes', function (Blueprint $table) {
            $table->dropUnique('feuille_notes_contexte_unique');
            $table->dropUnique('feuille_notes_matiere_unique');
        });
    }

    public function down(): void
    {
        foreach (['id_cours', 'id_matiere'] as $champ) {
            if (DB::table('feuilles_notes')->whereNotNull($champ)
                ->groupBy('id_annee_academique', 'id_promotion', $champ)->havingRaw('COUNT(*) > 1')->exists()) {
                throw new RuntimeException('Plusieurs feuilles par seance existent : retour arriere impossible sans perte de donnees.');
            }
        }
        Schema::table('feuilles_notes', function (Blueprint $table) {
            $table->unique(['id_annee_academique', 'id_promotion', 'id_cours'], 'feuille_notes_contexte_unique');
            $table->unique(['id_annee_academique', 'id_promotion', 'id_matiere'], 'feuille_notes_matiere_unique');
        });
        Schema::table('feuilles_notes', function (Blueprint $table) {
            $table->dropUnique('feuille_notes_cours_seance_unique');
            $table->dropUnique('feuille_notes_matiere_seance_unique');
            $table->dropConstrainedForeignId('id_seance');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notes_cours', function (Blueprint $table) {
            $table->string('evaluation', 100)->default('principale');
            $table->unique(['id_feuille_notes', 'id_etudiant', 'evaluation'], 'notes_evaluation_unique');
        });
        Schema::table('notes_cours', fn (Blueprint $table) => $table->dropUnique(['id_feuille_notes', 'id_etudiant']));
    }

    public function down(): void
    {
        if (DB::table('notes_cours')->select('id_feuille_notes', 'id_etudiant')
            ->groupBy('id_feuille_notes', 'id_etudiant')->havingRaw('COUNT(*) > 1')->exists()) {
            throw new RuntimeException('Plusieurs notes existent par etudiant : retour arriere impossible sans perte de donnees.');
        }
        Schema::table('notes_cours', fn (Blueprint $table) => $table->unique(['id_feuille_notes', 'id_etudiant']));
        Schema::table('notes_cours', function (Blueprint $table) {
            $table->dropUnique('notes_evaluation_unique');
            $table->dropColumn('evaluation');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('passages_niveau', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_promotion')->constrained('promotions')->restrictOnDelete();
            $table->foreignId('id_niveau_source')->constrained('niveaux')->restrictOnDelete();
            $table->foreignId('id_niveau_cible')->constrained('niveaux')->restrictOnDelete();
            $table->foreignId('id_annee_source')->nullable()->constrained('annees_academiques')->nullOnDelete();
            $table->foreignId('id_annee_cible')->constrained('annees_academiques')->restrictOnDelete();
            $table->unsignedInteger('etudiants_avances')->default(0);
            $table->unsignedInteger('etudiants_non_avances')->default(0);
            $table->unsignedInteger('cours_a_faire_reportes')->default(0);
            $table->boolean('malgre_notes_en_attente')->default(false);
            $table->foreignId('effectue_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['id_promotion', 'id_annee_cible']);
        });

        // Une inscription par année : l'étudiant garde sa promotion d'une année sur l'autre.
        if (Schema::hasIndex('inscriptions', 'inscriptions_id_etudiant_id_promotion_unique')) {
            Schema::table('inscriptions', fn (Blueprint $table) => $table->dropUnique('inscriptions_id_etudiant_id_promotion_unique'));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('passages_niveau');
    }
};

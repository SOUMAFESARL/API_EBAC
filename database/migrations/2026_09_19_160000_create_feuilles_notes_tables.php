<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feuilles_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_annee_academique')->constrained('annees_academiques');
            $table->foreignId('id_promotion')->constrained('promotions');
            $table->foreignId('id_cours')->constrained('cours');
            $table->string('statut')->default('brouillon');
            $table->timestamp('date_transmission')->nullable();
            $table->foreignId('updated_by')->constrained('users');
            $table->timestamps();
            $table->unique(['id_annee_academique', 'id_promotion', 'id_cours'], 'feuille_notes_contexte_unique');
        });
        Schema::create('notes_cours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_feuille_notes')->constrained('feuilles_notes')->cascadeOnDelete();
            $table->foreignId('id_etudiant')->constrained('etudiants');
            $table->decimal('note', 4, 2);
            $table->timestamps();
            $table->unique(['id_feuille_notes', 'id_etudiant']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notes_cours');
        Schema::dropIfExists('feuilles_notes');
    }
};

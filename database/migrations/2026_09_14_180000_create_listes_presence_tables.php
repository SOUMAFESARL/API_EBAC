<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feuilles_presence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_seance')->unique()->constrained('seances_cahier_texte')->cascadeOnDelete();
            $table->string('statut', 20)->default('brouillon');
            $table->dateTime('date_validation')->nullable();
            $table->foreignId('validee_par')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        Schema::create('presences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_feuille_presence')->constrained('feuilles_presence')->cascadeOnDelete();
            $table->foreignId('id_etudiant')->constrained('etudiants')->restrictOnDelete();
            $table->string('statut', 10);
            $table->timestamps();
            $table->unique(['id_feuille_presence', 'id_etudiant'], 'presences_feuille_etudiant_unique');
        });
        Schema::create('cours_a_faire', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_etudiant')->constrained('etudiants')->restrictOnDelete();
            $table->foreignId('id_cours')->nullable()->constrained('cours')->restrictOnDelete();
            $table->foreignId('id_matiere')->constrained('matieres')->restrictOnDelete();
            $table->foreignId('id_seance')->constrained('seances_cahier_texte')->cascadeOnDelete();
            $table->string('statut', 20)->default('a_faire');
            $table->string('motif')->default('absence');
            $table->timestamps();
            $table->unique(['id_etudiant', 'id_seance'], 'cours_a_faire_etudiant_seance_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cours_a_faire');
        Schema::dropIfExists('presences');
        Schema::dropIfExists('feuilles_presence');
    }
};

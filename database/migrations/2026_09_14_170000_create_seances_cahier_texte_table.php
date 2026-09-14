<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seances_cahier_texte', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_creneau')->nullable()->constrained('creneaux')->nullOnDelete();
            $table->foreignId('id_module_calendrier')->nullable()->constrained('modules_calendrier')->nullOnDelete();
            $table->foreignId('enseignant_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('id_niveau')->constrained('niveaux')->restrictOnDelete();
            $table->foreignId('id_matiere')->constrained('matieres')->restrictOnDelete();
            $table->foreignId('id_cours')->nullable()->constrained('cours')->restrictOnDelete();
            $table->foreignId('id_promotion')->nullable()->constrained('promotions')->restrictOnDelete();
            $table->foreignId('id_salle')->nullable()->constrained('salles')->nullOnDelete();
            $table->date('date_prevue');
            $table->time('heure_debut_prevue');
            $table->time('heure_fin_prevue')->nullable();
            $table->string('statut', 20)->default('prevue');
            $table->date('date_effective')->nullable();
            $table->time('heure_effective')->nullable();
            $table->unsignedSmallInteger('duree_reelle_minutes')->nullable();
            $table->text('theme_traite')->nullable();
            $table->text('observations')->nullable();
            $table->text('supports_pedagogiques')->nullable();
            $table->text('motif_annulation')->nullable();
            $table->string('source', 20)->default('planning');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['id_creneau', 'date_prevue'], 'seances_creneau_date_unique');
            $table->index(['enseignant_id', 'date_prevue', 'statut'], 'seances_enseignant_date_statut_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seances_cahier_texte');
    }
};

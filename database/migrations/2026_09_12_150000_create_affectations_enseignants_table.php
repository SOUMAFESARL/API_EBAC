<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('affectations_enseignants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_annee_academique')->constrained('annees_academiques')->restrictOnDelete();
            $table->foreignId('enseignant_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('id_matiere')->constrained('matieres')->restrictOnDelete();
            $table->foreignId('id_cours')->nullable()->constrained('cours')->restrictOnDelete();
            $table->string('portee', 20);
            $table->date('date_debut');
            $table->date('date_fin')->nullable();
            $table->text('motif_fin')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['id_annee_academique', 'date_debut', 'date_fin'], 'affectations_annee_dates_index');
            $table->index(['id_matiere', 'id_cours', 'date_fin'], 'affectations_cible_index');
            $table->index(['enseignant_id', 'date_fin']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('affectations_enseignants');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('creneaux', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_module_calendrier')->nullable()->constrained('modules_calendrier')->nullOnDelete();
            $table->foreignId('id_niveau')->constrained('niveaux')->restrictOnDelete();
            $table->foreignId('id_matiere')->constrained('matieres')->restrictOnDelete();
            $table->foreignId('id_cours')->nullable()->constrained('cours')->restrictOnDelete();
            $table->foreignId('id_promotion')->nullable()->constrained('promotions')->restrictOnDelete();
            $table->foreignId('enseignant_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('id_salle')->constrained('salles')->restrictOnDelete();
            $table->unsignedTinyInteger('jour');
            $table->time('heure_debut');
            $table->time('heure_fin');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['jour', 'heure_debut', 'heure_fin']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('creneaux');
    }
};

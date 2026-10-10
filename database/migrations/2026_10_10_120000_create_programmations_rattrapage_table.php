<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('programmations_rattrapage', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_cours_a_faire')->unique()->constrained('cours_a_faire')->cascadeOnDelete();
            $table->date('date_prevue');
            $table->time('heure_debut');
            $table->time('heure_fin');
            $table->foreignId('enseignant_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('id_salle')->nullable()->constrained('salles')->nullOnDelete();
            $table->string('statut', 20)->default('programme');
            $table->text('observations')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('programmations_rattrapage');
    }
};

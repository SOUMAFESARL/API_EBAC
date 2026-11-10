<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Une promotion n'occupe un niveau qu'une fois : (matière, promotion) suffit, l'année reste pour la trace.
        Schema::create('matieres_terminees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_matiere')->constrained('matieres')->cascadeOnDelete();
            $table->foreignId('id_promotion')->constrained('promotions')->cascadeOnDelete();
            $table->foreignId('id_annee_academique')->nullable()->constrained('annees_academiques')->nullOnDelete();
            $table->foreignId('terminee_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['id_matiere', 'id_promotion']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('matieres_terminees');
    }
};

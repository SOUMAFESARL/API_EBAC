<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parcours_academiques', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_etudiant')->constrained('etudiants')->cascadeOnDelete();
            $table->foreignId('id_annee_academique')->nullable()->constrained('annees_academiques')->nullOnDelete();
            $table->foreignId('id_niveau')->nullable()->constrained('niveaux')->nullOnDelete();
            $table->foreignId('id_promotion')->nullable()->constrained('promotions')->nullOnDelete();
            $table->string('annee_academique_externe', 20)->nullable();
            $table->string('niveau_externe', 150)->nullable();
            $table->string('promotion_externe', 150)->nullable();
            $table->string('etablissement', 200)->nullable();
            $table->string('type_parcours', 20)->default('Interne');
            $table->string('statut', 30)->default('En cours');
            $table->decimal('moyenne_generale', 5, 2)->nullable();
            $table->string('decision', 50)->nullable();
            $table->date('date_debut')->nullable();
            $table->date('date_fin')->nullable();
            $table->text('observations')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['id_etudiant', 'statut']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parcours_academiques');
    }
};

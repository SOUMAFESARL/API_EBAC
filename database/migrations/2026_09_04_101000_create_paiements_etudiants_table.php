<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paiements_etudiants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_etudiant')->constrained('etudiants')->restrictOnDelete();
            $table->foreignId('id_inscription')->nullable()->constrained('inscriptions')->nullOnDelete();
            $table->foreignId('id_annee_academique')->constrained('annees_academiques')->restrictOnDelete();
            $table->string('type_paiement', 30);
            $table->decimal('montant', 12, 2);
            $table->dateTime('date_paiement');
            $table->string('mode_paiement', 30)->nullable();
            $table->string('reference', 100)->nullable()->unique();
            $table->string('statut', 30)->default('Validé');
            $table->string('recu_chemin')->nullable();
            $table->text('observations')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['id_etudiant', 'id_annee_academique', 'type_paiement'], 'paiements_etudiant_annee_type_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paiements_etudiants');
    }
};

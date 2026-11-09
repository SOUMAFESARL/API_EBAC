<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journaux_audit', function (Blueprint $table) {
            $table->id();
            $table->dateTime('horodatage', 3);
            $table->foreignId('id_user')->nullable()->constrained('users')->nullOnDelete();
            $table->string('acteur_nom', 200);
            $table->string('acteur_profil', 40)->nullable();
            $table->string('categorie', 30);
            $table->string('criticite', 10);
            $table->string('action', 255);
            $table->string('objet_type', 100)->nullable();
            $table->string('objet_libelle', 255)->nullable();
            $table->json('valeur_avant')->nullable();
            $table->json('valeur_apres')->nullable();
            $table->string('adresse_ip', 45)->nullable();

            $table->index('horodatage');
            $table->index(['categorie', 'horodatage']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journaux_audit');
    }
};

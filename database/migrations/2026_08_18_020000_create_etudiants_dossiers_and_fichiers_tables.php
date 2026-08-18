<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('etudiants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_user')->nullable()->unique()->constrained('users')->nullOnDelete();
            $table->string('matricule', 50)->unique();
            $table->string('nom', 150);
            $table->string('prenoms', 150);
            $table->string('sexe', 20)->nullable();
            $table->date('date_naissance')->nullable();
            $table->string('lieu_naissance', 150)->nullable();
            $table->string('nationalite', 80)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('telephone', 30)->nullable();
            $table->string('adresse')->nullable();
            $table->foreignId('id_eglise')->nullable()->constrained('eglises')->nullOnDelete();
            $table->string('statut_professionnel', 100)->nullable();
            $table->string('photo_identite')->nullable()->comment('Chemin du fichier de la photo d identite');
            $table->date('date_inscription');
            $table->string('statut', 50)->default('En formation');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['nom', 'prenoms']);
            $table->index('statut');
        });

        Schema::create('dossiers_etudiants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_etudiant')->unique()->constrained('etudiants')->cascadeOnDelete();
            $table->string('numero_dossier', 50)->unique();
            $table->string('statut', 30)->default('Incomplet');
            $table->date('date_ouverture');
            $table->json('pieces_requises')->nullable();
            $table->text('observations')->nullable();
            $table->timestamps();
        });

        Schema::create('fichiers_dossiers_etudiants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_dossier_etudiant')
                ->constrained('dossiers_etudiants')
                ->cascadeOnDelete();
            $table->string('type_piece', 100)->nullable();
            $table->string('nom_original');
            $table->string('chemin');
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('taille')->nullable()->comment('Taille du fichier en octets');
            $table->timestamps();

            $table->index(['id_dossier_etudiant', 'type_piece']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fichiers_dossiers_etudiants');
        Schema::dropIfExists('dossiers_etudiants');
        Schema::dropIfExists('etudiants');
    }
};

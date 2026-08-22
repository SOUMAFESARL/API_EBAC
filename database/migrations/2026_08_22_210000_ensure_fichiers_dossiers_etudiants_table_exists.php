<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fichiers_dossiers_etudiants')) {
            return;
        }

        Schema::create('fichiers_dossiers_etudiants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_dossier_etudiant')
                ->constrained('dossiers_etudiants')
                ->cascadeOnDelete();
            $table->string('type_piece', 100)->nullable();
            $table->string('nom_original');
            $table->string('chemin');
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('taille')->nullable();
            $table->timestamps();

            $table->index(['id_dossier_etudiant', 'type_piece']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fichiers_dossiers_etudiants');
    }
};

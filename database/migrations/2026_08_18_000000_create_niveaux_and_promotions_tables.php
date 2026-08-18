<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('niveaux', function (Blueprint $table) {
            $table->id();
            $table->string('libelle', 100);
            $table->string('code', 20)->unique();
            $table->unsignedSmallInteger('rang')->unique();
            $table->enum('statut', ['Actif', 'Archive'])->default('Actif');
            $table->timestamps();
        });

        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('rang')->unique();
            $table->unsignedSmallInteger('annee_entree')->unique();
            $table->foreignId('id_niveau')
                ->comment('Niveau actuellement suivi par la promotion')
                ->constrained('niveaux')
                ->restrictOnDelete();
            $table->enum('statut', ['En cours', 'Diplomee', 'Archivee'])->default('En cours');
            $table->timestamps();

            $table->index(['statut', 'id_niveau']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotions');
        Schema::dropIfExists('niveaux');
    }
};

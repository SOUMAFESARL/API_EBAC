<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reclamations_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_etudiant')->constrained('etudiants');
            $table->foreignId('id_ligne_bulletin')->constrained('lignes_bulletins');
            $table->decimal('note_contestee', 5, 2);
            $table->text('motif');
            $table->string('statut')->default('en_attente')->index();
            $table->text('reponse')->nullable();
            $table->foreignId('traitee_par')->nullable()->constrained('users');
            $table->timestamp('date_traitement')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reclamations_notes');
    }
};

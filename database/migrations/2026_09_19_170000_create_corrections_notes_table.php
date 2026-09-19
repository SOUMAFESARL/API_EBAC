<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('corrections_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_note')->constrained('notes_cours');
            $table->decimal('note_initiale', 4, 2);
            $table->decimal('note_proposee', 4, 2);
            $table->decimal('note_finale', 4, 2)->nullable();
            $table->text('motif');
            $table->string('statut')->default('en_attente')->index();
            $table->foreignId('demande_par')->constrained('users');
            $table->foreignId('autorisee_par')->nullable()->constrained('users');
            $table->foreignId('appliquee_par')->nullable()->constrained('users');
            $table->timestamp('autorisee_le')->nullable();
            $table->timestamp('appliquee_le')->nullable();
            $table->timestamps();
        });
        Schema::create('traces_corrections_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_correction')->constrained('corrections_notes');
            $table->foreignId('id_acteur')->constrained('users');
            $table->string('action');
            $table->text('details')->nullable();
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('traces_corrections_notes');
        Schema::dropIfExists('corrections_notes');
    }
};

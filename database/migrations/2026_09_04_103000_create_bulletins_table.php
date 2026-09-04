<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bulletins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_inscription')->constrained('inscriptions')->cascadeOnDelete();
            $table->string('periode', 50);
            $table->decimal('moyenne', 5, 2)->nullable();
            $table->string('mention', 50)->nullable();
            $table->unsignedSmallInteger('rang')->nullable();
            $table->string('decision', 50)->nullable();
            $table->string('fichier_chemin')->nullable();
            $table->dateTime('date_publication')->nullable();
            $table->string('statut', 30)->default('Brouillon');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['id_inscription', 'periode']);
        });

        Schema::create('lignes_bulletins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_bulletin')->constrained('bulletins')->cascadeOnDelete();
            $table->foreignId('id_matiere')->constrained('matieres')->restrictOnDelete();
            $table->decimal('note', 5, 2)->nullable();
            $table->decimal('coefficient', 6, 2)->default(1);
            $table->decimal('moyenne_ponderee', 7, 2)->nullable();
            $table->text('appreciation')->nullable();
            $table->timestamps();

            $table->unique(['id_bulletin', 'id_matiere']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lignes_bulletins');
        Schema::dropIfExists('bulletins');
    }
};

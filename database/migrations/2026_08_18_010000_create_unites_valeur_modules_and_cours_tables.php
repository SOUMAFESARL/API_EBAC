<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unites_valeur', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('libelle', 180);
            $table->foreignId('id_niveau')->constrained('niveaux')->restrictOnDelete();
            $table->decimal('coefficient', 5, 2)->default(1);
            $table->string('type', 50)->default('Standard - presentiel');
            $table->boolean('obligatoire')->default(true);
            $table->decimal('volume_horaire', 6, 2)->default(0);
            $table->enum('statut', ['Active', 'Archivee'])->default('Active');
            $table->text('description')->nullable();
            $table->text('objectifs_pedagogiques')->nullable();
            $table->string('condition_validation', 150)
                ->default('Tous les cours suivis + note UV superieure ou egale au seuil');
            $table->decimal('note_minimale_validation', 4, 2)->default(10);
            $table->string('modalite_evaluation', 100)->default('Evaluation continue par cours');
            $table->foreignId('id_prerequis')->nullable()->constrained('unites_valeur')->nullOnDelete();
            $table->boolean('acquis_irrevocable')->default(true);
            $table->timestamps();

            $table->index(['id_niveau', 'statut']);
        });

        Schema::create('modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_unite_valeur')->constrained('unites_valeur')->cascadeOnDelete();
            $table->string('libelle', 180);
            $table->unsignedSmallInteger('ordre')->default(1);
            $table->timestamps();

            $table->unique(['id_unite_valeur', 'libelle']);
            $table->unique(['id_unite_valeur', 'ordre']);
        });

        Schema::create('cours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_module')->constrained('modules')->cascadeOnDelete();
            $table->string('libelle', 180);
            $table->decimal('volume_horaire', 6, 2)->default(1);
            $table->decimal('coefficient', 5, 2)->default(1);
            $table->unsignedSmallInteger('ordre')->default(1);
            $table->timestamps();

            $table->unique(['id_module', 'libelle']);
            $table->unique(['id_module', 'ordre']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cours');
        Schema::dropIfExists('modules');
        Schema::dropIfExists('unites_valeur');
    }
};

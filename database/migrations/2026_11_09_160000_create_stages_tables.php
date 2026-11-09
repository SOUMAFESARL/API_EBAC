<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stage de 3e Année (CDC §7) — 09/11/2026.
 *
 * Une campagne par promotion porte les bornes du stage ; à l'ouverture,
 * chaque étudiant inscrit dans la promotion reçoit une affectation vierge.
 * La validation d'une affectation ouvre le stage : l'église d'accueil le
 * suit, le stagiaire tient son journal hebdomadaire et dépose ses rapports.
 *
 * Les statuts de campagne et de stage ne sont pas stockés : ils se lisent
 * des dates et des écritures. Une semaine validée, une évaluation remise,
 * une part administrative portée et une décision de la Direction ne se
 * reprennent pas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campagnes_stage', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_promotion')->unique()->constrained('promotions')->restrictOnDelete();
            $table->date('date_debut');
            $table->date('date_fin');
            $table->foreignId('ouverte_par')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('affectations_stage', function (Blueprint $table) {
            $table->id();
            // Une campagne ne se supprime que sans affectation validée :
            // ses affectations vierges ou saisies partent avec elle.
            $table->foreignId('id_campagne')->constrained('campagnes_stage')->cascadeOnDelete();
            $table->foreignId('id_etudiant')->constrained('etudiants')->restrictOnDelete();
            $table->foreignId('id_eglise')->nullable()->constrained('eglises')->restrictOnDelete();
            $table->string('tuteur_nom', 160)->nullable();
            $table->string('tuteur_telephone', 40)->nullable();
            $table->string('tuteur_email', 180)->nullable();
            $table->string('statut', 20)->default('a-affecter');
            $table->timestamp('validee_le')->nullable();
            $table->foreignId('validee_par')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['id_campagne', 'id_etudiant']);
        });

        Schema::create('stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_affectation')->unique()->constrained('affectations_stage')->restrictOnDelete();
            $table->foreignId('id_campagne')->constrained('campagnes_stage')->restrictOnDelete();
            $table->foreignId('id_etudiant')->constrained('etudiants')->restrictOnDelete();
            $table->foreignId('id_eglise')->constrained('eglises')->restrictOnDelete();
            $table->string('encadreur', 160);

            // Part Église (85 %) — remise par le compte de l'église.
            $table->json('evaluation_criteres')->nullable();
            $table->text('evaluation_appreciation')->nullable();
            $table->decimal('evaluation_note', 4, 2)->nullable();
            $table->timestamp('evaluation_remise_le')->nullable();
            $table->foreignId('evaluation_remise_par')->nullable()->constrained('users')->nullOnDelete();

            // Part de l'Administration EBAC (15 %).
            $table->json('part_admin_criteres')->nullable();
            $table->text('part_admin_appreciation')->nullable();
            $table->timestamp('part_admin_portee_le')->nullable();
            $table->foreignId('part_admin_portee_par')->nullable()->constrained('users')->nullOnDelete();

            // Décision de la Direction sur la matière Stage.
            $table->string('validation_decision', 20)->nullable();
            $table->text('validation_motif')->nullable();
            $table->timestamp('validation_prononcee_le')->nullable();
            $table->foreignId('validation_prononcee_par')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
        });

        Schema::create('semaines_stage', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_stage')->constrained('stages')->cascadeOnDelete();
            $table->unsignedSmallInteger('numero');
            $table->string('theme', 160);
            $table->text('activites');
            $table->unsignedSmallInteger('heures');
            $table->string('statut', 20)->default('a-valider');
            $table->text('observation')->nullable();
            $table->timestamp('statuee_le')->nullable();
            $table->foreignId('statuee_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['id_stage', 'numero']);
        });

        Schema::create('rapports_stage', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_stage')->constrained('stages')->cascadeOnDelete();
            $table->string('type', 20);
            $table->string('chemin');
            $table->string('nom_fichier');
            $table->unsignedBigInteger('taille_octets');
            $table->timestamps();
            $table->unique(['id_stage', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rapports_stage');
        Schema::dropIfExists('semaines_stage');
        Schema::dropIfExists('stages');
        Schema::dropIfExists('affectations_stage');
        Schema::dropIfExists('campagnes_stage');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fichiers_dossiers_etudiants', function (Blueprint $table) {
            $table->string('statut_validation', 30)->default('En attente')->after('taille');
            $table->dateTime('date_validation')->nullable()->after('statut_validation');
            $table->date('date_expiration')->nullable()->after('date_validation');
            $table->text('motif_rejet')->nullable()->after('date_expiration');
            $table->foreignId('valide_par')->nullable()->after('motif_rejet')->constrained('users')->nullOnDelete();
            $table->index(['id_dossier_etudiant', 'statut_validation']);
        });

        Schema::table('inscriptions', function (Blueprint $table) {
            $table->foreignId('id_annee_academique')->nullable()->after('id_promotion')
                ->constrained('annees_academiques')->restrictOnDelete();
            $table->unique(['id_etudiant', 'id_annee_academique'], 'inscriptions_etudiant_annee_unique');
        });
    }

    public function down(): void
    {
        Schema::table('inscriptions', function (Blueprint $table) {
            $table->dropUnique('inscriptions_etudiant_annee_unique');
            $table->dropConstrainedForeignId('id_annee_academique');
        });

        Schema::table('fichiers_dossiers_etudiants', function (Blueprint $table) {
            $table->dropIndex(['id_dossier_etudiant', 'statut_validation']);
            $table->dropConstrainedForeignId('valide_par');
            $table->dropColumn(['statut_validation', 'date_validation', 'date_expiration', 'motif_rejet']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('fichiers_dossiers_etudiants', 'statut_validation')) {
            Schema::table('fichiers_dossiers_etudiants', fn (Blueprint $table) =>
                $table->string('statut_validation', 30)->default('En attente')->after('taille'));
        }
        if (! Schema::hasColumn('fichiers_dossiers_etudiants', 'date_validation')) {
            Schema::table('fichiers_dossiers_etudiants', fn (Blueprint $table) =>
                $table->dateTime('date_validation')->nullable()->after('statut_validation'));
        }
        if (! Schema::hasColumn('fichiers_dossiers_etudiants', 'date_expiration')) {
            Schema::table('fichiers_dossiers_etudiants', fn (Blueprint $table) =>
                $table->date('date_expiration')->nullable()->after('date_validation'));
        }
        if (! Schema::hasColumn('fichiers_dossiers_etudiants', 'motif_rejet')) {
            Schema::table('fichiers_dossiers_etudiants', fn (Blueprint $table) =>
                $table->text('motif_rejet')->nullable()->after('date_expiration'));
        }
        if (! Schema::hasColumn('fichiers_dossiers_etudiants', 'valide_par')) {
            Schema::table('fichiers_dossiers_etudiants', fn (Blueprint $table) =>
                $table->foreignId('valide_par')->nullable()->after('motif_rejet')->constrained('users')->nullOnDelete());
        }
        if (! Schema::hasIndex('fichiers_dossiers_etudiants', 'fichiers_dossier_statut_idx')) {
            Schema::table('fichiers_dossiers_etudiants', fn (Blueprint $table) =>
                $table->index(['id_dossier_etudiant', 'statut_validation'], 'fichiers_dossier_statut_idx'));
        }

        if (! Schema::hasColumn('inscriptions', 'id_annee_academique')) {
            Schema::table('inscriptions', fn (Blueprint $table) =>
                $table->foreignId('id_annee_academique')->nullable()->after('id_promotion')
                    ->constrained('annees_academiques')->restrictOnDelete());
        }
        if (! Schema::hasIndex('inscriptions', 'inscriptions_etudiant_annee_unique')) {
            Schema::table('inscriptions', fn (Blueprint $table) =>
                $table->unique(['id_etudiant', 'id_annee_academique'], 'inscriptions_etudiant_annee_unique'));
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('inscriptions', 'inscriptions_etudiant_annee_unique')) {
            Schema::table('inscriptions', fn (Blueprint $table) =>
                $table->dropUnique('inscriptions_etudiant_annee_unique'));
        }
        if (Schema::hasColumn('inscriptions', 'id_annee_academique')) {
            Schema::table('inscriptions', fn (Blueprint $table) =>
                $table->dropConstrainedForeignId('id_annee_academique'));
        }

        if (Schema::hasIndex('fichiers_dossiers_etudiants', 'fichiers_dossier_statut_idx')) {
            Schema::table('fichiers_dossiers_etudiants', fn (Blueprint $table) =>
                $table->dropIndex('fichiers_dossier_statut_idx'));
        }
        if (Schema::hasColumn('fichiers_dossiers_etudiants', 'valide_par')) {
            Schema::table('fichiers_dossiers_etudiants', fn (Blueprint $table) =>
                $table->dropConstrainedForeignId('valide_par'));
        }
        foreach (['statut_validation', 'date_validation', 'date_expiration', 'motif_rejet'] as $colonne) {
            if (Schema::hasColumn('fichiers_dossiers_etudiants', $colonne)) {
                Schema::table('fichiers_dossiers_etudiants', fn (Blueprint $table) => $table->dropColumn($colonne));
            }
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('promotions', 'code')) {
            Schema::table('promotions', fn (Blueprint $table) => $table->string('code', 30)->nullable()->unique());
        }
        if (! Schema::hasColumn('promotions', 'id_annee_academique')) {
            Schema::table('promotions', fn (Blueprint $table) => $table->foreignId('id_annee_academique')->nullable()->constrained('annees_academiques')->restrictOnDelete());
        }
        if (! Schema::hasColumn('promotions', 'capacite')) {
            Schema::table('promotions', fn (Blueprint $table) => $table->unsignedSmallInteger('capacite')->nullable());
        }
        if (! Schema::hasColumn('promotions', 'date_ouverture')) {
            Schema::table('promotions', fn (Blueprint $table) => $table->date('date_ouverture')->nullable());
        }
        if (! Schema::hasColumn('promotions', 'date_cloture')) {
            Schema::table('promotions', fn (Blueprint $table) => $table->date('date_cloture')->nullable());
        }

        foreach (['user_id', 'created_by', 'updated_by', 'deleted_by'] as $colonne) {
            if (! Schema::hasColumn('promotions', $colonne)) {
                Schema::table('promotions', fn (Blueprint $table) => $table->foreignId($colonne)->nullable()->constrained('users')->nullOnDelete());
            }
        }
        if (! Schema::hasColumn('promotions', 'deleted_at')) {
            Schema::table('promotions', fn (Blueprint $table) => $table->softDeletes());
        }

        if (Schema::hasColumn('promotions', 'rang')) {
            Schema::table('promotions', function (Blueprint $table) {
                $table->dropUnique(['rang']);
                $table->dropColumn('rang');
            });
        }
        if (Schema::hasColumn('promotions', 'annee_entree')) {
            Schema::table('promotions', function (Blueprint $table) {
                $table->dropUnique(['annee_entree']);
                $table->dropColumn('annee_entree');
            });
        }

        if (! Schema::hasTable('inscriptions')) {
            Schema::create('inscriptions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('id_etudiant')->constrained('etudiants')->restrictOnDelete();
                $table->foreignId('id_promotion')->constrained('promotions')->restrictOnDelete();
                $table->date('date_inscription');
                $table->string('statut', 40)->default('En formation');
                $table->string('decision_passage', 50)->nullable();
                $table->dateTime('date_decision')->nullable();
                $table->text('observations')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->unique(['id_etudiant', 'id_promotion']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('inscriptions');
    }
};

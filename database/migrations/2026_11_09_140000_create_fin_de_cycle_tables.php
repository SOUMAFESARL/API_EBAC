<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fin de cycle (3e Année, bulletin de fin de cycle) — 09/11/2026.
 *
 * Le référentiel des épreuves et leurs notes, par étudiant et par
 * promotion. La moyenne de fin de cycle est la moyenne pondérée des
 * épreuves actives ; elle n'est pas stockée.
 *
 * Une épreuve ne se supprime pas : elle se retire (`actif = false`).
 * Une note garde qui l'a saisie (`saisi_par`).
 */
return new class extends Migration
{
    /** Les cinq lignes du bulletin de fin de cycle, coefficient 1. */
    private const SOCLE = [
        ['rapport-stage', 'Rapport de stage'],
        ['predication', 'Prédication'],
        ['dissertation', 'Dissertation de fin de cycle'],
        ['connaissance-biblique', 'Connaissance biblique générale'],
        ['moyenne-annuelle', 'Moyenne générale annuelle'],
    ];

    public function up(): void
    {
        Schema::create('epreuves_fin_de_cycle', function (Blueprint $table) {
            $table->id();
            $table->string('code', 60)->unique();
            $table->string('libelle', 120);
            $table->unsignedTinyInteger('coefficient')->default(1);
            $table->unsignedSmallInteger('rang');
            $table->boolean('actif')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('notes_fin_de_cycle', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_epreuve')->constrained('epreuves_fin_de_cycle')->restrictOnDelete();
            $table->foreignId('id_etudiant')->constrained('etudiants')->restrictOnDelete();
            $table->foreignId('id_promotion')->constrained('promotions')->restrictOnDelete();
            $table->decimal('note', 4, 2);
            $table->foreignId('saisi_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['id_epreuve', 'id_etudiant', 'id_promotion'], 'notes_fin_de_cycle_unique');
        });

        $maintenant = now();
        foreach (self::SOCLE as $rang => [$code, $libelle]) {
            DB::table('epreuves_fin_de_cycle')->insert([
                'code' => $code,
                'libelle' => $libelle,
                'coefficient' => 1,
                'rang' => $rang + 1,
                'actif' => true,
                'created_at' => $maintenant,
                'updated_at' => $maintenant,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('notes_fin_de_cycle');
        Schema::dropIfExists('epreuves_fin_de_cycle');
    }
};

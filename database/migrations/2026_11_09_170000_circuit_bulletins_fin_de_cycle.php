<?php

use Database\Seeders\CatalogueDroitsSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Circuit des bulletins de fin de cycle et registre des bulletins
 * (09/11/2026).
 *
 * - `bulletins.id_niveau` : le niveau **du bulletin**, posé à sa création.
 *   Le niveau d'une promotion avance chaque année : sans lui, un bulletin
 *   de 1re Année passerait en 3e Année avec sa promotion. Les bulletins
 *   déjà émis reprennent le niveau actuel de leur promotion.
 * - `bulletins.valide_par/valide_le/publie_par` : qui a validé, quand, qui
 *   a publié (la date de publication est déjà portée).
 * - `lignes_bulletins_fin_de_cycle` : les notes d'épreuve figées à la
 *   validation — le bulletin ne bouge plus si le référentiel change.
 * - `historique_bulletins_fin_de_cycle` : chaque validation, renvoi en
 *   saisie et publication, avec son auteur. Un renvoi supprime le
 *   bulletin validé ; l'historique, lui, reste.
 * - Les droits `notes.bulletins.*` (ADMIN seul les détient d'office).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bulletins', function (Blueprint $table) {
            $table->foreignId('id_niveau')->nullable()->after('id_inscription')->constrained('niveaux')->nullOnDelete();
            $table->foreignId('valide_par')->nullable()->after('statut')->constrained('users')->nullOnDelete();
            $table->dateTime('valide_le')->nullable()->after('valide_par');
            $table->foreignId('publie_par')->nullable()->after('date_publication')->constrained('users')->nullOnDelete();
            $table->index(['id_niveau', 'periode']);
        });

        DB::table('bulletins')->update(['id_niveau' => DB::raw(
            '(SELECT p.id_niveau FROM inscriptions i JOIN promotions p ON p.id = i.id_promotion WHERE i.id = bulletins.id_inscription)'
        )]);

        Schema::create('lignes_bulletins_fin_de_cycle', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_bulletin')->constrained('bulletins')->cascadeOnDelete();
            $table->foreignId('id_epreuve')->nullable()->constrained('epreuves_fin_de_cycle')->nullOnDelete();
            $table->string('libelle', 120);
            $table->unsignedTinyInteger('coefficient');
            $table->decimal('note', 4, 2);
            $table->unsignedSmallInteger('rang');
            $table->foreignId('saisi_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['id_bulletin', 'id_epreuve']);
        });

        Schema::create('historique_bulletins_fin_de_cycle', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_etudiant')->constrained('etudiants')->restrictOnDelete();
            $table->foreignId('id_promotion')->constrained('promotions')->restrictOnDelete();
            $table->string('action', 20);
            $table->decimal('moyenne', 5, 2)->nullable();
            $table->foreignId('par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->index(['id_promotion', 'id_etudiant']);
        });

        (new CatalogueDroitsSeeder)->run();
    }

    public function down(): void
    {
        Schema::dropIfExists('historique_bulletins_fin_de_cycle');
        Schema::dropIfExists('lignes_bulletins_fin_de_cycle');
        Schema::table('bulletins', function (Blueprint $table) {
            $table->dropIndex(['id_niveau', 'periode']);
            $table->dropConstrainedForeignId('publie_par');
            $table->dropColumn('valide_le');
            $table->dropConstrainedForeignId('valide_par');
            $table->dropConstrainedForeignId('id_niveau');
        });
    }
};

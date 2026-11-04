<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Droits par module · sous-module · action (demande front §69).
 *
 * Le code s'élargit à 80 caractères — `planification.calendrier-academique.activer-annee`
 * en compte 49 — et le rangement est servi en colonnes, pour que le front
 * n'ait pas à le déduire du code. Les trois colonnes restent facultatives :
 * les permissions existantes (`COMPTE_GERER`…) n'en ont pas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('permissions', function (Blueprint $table) {
            $table->string('code', 80)->change();
        });

        Schema::table('permissions', function (Blueprint $table) {
            $table->string('module', 60)->nullable()->after('description');
            $table->string('sous_module', 60)->nullable()->after('module');
            $table->string('action', 40)->nullable()->after('sous_module');
            $table->index(['module', 'sous_module'], 'permissions_module_sous_module_index');
        });
    }

    public function down(): void
    {
        Schema::table('permissions', function (Blueprint $table) {
            $table->dropIndex('permissions_module_sous_module_index');
            $table->dropColumn(['module', 'sous_module', 'action']);
        });
    }
};

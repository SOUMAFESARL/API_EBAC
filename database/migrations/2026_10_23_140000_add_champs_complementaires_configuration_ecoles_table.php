<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuration_ecoles', function (Blueprint $table) {
            $table->string('sigle', 50)->nullable();
            foreach (['nom_plateforme', 'arrete', 'boite_postale', 'directeur_nom', 'directeur_prenoms',
                'directeur_academique_nom', 'directeur_academique_prenoms', 'signature_directeur_academique'] as $champ) {
                $table->string($champ)->nullable();
            }
            $table->string('site_web', 500)->nullable();
            $table->foreignId('directeur_civilite_id')->nullable()->constrained('civilite')->nullOnDelete();
            $table->foreignId('directeur_academique_civilite_id')->nullable()->constrained('civilite')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('configuration_ecoles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('directeur_civilite_id');
            $table->dropConstrainedForeignId('directeur_academique_civilite_id');
            $table->dropColumn(['sigle', 'nom_plateforme', 'arrete', 'boite_postale', 'site_web',
                'directeur_nom', 'directeur_prenoms', 'directeur_academique_nom',
                'directeur_academique_prenoms', 'signature_directeur_academique']);
        });
    }
};

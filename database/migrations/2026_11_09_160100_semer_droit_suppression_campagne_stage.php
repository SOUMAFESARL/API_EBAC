<?php

use Database\Seeders\CatalogueDroitsSeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * Sème `gestion-academique.stages.supprimer` (09/11/2026) : une campagne
 * de stage se supprime tant qu'aucune affectation n'est validée. Le
 * seeder est idempotent et ne touche pas aux droits déjà cochés.
 */
return new class extends Migration
{
    public function up(): void
    {
        (new CatalogueDroitsSeeder)->run();
    }

    public function down(): void
    {
        // Rien : retirer un droit déjà attribué effacerait un paramétrage.
    }
};

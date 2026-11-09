<?php

use Database\Seeders\CatalogueDroitsSeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * Sème le droit `ressources.bibliotheque.consulter` (Bibliothèque
 * pédagogique des espaces de gestion), ajouté au catalogue le 09/11/2026.
 * Le seeder est idempotent : il n'ajoute que ce qui manque et ne touche
 * pas aux droits déjà cochés des rôles.
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

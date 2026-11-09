<?php

use Database\Seeders\CatalogueDroitsSeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * Sème les droits `notes.fin-de-cycle-epreuves.*` et
 * `notes.fin-de-cycle-notes.*` (09/11/2026), et reprend au passage le
 * libellé « Gestion de stage » de `gestion-academique.stages.*`.
 * Le seeder est idempotent et ne touche pas aux droits déjà cochés.
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

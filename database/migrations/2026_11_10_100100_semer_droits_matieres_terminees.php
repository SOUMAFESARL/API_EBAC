<?php

use Database\Seeders\CatalogueDroitsSeeder;
use Illuminate\Database\Migrations\Migration;

// Sème `progression.matieres-terminees.*` ; les rôles déjà réglés les reçoivent depuis « Rôles & droits ».
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

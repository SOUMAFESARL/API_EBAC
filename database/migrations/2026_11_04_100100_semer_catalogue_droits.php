<?php

use Database\Seeders\CatalogueDroitsSeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * Sème le catalogue des droits au déploiement : la production ne lance que
 * `php artisan migrate`, jamais `db:seed`. Le seeder est idempotent — il
 * peut repasser sans rien dupliquer ni toucher aux droits des rôles.
 */
return new class extends Migration
{
    public function up(): void
    {
        (new CatalogueDroitsSeeder)->run();
    }

    public function down(): void
    {
        // Rien : retirer des droits déjà attribués à des rôles effacerait
        // un paramétrage. La colonne de rangement part avec la migration
        // précédente.
    }
};

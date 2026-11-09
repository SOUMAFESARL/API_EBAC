<?php

use Database\Seeders\CatalogueDroitsSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Réparation de la bibliothèque en production (09/11/2026).
 *
 * 1. Si l'ancienne version de 2026_11_09_090000 est passée (table
 *    `versions_supports`, fichier hors de `supports_pedagogiques`), le
 *    support garde le fichier de sa version la plus récente et les
 *    versions disparaissent : un support = un fichier.
 * 2. Le catalogue des droits est resemé avec la configuration à jour —
 *    `semer_droit_bibliotheque` a pu tourner sur une config en cache sans
 *    les droits `ressources.bibliotheque.*`.
 *
 * Idempotente : sans effet sur une base déjà au bon schéma, et le seeder
 * n'ajoute que ce qui manque.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->retirerLesVersions();

        // La config en cache est celle d'avant le déploiement : on relit les fichiers.
        Artisan::call('config:clear');
        app('config')->set('droits', require config_path('droits.php'));

        (new CatalogueDroitsSeeder)->run();
    }

    public function down(): void
    {
        // Rien : les versions supprimées ne se reconstituent pas.
    }

    private function retirerLesVersions(): void
    {
        if (! Schema::hasTable('supports_pedagogiques') || Schema::hasColumn('supports_pedagogiques', 'chemin')) {
            return;
        }

        Schema::table('supports_pedagogiques', function (Blueprint $table) {
            $table->string('chemin')->default('');
            $table->string('nom_fichier')->default('');
            $table->string('format', 10)->default('');
            $table->unsignedBigInteger('taille_octets')->default(0);
        });

        if (Schema::hasTable('versions_supports')) {
            $versions = DB::table('versions_supports')->orderByDesc('numero')->get()->groupBy('id_support');
            $gardes = [];

            foreach ($versions as $idSupport => $liste) {
                $courante = $liste->first();
                $gardes[] = $courante->chemin;
                DB::table('supports_pedagogiques')->where('id', $idSupport)->update([
                    'chemin' => $courante->chemin,
                    'nom_fichier' => $courante->nom_fichier,
                    'format' => $courante->format,
                    'taille_octets' => $courante->taille_octets,
                ]);
            }

            // Fichiers des anciennes versions (une republication partageait le chemin).
            foreach ($versions->flatten(1)->pluck('chemin')->unique() as $chemin) {
                if (! in_array($chemin, $gardes, true)) {
                    Storage::disk('local')->delete($chemin);
                }
            }

            Schema::drop('versions_supports');
        }

        // Un support sans fichier ne peut ni se télécharger ni se lire.
        DB::table('supports_pedagogiques')->where('chemin', '')->delete();
    }
};

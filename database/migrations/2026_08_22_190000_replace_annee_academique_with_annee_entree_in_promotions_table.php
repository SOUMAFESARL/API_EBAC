<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('promotions', 'annee_entree')) {
            Schema::table('promotions', function (Blueprint $table) {
                $table->unsignedSmallInteger('annee_entree')->nullable()->after('num_promotion');
            });
        }

        DB::table('promotions')
            ->leftJoin('annees_academiques', 'promotions.id_annee_academique', '=', 'annees_academiques.id')
            ->select('promotions.id', 'annees_academiques.date_debut', 'annees_academiques.libelle')
            ->orderBy('promotions.id')
            ->get()
            ->each(function (object $promotion): void {
                $annee = $promotion->date_debut
                    ? (int) substr((string) $promotion->date_debut, 0, 4)
                    : (int) substr((string) $promotion->libelle, 0, 4);

                DB::table('promotions')->where('id', $promotion->id)->whereNull('annee_entree')->update([
                    'annee_entree' => $annee >= 1900 ? $annee : (int) date('Y'),
                ]);
            });

        if (Schema::hasColumn('promotions', 'id_annee_academique')) {
            if (DB::getDriverName() === 'mysql') {
                $contraintes = DB::select(<<<'SQL'
                    SELECT CONSTRAINT_NAME AS nom
                    FROM information_schema.KEY_COLUMN_USAGE
                    WHERE TABLE_SCHEMA = DATABASE()
                      AND TABLE_NAME = 'promotions'
                      AND COLUMN_NAME = 'id_annee_academique'
                      AND REFERENCED_TABLE_NAME IS NOT NULL
                SQL);

                foreach ($contraintes as $contrainte) {
                    $nom = str_replace('`', '``', $contrainte->nom);
                    DB::statement("ALTER TABLE `promotions` DROP FOREIGN KEY `{$nom}`");
                }

                Schema::table('promotions', function (Blueprint $table) {
                    $table->dropColumn('id_annee_academique');
                });
            } else {
                Schema::table('promotions', function (Blueprint $table) {
                    $table->dropConstrainedForeignId('id_annee_academique');
                });
            }
        }

        Schema::table('promotions', function (Blueprint $table) {
            $table->unsignedSmallInteger('annee_entree')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            $table->foreignId('id_annee_academique')->nullable()->after('num_promotion')
                ->constrained('annees_academiques');
            $table->dropColumn('annee_entree');
        });
    }
};

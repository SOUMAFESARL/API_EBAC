<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Uniqueness depends on deletion, not on the academic year's active status.
        Schema::table('annees_academiques', function (Blueprint $table) {
            $table->unsignedTinyInteger('unicite_active')->nullable()
                ->virtualAs('CASE WHEN deleted_at IS NULL THEN 1 ELSE NULL END');
            $table->unique(['libelle', 'unicite_active'], 'annees_academiques_libelle_actif_unique');
        });

        Schema::table('annees_academiques', function (Blueprint $table) {
            $table->dropUnique('annees_academiques_libelle_unique');
        });
    }

    public function down(): void
    {
        if (DB::table('annees_academiques')->groupBy('libelle')->havingRaw('COUNT(*) > 1')->exists()) {
            throw new RuntimeException('Rollback impossible : des annees academiques partagent un libelle reutilise. Conserver leur historique avant de retablir la contrainte.');
        }

        Schema::table('annees_academiques', function (Blueprint $table) {
            $table->unique('libelle');
            $table->dropUnique('annees_academiques_libelle_actif_unique');
        });

        Schema::table('annees_academiques', fn (Blueprint $table) => $table->dropColumn('unicite_active'));
    }
};

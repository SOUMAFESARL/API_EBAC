<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // NULL permits multiple historical rows; 1 enforces uniqueness on live rows,
        // including those whose business status is Archive.
        Schema::table('niveaux', function (Blueprint $table) {
            $table->unsignedTinyInteger('unicite_active')->nullable()
                ->virtualAs('CASE WHEN deleted_at IS NULL THEN 1 ELSE NULL END');
            $table->unique(['code', 'unicite_active'], 'niveaux_code_actif_unique');
            $table->unique(['rang', 'unicite_active'], 'niveaux_rang_actif_unique');
        });

        Schema::table('niveaux', function (Blueprint $table) {
            $table->dropUnique('niveaux_code_unique');
            $table->dropUnique('niveaux_rang_unique');
        });
    }

    public function down(): void
    {
        foreach (['code', 'rang'] as $column) {
            if (DB::table('niveaux')->groupBy($column)->havingRaw('COUNT(*) > 1')->exists()) {
                throw new RuntimeException('Rollback impossible : des niveaux partagent un code ou un rang réutilisé. Conserver leur historique avant de rétablir les anciennes contraintes.');
            }
        }

        Schema::table('niveaux', function (Blueprint $table) {
            $table->unique('code');
            $table->unique('rang');
            $table->dropUnique('niveaux_code_actif_unique');
            $table->dropUnique('niveaux_rang_actif_unique');
        });

        Schema::table('niveaux', fn (Blueprint $table) => $table->dropColumn('unicite_active'));
    }
};

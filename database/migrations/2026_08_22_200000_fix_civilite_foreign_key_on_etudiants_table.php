<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql' || ! Schema::hasColumn('etudiants', 'civilite_id')) {
            return;
        }

        $contraintes = DB::select(<<<'SQL'
            SELECT CONSTRAINT_NAME AS nom, REFERENCED_TABLE_NAME AS table_reference
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'etudiants'
              AND COLUMN_NAME = 'civilite_id'
              AND REFERENCED_TABLE_NAME IS NOT NULL
        SQL);

        $relationCorrecteExiste = false;

        foreach ($contraintes as $contrainte) {
            if ($contrainte->table_reference === 'civilite') {
                $relationCorrecteExiste = true;
                continue;
            }

            $nom = str_replace('`', '``', $contrainte->nom);
            DB::statement("ALTER TABLE `etudiants` DROP FOREIGN KEY `{$nom}`");
        }

        if (! $relationCorrecteExiste) {
            DB::table('etudiants')
                ->whereNotNull('civilite_id')
                ->whereNotExists(function ($query) {
                    $query->selectRaw('1')
                        ->from('civilite')
                        ->whereColumn('civilite.id', 'etudiants.civilite_id');
                })
                ->update(['civilite_id' => null]);

            Schema::table('etudiants', function (Blueprint $table) {
                $table->foreign('civilite_id')
                    ->references('id')
                    ->on('civilite')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        // La relation historique vers `civilites` était invalide et ne doit pas être restaurée.
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Un support se range sous une matière ; module et cours sont facultatifs
 * (09/11/2026). Les supports existants, rattachés à un cours, reçoivent
 * le module et la matière de ce cours.
 *
 * Idempotente : sans effet si les colonnes existent déjà.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('supports_pedagogiques') || Schema::hasColumn('supports_pedagogiques', 'id_matiere')) {
            return;
        }

        Schema::table('supports_pedagogiques', function (Blueprint $table) {
            $table->foreignId('id_matiere')->nullable()->after('id')->constrained('matieres');
            $table->foreignId('id_module')->nullable()->after('id_matiere')->constrained('modules');
        });

        foreach (DB::table('supports_pedagogiques')->whereNotNull('id_cours')->get(['id', 'id_cours']) as $support) {
            $module = DB::table('cours')->join('modules', 'modules.id', '=', 'cours.id_module')
                ->where('cours.id', $support->id_cours)->first(['modules.id as id_module', 'modules.id_matiere']);
            if ($module) {
                DB::table('supports_pedagogiques')->where('id', $support->id)
                    ->update(['id_module' => $module->id_module, 'id_matiere' => $module->id_matiere]);
            }
        }

        Schema::table('supports_pedagogiques', function (Blueprint $table) {
            $table->unsignedBigInteger('id_cours')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('supports_pedagogiques', 'id_matiere')) {
            return;
        }

        DB::table('supports_pedagogiques')->whereNull('id_cours')->delete();
        Schema::table('supports_pedagogiques', function (Blueprint $table) {
            $table->dropConstrainedForeignId('id_module');
            $table->dropConstrainedForeignId('id_matiere');
        });
    }
};

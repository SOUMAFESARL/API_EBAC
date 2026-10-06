<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('feuilles_notes', function (Blueprint $table) {
            // Une référence historique reste conservée même si le compte est supprimé.
            $table->unsignedBigInteger('transmise_par')->nullable()->index();
        });

        DB::table('feuilles_notes')->whereNotNull('date_transmission')->orderBy('id')->chunkById(200, function ($feuilles) {
            foreach ($feuilles as $feuille) {
                $acteur = DB::table('historique_feuilles_notes')->where('id_feuille_notes', $feuille->id)
                    ->where('action', 'transmission_secretariat')->orderByDesc('id')->value('id_acteur');
                if (! $acteur) {
                    $acteur = DB::table('users')->join('roles', 'roles.id', '=', 'users.id_role')
                        ->where('users.id', $feuille->updated_by)->where('roles.code', 'ENSEIGNANT')->value('users.id');
                }
                if ($acteur) {
                    DB::table('feuilles_notes')->where('id', $feuille->id)->update(['transmise_par' => $acteur]);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('feuilles_notes', function (Blueprint $table) {
            $table->dropIndex(['transmise_par']);
            $table->dropColumn('transmise_par');
        });
    }
};

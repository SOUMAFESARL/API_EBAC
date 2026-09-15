<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('affectations_enseignants', function (Blueprint $table) {
            $table->dropForeign(['id_annee_academique']);
            $table->dropIndex('affectations_annee_dates_index');
        });
        Schema::table('affectations_enseignants', function (Blueprint $table) {
            // Conserver les anciennes valeurs pour l'historique, sans dépendance métier.
            $table->unsignedBigInteger('id_annee_academique')->nullable()->change();
            $table->index(['date_debut', 'date_fin'], 'affectations_dates_index');
        });
        $today = now()->toDateString();
        DB::table('matieres')->whereNull('deleted_at')->whereNotNull('enseignant_id')
            ->whereNotExists(function ($query) use ($today) {
                $query->selectRaw('1')->from('affectations_enseignants')
                    ->whereColumn('affectations_enseignants.id_matiere', 'matieres.id')
                    ->where(fn ($q) => $q->whereNull('date_fin')->orWhere('date_fin', '>', $today));
            })->orderBy('id')->chunkById(100, function ($matieres) use ($today) {
                foreach ($matieres as $matiere) {
                    DB::table('affectations_enseignants')->insert([
                        'id_matiere' => $matiere->id, 'enseignant_id' => $matiere->enseignant_id,
                        'portee' => 'matiere', 'date_debut' => $today,
                        'created_by' => $matiere->updated_by ?? $matiere->created_by,
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('affectations_enseignants', function (Blueprint $table) {
            $table->dropIndex('affectations_dates_index');
            $table->foreign('id_annee_academique')->references('id')->on('annees_academiques')->restrictOnDelete();
            $table->index(['id_annee_academique', 'date_debut', 'date_fin'], 'affectations_annee_dates_index');
        });
    }
};

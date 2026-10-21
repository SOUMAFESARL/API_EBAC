<?php

namespace App\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class NotesValideesService
{
    public function notes(int $promotion, int $annee): Builder
    {
        return DB::table('notes_cours as n')->join('feuilles_notes as f', 'f.id', '=', 'n.id_feuille_notes')
            ->leftJoin('cours as c', 'c.id', '=', 'f.id_cours')
            ->leftJoin('modules as m', 'm.id', '=', 'c.id_module')
            ->where('f.statut', 'validee_direction')->where('f.id_promotion', $promotion)
            ->where('f.id_annee_academique', $annee)->whereNotNull('n.note');
    }

    public function moyennes(int $promotion, int $annee): Builder
    {
        return $this->notes($promotion, $annee)
            ->selectRaw('COALESCE(m.id_matiere, f.id_matiere) as id_matiere, n.id_etudiant')
            ->selectRaw('SUM(n.note * CASE WHEN f.id_cours IS NULL THEN 1 ELSE c.coefficient END) / NULLIF(SUM(CASE WHEN f.id_cours IS NULL THEN 1 ELSE c.coefficient END), 0) as moyenne')
            ->selectRaw('COUNT(*) as nombre_notes')
            ->groupByRaw('COALESCE(m.id_matiere, f.id_matiere), n.id_etudiant');
    }
}

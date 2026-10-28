<?php

namespace App\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class NotesValideesService
{
    public function notes(int $promotion, int $annee, array $statuts = ['validee_direction']): Builder
    {
        return DB::table('notes_cours as n')->join('feuilles_notes as f', 'f.id', '=', 'n.id_feuille_notes')
            ->leftJoin('cours as c', 'c.id', '=', 'f.id_cours')
            ->leftJoin('modules as m', 'm.id', '=', 'c.id_module')
            ->whereIn('f.statut', $statuts)->where('f.id_promotion', $promotion)
            ->where('f.id_annee_academique', $annee)->whereNotNull('n.note');
    }

    public function moyennes(int $promotion, int $annee, array $statuts = ['validee_direction']): Builder
    {
        return $this->notes($promotion, $annee, $statuts)
            ->selectRaw('COALESCE(m.id_matiere, f.id_matiere) as id_matiere, n.id_etudiant')
            ->selectRaw('AVG(n.note) as moyenne')
            ->selectRaw('COUNT(*) as nombre_notes')
            ->groupByRaw('COALESCE(m.id_matiere, f.id_matiere), n.id_etudiant');
    }
}

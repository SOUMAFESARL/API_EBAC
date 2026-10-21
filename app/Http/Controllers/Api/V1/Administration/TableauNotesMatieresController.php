<?php

namespace App\Http\Controllers\Api\V1\Administration;

use App\Http\Controllers\Controller;
use App\Models\{AnneeAcademique, Etudiant, Matiere, Promotion};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TableauNotesMatieresController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'id_promotion' => ['required', 'integer', 'exists:promotions,id'],
            'id_annee_academique' => ['required', 'integer', 'exists:annees_academiques,id'],
            'id_matiere' => ['sometimes', 'integer', 'exists:matieres,id'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
        ]);
        $notes = app(\App\Services\NotesValideesService::class)->moyennes($data['id_promotion'], $data['id_annee_academique']);
        $matieres = Matiere::query()->whereIn('id', DB::query()->fromSub(clone $notes, 'notes_validees')->select('id_matiere'))
            ->when(isset($data['id_matiere']), fn ($q) => $q->whereKey($data['id_matiere']))
            ->orderBy('libelle')->orderBy('id')->paginate($data['per_page'] ?? 20);
        $moyennes = DB::query()->fromSub($notes, 'notes_validees')->whereIn('id_matiere', $matieres->getCollection()->pluck('id'))
            ->get()->groupBy('id_matiere');
        $etudiants = Etudiant::whereHas('inscriptions', fn ($q) => $q->where('id_promotion', $data['id_promotion'])
            ->where('id_annee_academique', $data['id_annee_academique']))
            ->orderBy('nom')->orderBy('prenoms')->orderBy('id')->get(['id', 'nom', 'prenoms', 'matricule']);

        return response()->json([
            'promotion' => Promotion::findOrFail($data['id_promotion'])->only(['id', 'code', 'num_promotion']),
            'annee_academique' => AnneeAcademique::findOrFail($data['id_annee_academique'])->only(['id', 'libelle']),
            'matieres' => $matieres->getCollection()->map(function ($matiere) use ($moyennes, $etudiants) {
                $parEtudiant = ($moyennes->get($matiere->id) ?? collect())->keyBy('id_etudiant');
                $seuil = (float) ($matiere->note_validation ?? 10);

                return [
                    'id' => $matiere->id, 'code' => $matiere->code, 'libelle' => $matiere->libelle,
                    'note_validation' => $seuil, 'effectif' => $etudiants->count(),
                    'etudiants' => $etudiants->map(function ($etudiant, $index) use ($parEtudiant, $seuil) {
                        $resultat = $parEtudiant->get($etudiant->id);
                        $moyenne = $resultat?->moyenne;
                        $decision = $moyenne === null ? 'en_attente' : ((float) $moyenne >= $seuil ? 'valide' : 'ajourne');

                        return [
                            'numero' => $index + 1, ...$etudiant->only(['id', 'nom', 'prenoms', 'matricule']),
                            'note' => $moyenne === null ? null : round((float) $moyenne, 2),
                            'nombre_notes_validees' => (int) ($resultat?->nombre_notes ?? 0),
                            'decision' => $decision,
                            'decision_libelle' => match ($decision) { 'valide' => 'Validé', 'ajourne' => 'Ajourné', default => 'En attente' },
                        ];
                    })->values(),
                ];
            })->values(),
            'meta' => ['total' => $matieres->total(), 'current_page' => $matieres->currentPage(),
                'last_page' => $matieres->lastPage(), 'per_page' => $matieres->perPage(), 'from' => $matieres->firstItem(), 'to' => $matieres->lastItem()],
        ]);
    }
}

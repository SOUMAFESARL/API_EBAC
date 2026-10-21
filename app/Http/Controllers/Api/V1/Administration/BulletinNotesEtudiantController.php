<?php

namespace App\Http\Controllers\Api\V1\Administration;

use App\Http\Controllers\Controller;
use App\Models\{Etudiant, Inscription, Matiere};
use App\Services\NotesValideesService;
use Illuminate\Http\Request;

class BulletinNotesEtudiantController extends Controller
{
    public function show(Request $request, int $id, NotesValideesService $service)
    {
        $data = $request->validate([
            'id_promotion' => ['required', 'integer', 'exists:promotions,id'],
            'id_annee_academique' => ['required', 'integer', 'exists:annees_academiques,id'],
        ]);
        $etudiant = Etudiant::findOrFail($id);
        $inscription = Inscription::with(['promotion.niveau', 'anneeAcademique'])
            ->where('id_etudiant', $id)->where('id_promotion', $data['id_promotion'])
            ->where('id_annee_academique', $data['id_annee_academique'])->firstOrFail();
        $moyennes = $service->moyennes($data['id_promotion'], $data['id_annee_academique'])
            ->where('n.id_etudiant', $id)->get()->keyBy('id_matiere');
        $enseignants = $service->notes($data['id_promotion'], $data['id_annee_academique'])
            ->where('n.id_etudiant', $id)->leftJoin('seances_cahier_texte as s', 's.id', '=', 'f.id_seance')
            ->leftJoin('users as u', fn ($join) => $join->whereRaw('u.id = COALESCE(f.transmise_par, s.enseignant_id)'))
            ->selectRaw('COALESCE(m.id_matiere, f.id_matiere) as id_matiere, u.id, u.nom, u.prenoms')
            ->distinct()->get()->groupBy('id_matiere');
        $matieres = Matiere::whereIn('id', $moyennes->keys())->orderBy('libelle')->orderBy('id')->get();
        $somme = 0.0;
        $coefficients = 0.0;
        $validees = 0;
        $lignes = $matieres->map(function ($matiere, $index) use ($moyennes, $enseignants, $inscription, &$somme, &$coefficients, &$validees) {
            $resultat = $moyennes->get($matiere->id);
            $note = $resultat->moyenne === null ? null : (float) $resultat->moyenne;
            $coefficient = (float) $matiere->coefficient;
            $seuil = (float) ($matiere->note_validation ?? 10);
            $validee = $note !== null && $note >= $seuil;
            if ($note !== null && $coefficient > 0) {
                $somme += $note * $coefficient;
                $coefficients += $coefficient;
            }
            $validees += $validee ? 1 : 0;

            return [
                'numero' => $index + 1, 'matiere' => $matiere->only(['id', 'code', 'libelle']),
                'coefficient' => $coefficient, 'note' => $note === null ? null : round($note, 2),
                'note_validation' => $seuil, 'nombre_notes_validees' => (int) $resultat->nombre_notes,
                'decision' => $note === null ? 'en_attente' : ($validee ? 'valide' : 'ajourne'),
                'decision_libelle' => $note === null ? 'En attente' : ($validee ? 'Validé' : 'Ajourné'),
                'enseignants' => ($enseignants->get($matiere->id) ?? collect())->filter(fn ($u) => $u->id !== null)
                    ->map(fn ($u) => ['id' => $u->id, 'nom' => $u->nom, 'prenoms' => $u->prenoms])->values(),
                'annee' => $inscription->anneeAcademique->libelle,
            ];
        });

        return response()->json(['bulletin' => [
            'id_inscription' => $inscription->id,
            'etudiant' => $etudiant->only(['id', 'matricule', 'nom', 'prenoms', 'photo_identite_url']),
            'promotion' => $inscription->promotion?->only(['id', 'code', 'num_promotion']),
            'niveau' => $inscription->promotion?->niveau?->only(['id', 'code', 'libelle']),
            'annee_academique' => $inscription->anneeAcademique->only(['id', 'libelle']),
            'matieres' => $lignes->values(),
            'moyenne_generale' => $coefficients > 0 ? round($somme / $coefficients, 2) : null,
            'total_coefficients' => $coefficients,
            'nombre_matieres' => $lignes->count(), 'matieres_validees' => $validees,
            'credits_valides' => round($lignes->where('decision', 'valide')->sum('coefficient'), 2),
            'regle_credits' => 'somme_coefficients_matieres_validees',
        ]]);
    }
}

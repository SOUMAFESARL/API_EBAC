<?php

namespace App\Http\Controllers\Api\V1\Administration;

use App\Http\Controllers\Controller;
use App\Models\Presence;
use App\Models\Promotion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AutorisationEvaluationController extends Controller
{
    private function absences()
    {
        return Presence::where('statut', 'absent')->whereHas('feuille', fn ($q) => $q
            ->where('statut', 'validee')->whereHas('seance', fn ($s) => $s->where('statut', 'realisee')));
    }

    public function index(Request $request)
    {
        $data = $request->validate([
            'id_etudiant' => ['sometimes', 'integer', 'exists:etudiants,id'],
            'id_seance' => ['sometimes', 'integer', 'exists:seances_cahier_texte,id'],
            'autorisee' => ['sometimes', 'boolean'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
        ]);
        $query = $this->absences()->with(['etudiant', 'feuille.seance']);
        if (isset($data['id_etudiant'])) {
            $query->where('id_etudiant', $data['id_etudiant']);
        }
        if (isset($data['id_seance'])) {
            $query->whereHas('feuille', fn ($q) => $q->where('id_seance', $data['id_seance']));
        }
        if (isset($data['autorisee'])) {
            $data['autorisee'] ? $query->whereNotNull('evaluation_autorisee_le') : $query->whereNull('evaluation_autorisee_le');
        }

        return response()->json($query->latest('id')->paginate($data['per_page'] ?? 20));
    }

    public function show(int $id)
    {
        return response()->json(['absence' => $this->absences()->with(['etudiant', 'feuille.seance'])->findOrFail($id)]);
    }

    public function autoriser(Request $request, int $id)
    {
        $data = $request->validate(['motif' => ['required', 'string', 'max:5000']]);
        $absence = DB::transaction(function () use ($request, $id, $data) {
            $presence = Presence::with('feuille.seance')->findOrFail($id);
            // Même verrou que la saisie et la transmission des notes.
            if ($promotionId = $presence->feuille?->seance?->id_promotion) {
                Promotion::whereKey($promotionId)->lockForUpdate()->firstOrFail();
            }
            $presence = Presence::with('feuille.seance')->whereKey($id)->lockForUpdate()->firstOrFail();
            if ($presence->statut !== 'absent' || $presence->feuille?->statut !== 'validee'
                || $presence->feuille?->seance?->statut !== 'realisee') {
                throw ValidationException::withMessages(['absence' => 'Une absence sur une séance réalisée et une feuille de présence validée est requise.']);
            }
            if ($presence->evaluation_autorisee_le === null) {
                $presence->forceFill([
                    'evaluation_autorisee_par' => $request->user()->id,
                    'evaluation_autorisee_le' => now(),
                    'motif_autorisation_evaluation' => $data['motif'],
                ])->save();
            }

            return $presence;
        });

        return response()->json(['message' => 'Évaluation autorisée pour cette absence.', 'absence' => $absence]);
    }
}

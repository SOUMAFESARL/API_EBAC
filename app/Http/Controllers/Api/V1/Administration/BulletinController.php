<?php

namespace App\Http\Controllers\Api\V1\Administration;

use App\Http\Controllers\Controller;
use App\Models\Bulletin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BulletinController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'id_inscription' => ['sometimes', 'integer', 'exists:inscriptions,id'],
            'id_promotion' => ['sometimes', 'integer', 'exists:promotions,id'],
            'id_annee_academique' => ['sometimes', 'integer', 'exists:annees_academiques,id'],
            'statut' => ['sometimes', Rule::in(['Brouillon', 'Publié'])],
            'periode' => ['sometimes', 'string', 'max:50'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
        ]);

        $query = Bulletin::with(['inscription.etudiant', 'inscription.promotion', 'inscription.anneeAcademique']);
        foreach (['id_inscription', 'statut', 'periode'] as $field) {
            if (isset($data[$field])) {
                $query->where($field, $data[$field]);
            }
        }
        foreach (['id_promotion', 'id_annee_academique'] as $field) {
            if (isset($data[$field])) {
                $query->whereHas('inscription', fn ($q) => $q->where($field, $data[$field]));
            }
        }

        return response()->json($query->latest('id')->paginate($data['per_page'] ?? 20));
    }

    public function show(int $id)
    {
        return response()->json(['bulletin' => Bulletin::with([
            'inscription.etudiant', 'inscription.promotion', 'inscription.anneeAcademique', 'lignes.matiere',
        ])->findOrFail($id)]);
    }

    public function publier(Request $request, int $id)
    {
        $bulletin = DB::transaction(function () use ($request, $id) {
            $bulletin = Bulletin::whereKey($id)->lockForUpdate()->firstOrFail();
            if ($bulletin->statut === 'Publié' && $bulletin->date_publication !== null) {
                return $bulletin;
            }
            if ($bulletin->statut !== 'Brouillon') {
                throw ValidationException::withMessages(['statut' => 'Seul un bulletin au statut Brouillon peut être publié.']);
            }
            $bulletin->update([
                'statut' => 'Publié',
                'date_publication' => now(),
                'updated_by' => $request->user()->id,
            ]);

            return $bulletin;
        });

        return response()->json(['message' => 'Bulletin publié.', 'bulletin' => $bulletin]);
    }
}

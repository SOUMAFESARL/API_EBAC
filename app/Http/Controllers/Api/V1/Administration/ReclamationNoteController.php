<?php

namespace App\Http\Controllers\Api\V1\Administration;

use App\Http\Controllers\Controller;
use App\Models\ReclamationNote;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ReclamationNoteController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'statut' => ['sometimes', Rule::in(['en_attente', 'acceptee', 'rejetee'])],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        return response()->json(ReclamationNote::query()
            ->when($data['statut'] ?? null, fn ($q, $statut) => $q->where('statut', $statut))
            ->latest('id')->paginate($data['per_page'] ?? 15));
    }

    public function show(int $id)
    {
        return response()->json(['reclamation' => ReclamationNote::findOrFail($id)]);
    }

    public function traiter(Request $request, int $id)
    {
        $data = $request->validate([
            'statut' => ['required', Rule::in(['acceptee', 'rejetee'])],
            'reponse' => ['required', 'string', 'max:5000'],
        ]);
        $reclamation = DB::transaction(function () use ($request, $data, $id) {
            $reclamation = ReclamationNote::whereKey($id)->lockForUpdate()->firstOrFail();
            if ($reclamation->statut !== 'en_attente') {
                throw ValidationException::withMessages(['statut' => 'Cette réclamation a déjà été traitée.']);
            }
            $reclamation->update([...$data, 'traitee_par' => $request->user()->id, 'date_traitement' => now()]);

            return $reclamation;
        });

        return response()->json(['message' => 'Réclamation traitée.', 'reclamation' => $reclamation]);
    }
}

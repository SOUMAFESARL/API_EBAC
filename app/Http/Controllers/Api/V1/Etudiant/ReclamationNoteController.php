<?php

namespace App\Http\Controllers\Api\V1\Etudiant;

use App\Http\Controllers\Controller;
use App\Models\Etudiant;
use App\Models\LigneBulletin;
use App\Models\ReclamationNote;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ReclamationNoteController extends Controller
{
    private function etudiant(Request $request): Etudiant
    {
        return Etudiant::where('user_id', $request->user()->id)->firstOrFail();
    }

    public function index(Request $request)
    {
        $data = $request->validate([
            'statut' => ['sometimes', Rule::in(['en_attente', 'acceptee', 'rejetee'])],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        return response()->json(ReclamationNote::where('id_etudiant', $this->etudiant($request)->id)
            ->when($data['statut'] ?? null, fn ($q, $statut) => $q->where('statut', $statut))
            ->latest('id')->paginate($data['per_page'] ?? 15));
    }

    public function show(Request $request, int $id)
    {
        return response()->json(['reclamation' => ReclamationNote::where('id_etudiant', $this->etudiant($request)->id)->findOrFail($id)]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'id_ligne_bulletin' => ['required', 'integer'],
            'motif' => ['required', 'string', 'max:5000'],
        ]);
        $etudiant = $this->etudiant($request);
        $reclamation = DB::transaction(function () use ($data, $etudiant) {
            $ligne = LigneBulletin::whereHas('bulletin', fn ($q) => $q->where('statut', 'Publié')->whereNotNull('date_publication')
                ->whereHas('inscription', fn ($i) => $i->where('id_etudiant', $etudiant->id)))
                ->whereKey($data['id_ligne_bulletin'])->lockForUpdate()->firstOrFail();
            if ($ligne->note === null) {
                throw ValidationException::withMessages(['id_ligne_bulletin' => 'Cette matière ne possède pas de note à contester.']);
            }
            if (ReclamationNote::where('id_ligne_bulletin', $ligne->id)->where('statut', 'en_attente')->exists()) {
                throw ValidationException::withMessages(['id_ligne_bulletin' => 'Une réclamation est déjà en attente pour cette note.']);
            }

            return ReclamationNote::create([...$data, 'id_etudiant' => $etudiant->id, 'note_contestee' => $ligne->note, 'statut' => 'en_attente']);
        });

        return response()->json(['message' => 'Réclamation enregistrée.', 'reclamation' => $reclamation], 201);
    }
}

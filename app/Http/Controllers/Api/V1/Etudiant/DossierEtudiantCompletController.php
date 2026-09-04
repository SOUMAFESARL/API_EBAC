<?php

namespace App\Http\Controllers\Api\V1\Etudiant;

use App\Http\Controllers\Controller;
use App\Models\AnneeAcademique;
use App\Models\Etudiant;
use App\Models\Inscription;
use App\Models\Promotion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

class DossierEtudiantCompletController extends Controller
{
    #[OA\Post(path: '/administration/etudiants/{id}/affecter-promotion', operationId: 'affecterEtudiantPromotion', summary: 'Affecter un étudiant ayant déjà un compte à une promotion', tags: ['Dossier étudiant'], security: [['sanctum' => []]], parameters: [new OA\PathParameter(name: 'id', required: true, schema: new OA\Schema(type: 'integer'))], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['id_promotion'], properties: [new OA\Property(property: 'id_promotion', type: 'integer')])), responses: [new OA\Response(response: 201, description: 'Étudiant affecté et inscrit au niveau actuel de la promotion'), new OA\Response(response: 403, description: 'Accès interdit'), new OA\Response(response: 404, description: 'Étudiant ou promotion introuvable'), new OA\Response(response: 422, description: 'Compte absent, promotion inactive, année absente ou étudiant déjà inscrit')])]
    public function affecter(Request $request, int $id): JsonResponse
    {
        $donnees = $request->validate([
            'id_promotion' => ['required', 'integer', Rule::exists('promotions', 'id')->whereNull('deleted_at')],
        ]);

        $etudiant = Etudiant::query()->findOrFail($id);

        if (! $etudiant->user_id) {
            return response()->json(['message' => 'Le compte étudiant doit être créé avant l’affectation.'], 422);
        }

        $anneeAcademique = AnneeAcademique::query()->where('active', true)->first();
        if (! $anneeAcademique) {
            return response()->json(['message' => 'Aucune année académique active n’est configurée.'], 422);
        }

        $promotion = Promotion::query()->with('niveau')->findOrFail($donnees['id_promotion']);
        if (! in_array($promotion->statut, ['Active', 'Actif'], true)) {
            return response()->json(['message' => 'La promotion sélectionnée n’est pas active.'], 422);
        }

        $inscription = DB::transaction(function () use ($request, $etudiant, $promotion, $anneeAcademique): Inscription {
            $etudiantVerrouille = Etudiant::query()->lockForUpdate()->findOrFail($etudiant->id);

            if (Inscription::query()
                ->where('id_etudiant', $etudiantVerrouille->id)
                ->where('id_annee_academique', $anneeAcademique->id)
                ->exists()) {
                abort(422, 'Cet étudiant est déjà inscrit pour l’année académique active.');
            }

            $inscription = Inscription::query()->create([
                'id_etudiant' => $etudiantVerrouille->id,
                'id_promotion' => $promotion->id,
                'id_annee_academique' => $anneeAcademique->id,
                'date_inscription' => now()->toDateString(),
                'statut' => 'En formation',
                'created_by' => $request->user()->id,
            ]);

            $etudiantVerrouille->update([
                'statut' => 'En formation',
                'updated_by' => $request->user()->id,
            ]);

            return $inscription;
        });

        return response()->json([
            'message' => 'Étudiant affecté à la promotion et inscrit avec succès.',
            'inscription' => $inscription->load(['promotion.niveau', 'anneeAcademique']),
            'dossier' => $this->construireDossier($etudiant->fresh()),
        ], 201);
    }

    #[OA\Get(path: '/administration/etudiants/{id}/dossier-complet', operationId: 'afficherDossierEtudiantComplet', summary: 'Ouvrir le dossier étudiant complet', tags: ['Dossier étudiant'], security: [['sanctum' => []]], parameters: [new OA\PathParameter(name: 'id', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 200, description: 'Dossier, informations personnelles, pièces, finances, parcours et bulletins'), new OA\Response(response: 403, description: 'Accès interdit'), new OA\Response(response: 404, description: 'Étudiant introuvable')])]
    public function show(int $id): JsonResponse
    {
        $etudiant = Etudiant::query()->findOrFail($id);

        return response()->json(['dossier' => $this->construireDossier($etudiant)]);
    }

    private function construireDossier(Etudiant $etudiant): array
    {
        $etudiant->load([
            'civilite',
            'eglise',
            'user.role',
            'dossier.fichiers',
            'inscriptions' => fn ($query) => $query->latest('date_inscription')->latest('id'),
            'inscriptions.promotion.niveau',
            'inscriptions.anneeAcademique',
            'inscriptions.bulletins.lignes.matiere',
            'paiements.anneeAcademique',
            'parcoursAcademiques.anneeAcademique',
            'parcoursAcademiques.niveau',
            'parcoursAcademiques.promotion.niveau',
        ]);

        $dossier = $etudiant->dossier;
        $fichiers = $dossier?->fichiers ?? collect();
        $piecesRequises = collect($dossier?->pieces_requises ?? []);
        $maintenant = now()->startOfDay();
        $piecesValides = $fichiers->filter(fn ($fichier) => $fichier->statut_validation === 'Validé'
            && (! $fichier->date_expiration || $fichier->date_expiration->gte($maintenant)));
        $typesValides = $piecesValides->pluck('type_piece')->filter()->map(fn ($type) => mb_strtolower(trim($type)));
        $piecesManquantes = $piecesRequises
            ->reject(fn ($piece) => $typesValides->contains(mb_strtolower(trim((string) $piece))))
            ->values();

        $paiementsValides = $etudiant->paiements->reject(fn ($paiement) => $paiement->statut === 'Annulé');
        $inscriptions = $etudiant->inscriptions->values();

        return [
            'numero_dossier' => $dossier?->numero_dossier,
            'statut_dossier' => $dossier?->statut,
            'pieces_a_jour' => $piecesRequises->isNotEmpty() && $piecesManquantes->isEmpty(),
            'pieces_requises' => $piecesRequises,
            'pieces_manquantes' => $piecesManquantes,
            'documents' => $fichiers,
            'informations_personnelles' => [
                'id' => $etudiant->id,
                'matricule' => $etudiant->matricule,
                'nom' => $etudiant->nom,
                'prenoms' => $etudiant->prenoms,
                'civilite' => $etudiant->civilite,
                'date_naissance' => $etudiant->date_naissance?->format('Y-m-d'),
                'lieu_naissance' => $etudiant->lieu_naissance,
                'nationalite' => $etudiant->nationalite,
                'email' => $etudiant->email,
                'telephone' => $etudiant->telephone,
                'adresse' => $etudiant->adresse,
                'situation_matrimonial' => $etudiant->situation_matrimonial,
                'nombre_enfant' => $etudiant->nombre_enfant,
                'photo_identite_url' => $etudiant->photo_identite_url,
                'statut_actuel' => $etudiant->statut,
                'compte' => $etudiant->user,
            ],
            'eglise_recommandante' => $etudiant->eglise,
            'situation_financiere' => [
                'somme_payee_inscription' => (float) $paiementsValides->where('type_paiement', 'Inscription')->sum('montant'),
                'somme_payee_scolarite' => (float) $paiementsValides->where('type_paiement', 'Scolarité')->sum('montant'),
                'total_paye' => (float) $paiementsValides->sum('montant'),
                'paiements' => $etudiant->paiements,
            ],
            'inscription_actuelle' => $inscriptions->first(),
            'inscription_precedente' => $inscriptions->get(1),
            'historique_inscriptions' => $inscriptions,
            'parcours_academique' => $etudiant->parcoursAcademiques,
            'bulletins' => $inscriptions->flatMap->bulletins->values(),
            'decision_fin_annee' => $inscriptions->first()?->decision_passage,
        ];
    }
}

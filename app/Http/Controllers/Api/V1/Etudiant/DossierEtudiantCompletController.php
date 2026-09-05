<?php

namespace App\Http\Controllers\Api\V1\Etudiant;

use App\Http\Controllers\Controller;
use App\Models\AnneeAcademique;
use App\Models\Etudiant;
use App\Models\FichierDossierEtudiant;
use App\Models\Inscription;
use App\Models\Promotion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

class DossierEtudiantCompletController extends Controller
{
    #[OA\Get(path: '/etudiant/dossier', operationId: 'afficherMonDossierEtudiant', summary: 'Ouvrir le dossier de l’étudiant connecté', tags: ['Dossier étudiant'], security: [['sanctum' => []]], responses: [new OA\Response(response: 200, description: 'Dossier complet de l’étudiant connecté'), new OA\Response(response: 401, description: 'Non authentifié'), new OA\Response(response: 403, description: 'Route réservée au rôle ETUDIANT'), new OA\Response(response: 404, description: 'Aucun dossier étudiant rattaché au compte')])]
    public function monDossier(Request $request): JsonResponse
    {
        $utilisateur = $request->user()->loadMissing('role');

        if ($utilisateur->role?->code !== 'ETUDIANT') {
            return response()->json([
                'message' => 'Cette ressource est réservée aux étudiants.',
            ], 403);
        }

        $etudiant = Etudiant::query()->where('user_id', $utilisateur->id)->firstOrFail();

        return response()->json([
            'id' => $etudiant->id,
            'dossier' => $this->construireDossier($etudiant),
        ]);
    }

    #[OA\Patch(path: '/etudiant/dossier', operationId: 'modifierMonDossierEtudiant', summary: 'Modifier les informations personnelles de son dossier', description: 'JSON à la racine. Pour les fichiers, utiliser POST en multipart/form-data. Matricule, statut, promotion, niveau, décision et paiements ne sont pas modifiables ici.', tags: ['Dossier étudiant'], security: [['sanctum' => []]], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/ModifierDossierEtudiantPayload', example: ['nom' => 'KOUAME', 'prenoms' => 'Anne Marie', 'telephone' => '0102030405'])), responses: [new OA\Response(response: 200, description: 'Dossier personnel modifié'), new OA\Response(response: 403, description: 'Réservé au rôle ETUDIANT'), new OA\Response(response: 422, description: 'Données invalides ou champ administratif interdit')])]
    #[OA\Post(path: '/etudiant/dossier', operationId: 'modifierMonDossierEtudiantMultipart', summary: 'Remplacer sa photo ou ajouter des documents', description: 'POST multipart/form-data. Dans Postman, choisir Body > form-data et le type File. photo_identite remplace la photo d’identité et du profil ; documents[] ajoute des fichiers au dossier existant. Aucun ID étudiant requis.', tags: ['Dossier étudiant'], security: [['sanctum' => []]], requestBody: new OA\RequestBody(required: true, content: new OA\MediaType(mediaType: 'multipart/form-data', schema: new OA\Schema(ref: '#/components/schemas/ModifierDossierEtudiantMultipart'))), responses: [new OA\Response(response: 200, description: 'Dossier personnel et fichiers modifiés'), new OA\Response(response: 403, description: 'Réservé au rôle ETUDIANT'), new OA\Response(response: 422, description: 'Données invalides')])]
    public function modifierMonDossier(Request $request): JsonResponse
    {
        $utilisateur = $request->user()->loadMissing('role');
        if ($utilisateur->role?->code !== 'ETUDIANT') {
            return response()->json(['message' => 'Cette ressource est réservée aux étudiants.'], 403);
        }

        $donnees = $request->validate([
            'nom' => ['sometimes', 'required', 'string', 'max:150'],
            'prenoms' => ['sometimes', 'required', 'string', 'max:150'],
            'civilite_id' => ['sometimes', 'integer', 'exists:civilite,id'],
            'date_naissance' => ['sometimes', 'nullable', 'date'],
            'lieu_naissance' => ['sometimes', 'nullable', 'string', 'max:150'],
            'nationalite' => ['sometimes', 'nullable', 'string', 'max:80'],
            'telephone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'adresse' => ['sometimes', 'nullable', 'string', 'max:255'],
            'eglise_id' => ['sometimes', 'nullable', 'integer', Rule::exists('eglises', 'id')->whereNull('deleted_at')],
            'statut_professionnel' => ['sometimes', 'nullable', 'string', 'max:100'],
            'situation_matrimonial' => ['sometimes', 'nullable', 'string', 'max:50'],
            'nombre_enfant' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:65535'],
            'photo_identite' => ['sometimes', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'documents' => ['sometimes', 'array', 'max:10'],
            'documents.*' => ['file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
            'matricule' => ['prohibited'],
            'statut' => ['prohibited'],
            'id_promotion' => ['prohibited'],
            'id_niveau' => ['prohibited'],
            'decision_passage' => ['prohibited'],
            'paiements' => ['prohibited'],
        ]);

        if (collect($donnees)->except('documents')->isEmpty() && ! $request->hasFile('documents')) {
            throw ValidationException::withMessages([
                'dossier' => 'Aucun champ modifiable reçu. Envoyez les champs à la racine du JSON (exemple : telephone, adresse). Pour une photo ou des documents, utilisez POST en multipart/form-data avec photo_identite ou documents[].',
            ]);
        }

        $etudiant = Etudiant::query()->with('dossier')->where('user_id', $utilisateur->id)->firstOrFail();
        abort_if(! $etudiant->dossier, 404, 'Aucun dossier n’est rattaché à ce compte étudiant.');

        $anciennePhoto = $etudiant->photo_identite;
        $anciennePhotoProfil = $utilisateur->photo;
        $nouveauxChemins = [];
        if ($request->hasFile('photo_identite')) {
            $donnees['photo_identite'] = $request->file('photo_identite')->store('etudiants/photos-identite', 'public');
            $nouveauxChemins[] = $donnees['photo_identite'];
        }
        unset($donnees['documents']);

        try {
            DB::transaction(function () use ($request, $utilisateur, $etudiant, $donnees, &$nouveauxChemins): void {
                $etudiant->update([...$donnees, 'updated_by' => $utilisateur->id]);

                $identite = array_intersect_key($donnees, array_flip(['nom', 'prenoms']));
                if (isset($donnees['photo_identite'])) {
                    $identite['photo'] = $donnees['photo_identite'];
                }
                if ($identite !== []) {
                    $utilisateur->update([...$identite, 'updated_by' => $utilisateur->id]);
                }

                foreach ($request->file('documents', []) as $document) {
                    $chemin = $document->store("etudiants/dossiers/{$etudiant->dossier->id}", 'public');
                    $nouveauxChemins[] = $chemin;
                    FichierDossierEtudiant::query()->create([
                        'id_dossier_etudiant' => $etudiant->dossier->id,
                        'type_piece' => pathinfo($document->getClientOriginalName(), PATHINFO_FILENAME),
                        'nom_original' => $document->getClientOriginalName(),
                        'chemin' => $chemin,
                        'mime_type' => $document->getMimeType(),
                        'taille' => $document->getSize(),
                        'statut_validation' => 'En attente',
                    ]);
                }
            });
        } catch (\Throwable $exception) {
            Storage::disk('public')->delete($nouveauxChemins);
            throw $exception;
        }

        if (isset($donnees['photo_identite'])) {
            Storage::disk('public')->delete(array_values(array_unique(array_filter(
                [$anciennePhoto, $anciennePhotoProfil],
                fn ($chemin) => $chemin && $chemin !== $donnees['photo_identite'],
            ))));
        }

        return response()->json([
            'message' => $request->hasFile('documents')
                ? 'Votre dossier a été modifié avec succès. Les nouveaux documents sont en attente de validation.'
                : 'Votre dossier a été modifié avec succès.',
            'id' => $etudiant->id,
            'dossier' => $this->construireDossier($etudiant->fresh()),
        ]);
    }

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
            'id' => $etudiant->id,
            'inscription' => $inscription->load(['promotion.niveau', 'anneeAcademique']),
            'dossier' => $this->construireDossier($etudiant->fresh()),
        ], 201);
    }

    #[OA\Get(path: '/administration/registre-etudiants/{id}/dossier', operationId: 'ouvrirDossierDepuisRegistre', summary: 'Ouvrir le dossier complet d’un étudiant depuis le registre', tags: ['Registre étudiants'], security: [['sanctum' => []]], parameters: [new OA\PathParameter(name: 'id', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 200, description: 'Dossier, informations personnelles, pièces, finances, parcours et bulletins'), new OA\Response(response: 403, description: 'Réservé à ADMIN et SECRETARIAT'), new OA\Response(response: 404, description: 'Étudiant introuvable')])]
    #[OA\Get(path: '/administration/etudiants/{id}/dossier-complet', operationId: 'afficherDossierEtudiantComplet', summary: 'Ouvrir le dossier étudiant complet', tags: ['Dossier étudiant'], security: [['sanctum' => []]], parameters: [new OA\PathParameter(name: 'id', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 200, description: 'Dossier, informations personnelles, pièces, finances, parcours et bulletins'), new OA\Response(response: 403, description: 'Réservé à ADMIN et SECRETARIAT'), new OA\Response(response: 404, description: 'Étudiant introuvable')])]
    public function show(int $id): JsonResponse
    {
        $etudiant = Etudiant::query()->findOrFail($id);

        return response()->json([
            'id' => $etudiant->id,
            'dossier' => $this->construireDossier($etudiant),
        ]);
    }

    #[OA\Post(path: '/etudiant/dossier/documents/{id}', operationId: 'remplacerMonDocumentEtudiant', summary: 'Remplacer un document existant par son ID', description: 'Récupérer dossier.documents[].id avec GET /etudiant/dossier et le placer dans le chemin. Il s’agit de l’ID du document, pas de l’étudiant. Body > form-data : clé document, type File. Le document doit appartenir au compte connecté. Son ID et son type sont conservés ; la validation est réinitialisée et l’ancien fichier est supprimé après enregistrement.', tags: ['Dossier étudiant'], security: [['sanctum' => []]], parameters: [new OA\PathParameter(name: 'id', required: true, schema: new OA\Schema(type: 'integer'))], requestBody: new OA\RequestBody(required: true, content: new OA\MediaType(mediaType: 'multipart/form-data', schema: new OA\Schema(type: 'object', required: ['document'], properties: [new OA\Property(property: 'document', type: 'string', format: 'binary', description: 'Nouveau fichier PDF, JPG, JPEG, PNG ou WEBP, 10 Mo maximum. Sélectionner le fichier, ne pas saisir un chemin.')]))), responses: [new OA\Response(response: 200, description: 'Document remplacé et remis en attente de validation'), new OA\Response(response: 401, description: 'Non authentifié'), new OA\Response(response: 403, description: 'Réservé au rôle ETUDIANT'), new OA\Response(response: 404, description: 'Document absent du dossier de cet étudiant'), new OA\Response(response: 422, description: 'Fichier invalide')])]
    public function remplacerMonDocument(Request $request, int $id): JsonResponse
    {
        $utilisateur = $request->user()->loadMissing('role');
        abort_unless($utilisateur->role?->code === 'ETUDIANT', 403, 'Cette ressource est réservée aux étudiants.');

        $etudiant = Etudiant::query()->with('dossier')->where('user_id', $utilisateur->id)->firstOrFail();
        abort_if(! $etudiant->dossier, 404, 'Aucun dossier n’est rattaché à ce compte étudiant.');
        $request->validate([
            'document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
        ]);

        $nouveauChemin = null;
        $ancienChemin = null;
        try {
            DB::transaction(function () use ($request, $id, $etudiant, &$nouveauChemin, &$ancienChemin): void {
                $fichier = $etudiant->dossier->fichiers()->lockForUpdate()->findOrFail($id);
                $ancienChemin = $fichier->chemin;
                $nouveauFichier = $request->file('document');
                $nouveauChemin = $nouveauFichier->store("etudiants/dossiers/{$etudiant->dossier->id}", 'public');
                $fichier->update([
                    'nom_original' => $nouveauFichier->getClientOriginalName(),
                    'chemin' => $nouveauChemin,
                    'mime_type' => $nouveauFichier->getMimeType(),
                    'taille' => $nouveauFichier->getSize(),
                    'statut_validation' => 'En attente',
                    'date_validation' => null,
                    'date_expiration' => null,
                    'motif_rejet' => null,
                    'valide_par' => null,
                ]);
            });
        } catch (\Throwable $exception) {
            if ($nouveauChemin) {
                Storage::disk('public')->delete($nouveauChemin);
            }
            throw $exception;
        }

        if ($ancienChemin && $ancienChemin !== $nouveauChemin) {
            Storage::disk('public')->delete($ancienChemin);
        }

        return response()->json([
            'message' => 'Votre document a été remplacé. Il est en attente de validation.',
            'id' => $etudiant->id,
            'dossier' => $this->construireDossier($etudiant->fresh()),
        ]);
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
                'statut_professionnel' => $etudiant->statut_professionnel,
                'situation_matrimonial' => $etudiant->situation_matrimonial,
                'nombre_enfant' => $etudiant->nombre_enfant,
                'photo_identite_url' => $etudiant->photo_identite_url,
                'statut_actuel' => $etudiant->statut,
                'compte' => $etudiant->user?->append('photo_url'),
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

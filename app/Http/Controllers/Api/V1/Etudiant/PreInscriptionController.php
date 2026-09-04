<?php

namespace App\Http\Controllers\Api\V1\Etudiant;

use App\DTOs\Api\V1\Etudiant\PreInscriptionDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Etudiant\PreInscriptionRequest;
use App\Models\DossierEtudiant;
use App\Models\Etudiant;
use App\Models\FichierDossierEtudiant;
use App\Notifications\PreInscriptionRecueNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

class PreInscriptionController extends Controller
{
    #[OA\Post(path: '/etudiant/pre-inscription', operationId: 'preInscrireEtudiant', summary: 'Enregistrer une demande publique de pré-inscription', tags: ['Pré-inscription'], requestBody: new OA\RequestBody(required: true, content: new OA\MediaType(mediaType: 'multipart/form-data', schema: new OA\Schema(ref: '#/components/schemas/PreInscriptionPayload'))), responses: [new OA\Response(response: 201, description: 'Pré-inscription enregistrée', content: new OA\JsonContent(type: 'object', properties: [new OA\Property(property: 'message', type: 'string'), new OA\Property(property: 'pre_inscription', ref: '#/components/schemas/PreInscriptionResultat')])), new OA\Response(response: 422, description: 'Erreur de validation'), new OA\Response(response: 429, description: 'Trop de tentatives')])]
    public function store(PreInscriptionRequest $request): JsonResponse
    {
        $dto = PreInscriptionDTO::fromArray($request->validated());
        $donnees = $dto->toArray();
        $cheminsEnregistres = [];
        $donnees['photo_identite'] = $request->file('photo_identite')->store('etudiants/photos-identite', 'public');
        $cheminsEnregistres[] = $donnees['photo_identite'];

        try {
            $resultat = DB::transaction(function () use ($donnees, $request, &$cheminsEnregistres): array {
                $dossier = [
                    'pieces_requises' => $donnees['pieces_requises'] ?? null,
                    'observations' => $donnees['observations'] ?? null,
                ];
                unset($donnees['pieces_requises'], $donnees['observations'], $donnees['documents']);

                $etudiant = Etudiant::query()->create([
                    ...$donnees,
                    // Compatibilité avec les bases non encore migrées où la colonne est NOT NULL.
                    // Cette référence technique n'est jamais exposée comme matricule étudiant.
                    'matricule' => 'PRE-'.Str::uuid(),
                    'date_inscription' => now()->toDateString(),
                    'statut' => 'Préinscrit',
                ]);
                $dossierEtudiant = DossierEtudiant::query()->create([
                    ...$dossier,
                    'id_etudiant' => $etudiant->id,
                    'numero_dossier' => $this->genererNumeroDossier($etudiant->nom, $etudiant->prenoms),
                    'statut' => 'Incomplet',
                    'date_ouverture' => now()->toDateString(),
                ]);

                foreach ($request->file('documents', []) as $document) {
                    $chemin = $document->store("etudiants/dossiers/{$dossierEtudiant->id}", 'public');
                    $cheminsEnregistres[] = $chemin;

                    FichierDossierEtudiant::query()->create([
                        'id_dossier_etudiant' => $dossierEtudiant->id,
                        'type_piece' => pathinfo($document->getClientOriginalName(), PATHINFO_FILENAME),
                        'nom_original' => $document->getClientOriginalName(),
                        'chemin' => $chemin,
                        'mime_type' => $document->getMimeType(),
                        'taille' => $document->getSize(),
                    ]);
                }

                return [$etudiant, $dossierEtudiant];
            });
        } catch (\Throwable $exception) {
            Storage::disk('public')->delete($cheminsEnregistres);
            throw $exception;
        }

        [$etudiant, $dossier] = $resultat;

        Notification::route('mail', $etudiant->email)->notify(
            new PreInscriptionRecueNotification(
                nomComplet: trim("{$etudiant->prenoms} {$etudiant->nom}"),
                numeroDossier: $dossier->numero_dossier,
            ),
        );

        return response()->json([
            'message' => 'Pré-inscription enregistrée avec succès.',
            'pre_inscription' => [
                'id' => $etudiant->id,
                'numero_dossier' => $dossier->numero_dossier,
                'statut' => $etudiant->statut,
                'statut_dossier' => $dossier->statut,
                'nombre_documents' => $dossier->fichiers()->count(),
                'situation_matrimonial' => $etudiant->situation_matrimonial,
                'nombre_enfant' => $etudiant->nombre_enfant,
            ],
        ], 201);
    }

    private function genererNumeroDossier(string $nom, string $prenoms): string
    {
        $initialesNom = Str::upper(Str::substr(preg_replace('/[^A-Za-z]/', '', Str::ascii($nom)) ?: 'XX', 0, 2));
        $initialesNom = str_pad($initialesNom, 2, 'X');
        $initialePrenom = Str::upper(Str::substr(preg_replace('/[^A-Za-z]/', '', Str::ascii($prenoms)) ?: 'X', 0, 1));
        $prefixe = $initialesNom.$initialePrenom;
        $annee = now()->year;

        $derniereSequence = DossierEtudiant::query()
            ->withTrashed()
            ->where('numero_dossier', 'like', "{$prefixe}%{$annee}")
            ->lockForUpdate()
            ->pluck('numero_dossier')
            ->map(function (string $numero) use ($prefixe, $annee): int {
                return preg_match("/^{$prefixe}(\\d{3}){$annee}$/", $numero, $correspondances)
                    ? (int) $correspondances[1]
                    : -1;
            })
            ->max() ?? -1;

        return sprintf('%s%03d%d', $prefixe, $derniereSequence + 1, $annee);
    }
}

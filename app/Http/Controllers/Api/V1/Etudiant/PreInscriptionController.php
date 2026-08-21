<?php

namespace App\Http\Controllers\Api\V1\Etudiant;

use App\DTOs\Api\V1\Etudiant\PreInscriptionDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Etudiant\PreInscriptionRequest;
use App\Models\DossierEtudiant;
use App\Models\Etudiant;
use App\Notifications\PreInscriptionRecueNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

class PreInscriptionController extends Controller
{
    #[OA\Post(path: '/etudiant/pre-inscription', operationId: 'preInscrireEtudiant', summary: 'Enregistrer une demande publique de pré-inscription', tags: ['Pré-inscription'], requestBody: new OA\RequestBody(required: true, content: new OA\MediaType(mediaType: 'multipart/form-data', schema: new OA\Schema(ref: '#/components/schemas/PreInscriptionPayload'))), responses: [new OA\Response(response: 201, description: 'Pré-inscription enregistrée', content: new OA\JsonContent(type: 'object', properties: [new OA\Property(property: 'message', type: 'string'), new OA\Property(property: 'pre_inscription', ref: '#/components/schemas/PreInscriptionResultat')])), new OA\Response(response: 422, description: 'Erreur de validation'), new OA\Response(response: 429, description: 'Trop de tentatives')])]
    public function store(PreInscriptionRequest $request): JsonResponse
    {
        $dto = PreInscriptionDTO::fromArray($request->validated());
        $donnees = $dto->toArray();
        $donnees['photo_identite'] = $request->file('photo_identite')->store('etudiants/photos-identite', 'public');

        $resultat = DB::transaction(function () use ($donnees): array {
            $dossier = [
                'pieces_requises' => $donnees['pieces_requises'] ?? null,
                'observations' => $donnees['observations'] ?? null,
            ];
            unset($donnees['pieces_requises'], $donnees['observations']);

            $etudiant = Etudiant::query()->create([
                ...$donnees,
                'matricule' => $this->genererMatricule(),
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

            return [$etudiant, $dossierEtudiant];
        });

        [$etudiant, $dossier] = $resultat;

        Notification::route('mail', $etudiant->email)->notify(
            new PreInscriptionRecueNotification(
                nomComplet: trim("{$etudiant->prenoms} {$etudiant->nom}"),
                matricule: $etudiant->matricule,
                numeroDossier: $dossier->numero_dossier,
            ),
        );

        return response()->json([
            'message' => 'Pré-inscription enregistrée avec succès.',
            'pre_inscription' => [
                'id' => $etudiant->id,
                'matricule' => $etudiant->matricule,
                'numero_dossier' => $dossier->numero_dossier,
                'statut' => $etudiant->statut,
                'statut_dossier' => $dossier->statut,
            ],
        ], 201);
    }

    private function genererMatricule(): string
    {
        $annee = now()->year;
        $derniereSequence = Etudiant::query()
            ->withTrashed()
            ->where('matricule', 'like', "EBAC-%-{$annee}")
            ->lockForUpdate()
            ->pluck('matricule')
            ->map(function (string $matricule) use ($annee): int {
                return preg_match("/^EBAC-(\\d{4})-{$annee}$/", $matricule, $correspondances)
                    ? (int) $correspondances[1]
                    : 0;
            })
            ->max() ?? 0;

        return sprintf('EBAC-%04d-%d', $derniereSequence + 1, $annee);
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

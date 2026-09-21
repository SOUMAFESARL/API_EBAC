<?php

namespace App\Http\Controllers\Api\V1\Etudiant;

use App\Http\Controllers\Controller;
use App\Models\Creneau;
use App\Models\Etudiant;
use App\Services\CalendrierAcademiqueService;
use App\Services\CreneauService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

class MonEmploiDuTempsController extends Controller
{
    #[OA\Get(
        path: '/etudiant/emploi-du-temps',
        operationId: 'emploiDuTempsEtudiant',
        summary: 'Consulter l’emploi du temps de l’étudiant connecté',
        description: 'Planning publié de la promotion et des cours communs au niveau de l’étudiant authentifié. Année active par défaut, sinon année couvrant la date du jour. La dernière inscription de cette année détermine la promotion. Sans année, inscription ou programme publié, le planning est vide (200). Sans filtre de module, tous les modules sont retournés ; le total des heures additionne leurs créneaux, même sur des périodes différentes. Aucun identifiant étudiant n’est nécessaire.',
        tags: ['Espace étudiant'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id_annee_academique', in: 'query', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'id_module_calendrier', in: 'query', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Planning hebdomadaire de l’étudiant.', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'etudiant', type: 'object', properties: [new OA\Property(property: 'id', type: 'integer'), new OA\Property(property: 'matricule', type: 'string'), new OA\Property(property: 'nom', type: 'string'), new OA\Property(property: 'prenoms', type: 'string')]),
                new OA\Property(property: 'annee_academique', type: 'object', nullable: true),
                new OA\Property(property: 'promotion', type: 'object', nullable: true),
                new OA\Property(property: 'niveau', type: 'object', nullable: true),
                new OA\Property(property: 'module_calendrier', type: 'object', nullable: true),
                new OA\Property(property: 'publication', type: 'object', properties: [new OA\Property(property: 'statut', type: 'string', enum: ['publie', 'non_publie']), new OA\Property(property: 'version', type: 'integer'), new OA\Property(property: 'date_publication', type: 'string', format: 'date-time', nullable: true)]),
                new OA\Property(property: 'message', type: 'string', nullable: true),
                new OA\Property(property: 'modules_disponibles', type: 'array', items: new OA\Items(type: 'object')),
                new OA\Property(property: 'planning_hebdomadaire', type: 'boolean', example: true),
                new OA\Property(property: 'jours', type: 'array', items: new OA\Items(type: 'object', properties: [new OA\Property(property: 'numero', type: 'integer', minimum: 1, maximum: 7), new OA\Property(property: 'libelle', type: 'string', example: 'Lundi'), new OA\Property(property: 'creneaux', type: 'array', items: new OA\Items(type: 'object'))])),
                new OA\Property(property: 'creneaux', description: 'Horaires, durée en minutes, module calendrier, niveau, matière, cours, promotion, enseignant et salle.', type: 'array', items: new OA\Items(type: 'object')),
                new OA\Property(property: 'nombre_creneaux', type: 'integer', example: 2),
                new OA\Property(property: 'heures_hebdomadaires', type: 'number', example: 4),
            ])),
            new OA\Response(response: 401, description: 'Authentification requise'),
            new OA\Response(response: 403, description: 'Compte etudiant actif requis'),
            new OA\Response(response: 404, description: 'Fiche etudiant introuvable'),
            new OA\Response(response: 422, description: 'Filtres invalides'),
        ]
    )]
    public function __invoke(Request $request, CalendrierAcademiqueService $calendriers, CreneauService $service): JsonResponse
    {
        $filtres = $request->validate([
            'id_annee_academique' => ['sometimes', 'integer', Rule::exists('annees_academiques', 'id')->whereNull('deleted_at')],
            'id_module_calendrier' => ['sometimes', 'integer', 'exists:modules_calendrier,id'],
        ]);
        $etudiant = Etudiant::where('user_id', $request->user()->id)->firstOrFail();
        $annee = $calendriers->anneePublication($filtres['id_annee_academique'] ?? null);
        $calendrier = $annee?->calendrier()->with(['publication', 'modules'])->first();
        $modules = $calendrier?->modules ?? collect();
        $module = isset($filtres['id_module_calendrier']) ? $modules->firstWhere('id', $filtres['id_module_calendrier']) : null;
        abort_if(isset($filtres['id_module_calendrier']) && ! $module, 422, 'Le module calendrier ne correspond pas à cette année académique.');
        $inscription = $annee ? $etudiant->inscriptions()->where('id_annee_academique', $annee->id)
            ->with('promotion.niveau')->latest('date_inscription')->latest('id')->first() : null;
        $promotion = $inscription?->promotion;
        $niveau = $promotion?->niveau;
        $publication = $calendrier?->publication;
        $publie = $publication?->statut === 'publie';
        $creneaux = collect();
        if ($publie && $promotion && $niveau) {
            $creneaux = Creneau::query()->whereIn('id_module_calendrier', $modules->pluck('id'))
                ->when($module, fn ($q) => $q->where('id_module_calendrier', $module->id))
                ->where(fn ($q) => $q->where('id_promotion', $promotion->id)
                    ->orWhere(fn ($q) => $q->whereNull('id_promotion')->where('id_niveau', $niveau->id)))
                ->with(['moduleCalendrier.calendrier', 'niveau', 'matiere', 'cours', 'promotion', 'enseignant', 'salle'])
                ->orderBy('jour')->orderBy('heure_debut')->orderBy('id')->get()
                ->map(fn (Creneau $creneau) => $service->presenter($creneau));
        }
        $jours = collect([1 => 'Lundi', 2 => 'Mardi', 3 => 'Mercredi', 4 => 'Jeudi', 5 => 'Vendredi', 6 => 'Samedi', 7 => 'Dimanche'])
            ->map(function ($libelle, $numero) use ($creneaux) {
                $items = $creneaux->where('jour', $numero)->values();

                return $items->isEmpty() ? null : ['numero' => $numero, 'libelle' => $libelle, 'creneaux' => $items];
            })->filter()->values();

        return response()->json([
            'etudiant' => $etudiant->only(['id', 'matricule', 'nom', 'prenoms']),
            'annee_academique' => $annee?->only(['id', 'libelle', 'date_debut', 'date_fin']),
            'promotion' => $promotion?->only(['id', 'code', 'num_promotion']),
            'niveau' => $niveau?->only(['id', 'code', 'libelle']),
            'module_calendrier' => $module?->only(['id', 'libelle', 'ordre', 'date_debut', 'date_fin']),
            'publication' => ['statut' => $publication?->statut ?? 'non_publie', 'version' => $publication?->version ?? 0, 'date_publication' => $publication?->date_publication],
            'message' => ! $annee ? 'Aucune année académique active ou en cours.'
                : (! $promotion || ! $niveau ? 'Aucune inscription avec promotion et niveau pour cette année.'
                    : ($publie ? null : 'Le programme n’est pas encore disponible.')),
            'modules_disponibles' => $publie && $promotion && $niveau ? $modules->map->only(['id', 'libelle', 'ordre', 'date_debut', 'date_fin']) : [],
            'planning_hebdomadaire' => true,
            'jours' => $jours, 'creneaux' => $creneaux,
            'nombre_creneaux' => $creneaux->count(),
            'heures_hebdomadaires' => $creneaux->sum('duree_minutes') / 60,
        ]);
    }
}

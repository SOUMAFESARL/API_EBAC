<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Cours;
use App\Models\Matiere;
use App\Models\Module;
use App\Models\QuotaStockage;
use App\Models\Role;
use App\Models\SupportPedagogique;
use App\Models\UniteEnseignement;
use App\Models\User;
use App\Services\BibliothequeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SupportPedagogiqueController extends Controller
{
    private const DISQUE = 'local';

    public function __construct(private readonly BibliothequeService $service) {}

    /** Bibliothèque de l'enseignant connecté : ses cours affectés et son quota. */
    public function monEspace(Request $request): JsonResponse
    {
        $enseignant = $request->user();
        $ids = $this->service->coursAffectes($enseignant);
        if ($ids === []) {
            return $this->refus(404, 'AUCUNE_AFFECTATION', 'Aucun cours ne vous est affecté.');
        }

        $portee = $this->service->portee($enseignant);
        $cours = Cours::query()->whereIn('id', $ids)->with(['module.matiere.niveau'])->orderBy('ordre')->get();
        $tous = SupportPedagogique::query()->with('auteur')->orderBy('id')
            ->where(fn ($q) => $q->whereIn('id_cours', $ids)
                ->orWhere(fn ($q) => $q->whereNull('id_cours')->whereIn('id_module', $portee['modules']))
                ->orWhere(fn ($q) => $q->whereNull('id_cours')->whereNull('id_module')->whereIn('id_matiere', $portee['matieres'])))
            ->get();
        $supports = $tous->whereNotNull('id_cours')->groupBy('id_cours');
        $parModule = $tous->whereNull('id_cours')->whereNotNull('id_module')->groupBy('id_module');
        $parMatiere = $tous->whereNull('id_cours')->whereNull('id_module')->groupBy('id_matiere');
        $liste = fn ($groupe, $cle) => ($groupe[$cle] ?? collect())->map(fn ($s) => $this->service->support($s))->values()->all();

        $matieres = $cours->groupBy(fn (Cours $c) => $c->module->id_matiere)->map(function ($items) use ($supports, $parModule, $parMatiere, $liste) {
            $matiere = $items->first()->module->matiere;

            return [
                'id' => $matiere->id,
                'code' => $matiere->code,
                'libelle' => $matiere->libelle,
                'niveau' => $matiere->niveau?->libelle,
                'supports' => $liste($parMatiere, $matiere->id),
                'modules' => $items->groupBy('id_module')->map(fn ($items) => [
                    'id' => $items->first()->module->id,
                    'libelle' => $items->first()->module->libelle,
                    'supports' => $liste($parModule, $items->first()->module->id),
                    'cours' => $items->map(fn (Cours $c) => [
                        'id' => $c->id,
                        'libelle' => $c->libelle,
                        'supports' => ($supports[$c->id] ?? collect())->map(fn ($s) => $this->service->support($s))->values()->all(),
                    ])->values()->all(),
                ])->values()->all(),
            ];
        })->values();

        return response()->json([
            'enseignant' => $this->service->personne($enseignant),
            'matieres' => $matieres,
            'quota' => $this->service->quota($enseignant),
        ]);
    }

    /**
     * Bibliothèque de l'étudiant : matières de son niveau et des niveaux
     * antérieurs, réduites aux branches qui portent un document ; sans déposant.
     */
    public function espaceEtudiant(Request $request): JsonResponse
    {
        $rang = $this->service->rangEtudiant($request->user());
        if ($rang === null) {
            return $this->refus(404, 'AUCUNE_INSCRIPTION', 'Aucune inscription en cours.');
        }

        $supports = SupportPedagogique::query()->with(['matiere.niveau', 'module', 'cours'])
            ->whereHas('matiere', fn ($q) => $q->where('active', true)->whereHas('niveau', fn ($q) => $q->where('rang', '<=', $rang)))
            ->orderBy('id')->get();
        $vue = fn ($liste) => $liste->map(fn ($s) => $this->service->supportSansAuteur($s))->values()->all();

        $matieres = $supports->groupBy('id_matiere')->map(function ($liste) use ($vue) {
            $matiere = $liste->first()->matiere;
            $modules = $liste->whereNotNull('id_module')->groupBy('id_module')
                ->sortBy(fn ($items) => $items->first()->module?->ordre ?? PHP_INT_MAX)
                ->map(fn ($items) => [
                    'id' => $items->first()->id_module,
                    'libelle' => $items->first()->module?->libelle ?? '',
                    'supports' => $vue($items->whereNull('id_cours')),
                    'cours' => $items->whereNotNull('id_cours')->groupBy('id_cours')
                        ->sortBy(fn ($docs) => $docs->first()->cours?->ordre ?? PHP_INT_MAX)
                        ->map(fn ($docs) => [
                            'id' => $docs->first()->id_cours,
                            'libelle' => $docs->first()->cours?->libelle ?? '',
                            'supports' => $vue($docs),
                        ])->values()->all(),
                ])->values()->all();

            return [
                'id' => $matiere->id,
                'code' => $matiere->code,
                'libelle' => $matiere->libelle,
                'niveau' => $matiere->niveau?->libelle,
                'rang' => $matiere->niveau?->rang ?? PHP_INT_MAX,
                'supports' => $vue($liste->whereNull('id_module')),
                'modules' => $modules,
            ];
        })->sortBy([['rang', 'asc'], [fn ($a, $b) => strcmp($a['libelle'], $b['libelle'])]])
            ->map(fn ($m) => collect($m)->except('rang')->all())->values();

        return response()->json(['matieres' => $matieres, 'supports_accessibles' => $supports->count()]);
    }

    /** Toute la bibliothèque, rangée par domaine puis matière (espaces de gestion). */
    public function bibliotheque(): JsonResponse
    {
        $supports = SupportPedagogique::query()
            ->with(['auteur', 'matiere.niveau', 'matiere.enseignant', 'module', 'cours'])
            ->whereHas('matiere')->orderBy('id')->get();

        $parMatiere = $supports->groupBy('id_matiere');
        $domaines = UniteEnseignement::query()->orderBy('libelle')->get()->keyBy('id');

        $matieres = $parMatiere->map(function ($liste) {
            $matiere = $liste->first()->matiere;

            return [
                'domaine_id' => $matiere->unite_ensigne_id,
                'rang' => $matiere->niveau?->rang ?? PHP_INT_MAX,
                'matiere' => [
                    'id' => $matiere->id,
                    'code' => $matiere->code,
                    'libelle' => $matiere->libelle,
                    'niveau' => $matiere->niveau?->libelle,
                    'enseignant_responsable' => $this->service->personne($matiere->enseignant),
                    'documents' => $liste->map(fn (SupportPedagogique $s) => [
                        'support' => $this->service->support($s),
                        'module' => $s->module?->libelle,
                        'cours' => $s->cours?->libelle,
                        'ajoute_par' => $this->service->personne($s->auteur),
                    ])->values()->all(),
                ],
            ];
        })->sortBy([['rang', 'asc'], [fn ($a, $b) => strcmp($a['matiere']['libelle'], $b['matiere']['libelle'])]]);

        $groupes = $matieres->groupBy(fn ($m) => $domaines->has($m['domaine_id']) ? $m['domaine_id'] : 0);
        $resultat = [];
        foreach ($domaines as $id => $domaine) {
            if ($groupes->has($id)) {
                $resultat[] = ['id' => $domaine->id, 'code' => $domaine->code, 'libelle' => $domaine->libelle,
                    'matieres' => $groupes[$id]->pluck('matiere')->values()->all()];
            }
        }
        if ($groupes->has(0)) {
            $resultat[] = ['id' => null, 'code' => null, 'libelle' => null, 'matieres' => $groupes[0]->pluck('matiere')->values()->all()];
        }

        return response()->json([
            'domaines' => $resultat,
            'matieres_sans_document' => Matiere::query()->count() - $parMatiere->count(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $donnees = $request->validate([
            'id_matiere' => ['required', 'integer', Rule::exists('matieres', 'id')->whereNull('deleted_at')],
            'id_module' => ['nullable', 'integer', Rule::exists('modules', 'id')->whereNull('deleted_at')],
            'id_cours' => ['nullable', 'integer', Rule::exists('cours', 'id')->whereNull('deleted_at')],
            'nom' => ['required', 'string', 'min:3', 'max:120'],
            'type' => ['required', Rule::in(SupportPedagogique::TYPES)],
            'fichier' => ['required', 'file'],
        ]);
        $utilisateur = $request->user();
        [$matiereId, $moduleId, $coursId] = $this->rattachement($donnees);

        if (! $this->service->peutEcrire($utilisateur, $matiereId, $moduleId, $coursId, 'creer')) {
            return $this->service->estEnseignant($utilisateur)
                ? $this->refus(403, 'COURS_NON_AFFECTE', 'Ce cours ne vous est pas affecté.')
                : $this->refus(403, 'DROITS_INSUFFISANTS', 'Vous ne disposez pas de la permission nécessaire pour cette action.');
        }
        $fichier = $request->file('fichier');
        if ($refus = $this->refusFichier($fichier, $utilisateur)) {
            return $refus;
        }

        $chemin = $fichier->store('supports', self::DISQUE);
        try {
            $support = SupportPedagogique::create([
                'id_matiere' => $matiereId,
                'id_module' => $moduleId,
                'id_cours' => $coursId,
                'nom' => $donnees['nom'],
                'type' => $donnees['type'],
                'chemin' => $chemin,
                'nom_fichier' => $fichier->getClientOriginalName(),
                'format' => strtoupper($fichier->getClientOriginalExtension()),
                'taille_octets' => (int) $fichier->getSize(),
                'depose_par' => $utilisateur->id,
            ]);
        } catch (\Throwable $erreur) {
            Storage::disk(self::DISQUE)->delete($chemin);
            throw $erreur;
        }

        return response()->json([
            'support' => $this->service->support($support),
            'quota' => $this->quotaDe($utilisateur),
        ], 201);
    }

    /**
     * Supprime le document et son fichier. Un support n'a pas de versions :
     * pour le remplacer, on le supprime puis on dépose le nouveau.
     */
    public function destroy(Request $request, int $support): JsonResponse
    {
        $utilisateur = $request->user();
        $item = SupportPedagogique::find($support);
        if (! $item) {
            return $this->refus(404, 'SUPPORT_INTROUVABLE', 'Support introuvable.');
        }
        if (! $this->service->peutEcrire($utilisateur, $item->id_matiere, $item->id_module, $item->id_cours, 'supprimer')) {
            return $this->service->estEnseignant($utilisateur)
                ? $this->refus(403, 'SUPPORT_NON_AFFECTE', "Ce support relève d'un cours qui ne vous est pas affecté.")
                : $this->refus(403, 'DROITS_INSUFFISANTS', 'Vous ne disposez pas de la permission nécessaire pour cette action.');
        }

        $chemin = $item->chemin;
        $item->delete();
        Storage::disk(self::DISQUE)->delete($chemin);

        return response()->json(['quota' => $this->quotaDe($utilisateur)]);
    }

    public function telecharger(Request $request, int $support): JsonResponse|StreamedResponse
    {
        $item = SupportPedagogique::with('matiere.niveau')->find($support);
        if (! $item || ! Storage::disk(self::DISQUE)->exists($item->chemin)) {
            return $this->refus(404, 'SUPPORT_INTROUVABLE', 'Fichier introuvable.');
        }
        if (! $this->service->peutLire($request->user(), $item)) {
            return $this->refus(403, 'DROITS_INSUFFISANTS', "Vous n'avez pas accès à ce document.");
        }

        return Storage::disk(self::DISQUE)->download($item->chemin, $item->nom_fichier, [
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function quotas(): JsonResponse
    {
        $enseignants = User::query()->whereHas('role', fn ($q) => $q->where('code', 'ENSEIGNANT'))->orderBy('nom')->orderBy('prenoms')->get();

        return response()->json(['quotas' => $enseignants->map(fn (User $u) => $this->service->quota($u))->values()]);
    }

    public function definirQuota(Request $request, int $enseignant): JsonResponse
    {
        $donnees = $request->validate(['quota_mo' => ['required', 'integer', 'min:50', 'max:20000']]);
        $titulaire = User::query()->whereKey($enseignant)
            ->whereIn('id_role', Role::query()->where('code', 'ENSEIGNANT')->pluck('id'))->first();
        if (! $titulaire) {
            return $this->refus(404, 'ENSEIGNANT_INTROUVABLE', 'Enseignant introuvable.');
        }
        if ($this->service->utiliseOctets($titulaire) > $donnees['quota_mo'] * 1048576) {
            return $this->refus(409, 'QUOTA_INFERIEUR_UTILISE', "Le quota ne peut pas descendre sous l'espace déjà occupé.");
        }

        QuotaStockage::updateOrCreate(['enseignant_id' => $titulaire->id], ['quota_mo' => $donnees['quota_mo']]);

        return response()->json(['quota' => $this->service->quota($titulaire)]);
    }

    private function refusFichier(UploadedFile $fichier, User $deposant): ?JsonResponse
    {
        if (! in_array(strtoupper($fichier->getClientOriginalExtension()), BibliothequeService::FORMATS, true)) {
            return $this->refus(409, 'FORMAT_REFUSE', 'Format de fichier non accepté.');
        }
        if ($fichier->getSize() > BibliothequeService::TAILLE_MAX_KO * 1024) {
            return $this->refus(409, 'FICHIER_TROP_LOURD', 'Le fichier dépasse 50 Mo.');
        }
        if ($this->service->quotaDepasse($deposant, (int) $fichier->getSize())) {
            return $this->refus(409, 'QUOTA_DEPASSE', 'Espace de stockage insuffisant.');
        }

        return null;
    }

    /**
     * Matière obligatoire ; module et cours facultatifs, mais cohérents :
     * le cours appartient au module, le module à la matière.
     *
     * @return array{0: int, 1: ?int, 2: ?int}
     */
    private function rattachement(array $donnees): array
    {
        $matiereId = (int) $donnees['id_matiere'];
        $moduleId = isset($donnees['id_module']) ? (int) $donnees['id_module'] : null;
        $coursId = isset($donnees['id_cours']) ? (int) $donnees['id_cours'] : null;

        if ($coursId !== null) {
            $moduleDuCours = (int) Cours::query()->whereKey($coursId)->value('id_module');
            if ($moduleId !== null && $moduleId !== $moduleDuCours) {
                throw ValidationException::withMessages(['id_cours' => "Ce cours n'appartient pas au module choisi."]);
            }
            $moduleId = $moduleDuCours;
        }
        if ($moduleId !== null && (int) Module::query()->whereKey($moduleId)->value('id_matiere') !== $matiereId) {
            throw ValidationException::withMessages(['id_module' => "Ce module n'appartient pas à la matière choisie."]);
        }

        return [$matiereId, $moduleId, $coursId];
    }

    /** Quota du déposant enseignant ; `null` pour la gestion, hors quota. */
    private function quotaDe(User $utilisateur): ?array
    {
        return $this->service->estEnseignant($utilisateur) ? $this->service->quota($utilisateur) : null;
    }

    private function refus(int $statut, string $code, string $message): JsonResponse
    {
        return response()->json(['code' => $code, 'message' => $message], $statut);
    }
}

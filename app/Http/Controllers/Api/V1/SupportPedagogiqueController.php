<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Cours;
use App\Models\Matiere;
use App\Models\QuotaStockage;
use App\Models\Role;
use App\Models\SupportPedagogique;
use App\Models\UniteEnseignement;
use App\Models\User;
use App\Models\VersionSupport;
use App\Services\BibliothequeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
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

        $cours = Cours::query()->whereIn('id', $ids)->with(['module.matiere.niveau'])->orderBy('ordre')->get();
        $supports = SupportPedagogique::query()->whereIn('id_cours', $ids)->with('versions.auteur')->orderBy('id')->get()->groupBy('id_cours');

        $matieres = $cours->groupBy(fn (Cours $c) => $c->module->id_matiere)->map(function ($liste) use ($supports) {
            $matiere = $liste->first()->module->matiere;

            return [
                'id' => $matiere->id,
                'code' => $matiere->code,
                'libelle' => $matiere->libelle,
                'niveau' => $matiere->niveau?->libelle,
                'modules' => $liste->groupBy('id_module')->map(fn ($items) => [
                    'id' => $items->first()->module->id,
                    'libelle' => $items->first()->module->libelle,
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

    /** Toute la bibliothèque, rangée par domaine puis matière (espaces de gestion). */
    public function bibliotheque(): JsonResponse
    {
        $supports = SupportPedagogique::query()
            ->with(['versions.auteur', 'auteur', 'cours.module.matiere.niveau', 'cours.module.matiere.enseignant'])
            ->whereHas('cours.module.matiere')->orderBy('id')->get();

        $parMatiere = $supports->groupBy(fn ($s) => $s->cours->module->id_matiere);
        $domaines = UniteEnseignement::query()->orderBy('libelle')->get()->keyBy('id');

        $matieres = $parMatiere->map(function ($liste) {
            $matiere = $liste->first()->cours->module->matiere;

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
                        'module' => $s->cours->module->libelle,
                        'cours' => $s->cours->libelle,
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
            'id_cours' => ['required', 'integer', Rule::exists('cours', 'id')->whereNull('deleted_at')],
            'nom' => ['required', 'string', 'min:3', 'max:120'],
            'type' => ['required', Rule::in(SupportPedagogique::TYPES)],
            'note' => ['nullable', 'string', 'max:300'],
            'fichier' => ['required', 'file'],
        ]);
        $utilisateur = $request->user();

        if (! $this->service->peutEcrire($utilisateur, (int) $donnees['id_cours'], 'creer')) {
            return $this->service->estEnseignant($utilisateur)
                ? $this->refus(403, 'COURS_NON_AFFECTE', 'Ce cours ne vous est pas affecté.')
                : $this->refus(403, 'DROITS_INSUFFISANTS', 'Vous ne disposez pas de la permission nécessaire pour cette action.');
        }
        if ($refus = $this->refusFichier($request->file('fichier'), $utilisateur)) {
            return $refus;
        }

        $support = DB::transaction(function () use ($donnees, $request, $utilisateur) {
            $support = SupportPedagogique::create([
                'id_cours' => $donnees['id_cours'], 'nom' => $donnees['nom'], 'type' => $donnees['type'], 'depose_par' => $utilisateur->id,
            ]);
            $this->enregistrerVersion($support, 1, $request->file('fichier'), $donnees['note'] ?? 'Version initiale.', $utilisateur);

            return $support;
        });

        return $this->reponseEcriture($support, $utilisateur, 201);
    }

    public function publierVersion(Request $request, int $support): JsonResponse
    {
        $donnees = $request->validate([
            'note' => ['required', 'string', 'min:5', 'max:300'],
            'fichier' => ['required', 'file'],
        ]);
        $utilisateur = $request->user();
        $item = SupportPedagogique::find($support);
        if (! $item) {
            return $this->refus(404, 'SUPPORT_INTROUVABLE', 'Support introuvable.');
        }
        if ($refus = $this->refusEcriture($utilisateur, $item)) {
            return $refus;
        }
        if ($refus = $this->refusFichier($request->file('fichier'), $utilisateur)) {
            return $refus;
        }

        DB::transaction(fn () => $this->enregistrerVersion(
            $item, $this->prochainNumero($item), $request->file('fichier'), $donnees['note'], $utilisateur
        ));

        return $this->reponseEcriture($item, $utilisateur);
    }

    public function republier(Request $request, int $support, int $numero): JsonResponse
    {
        $utilisateur = $request->user();
        $item = SupportPedagogique::find($support);
        if (! $item) {
            return $this->refus(404, 'SUPPORT_INTROUVABLE', 'Support introuvable.');
        }
        if ($refus = $this->refusEcriture($utilisateur, $item)) {
            return $refus;
        }
        $ancienne = $item->versions()->where('numero', $numero)->first();
        if (! $ancienne) {
            return $this->refus(404, 'VERSION_INTROUVABLE', 'Version introuvable.');
        }
        $courante = $this->prochainNumero($item) - 1;
        if ($ancienne->numero === $courante) {
            return $this->refus(422, 'DONNEES_INVALIDES', 'Cette version est déjà la version courante.');
        }
        if ($this->service->quotaDepasse($utilisateur, $ancienne->taille_octets)) {
            return $this->refus(409, 'QUOTA_DEPASSE', 'Espace de stockage insuffisant.');
        }

        DB::transaction(fn () => VersionSupport::create([
            'id_support' => $item->id, 'numero' => $courante + 1, 'chemin' => $ancienne->chemin,
            'nom_fichier' => $ancienne->nom_fichier, 'format' => $ancienne->format, 'taille_octets' => $ancienne->taille_octets,
            'note' => "Republication de la version v{$ancienne->numero}.", 'depose_par' => $utilisateur->id,
        ]));

        return $this->reponseEcriture($item, $utilisateur);
    }

    public function telecharger(Request $request, int $support, int $numero): JsonResponse|StreamedResponse
    {
        $item = SupportPedagogique::with('cours.module.matiere.niveau')->find($support);
        $version = $item?->versions()->where('numero', $numero)->first();
        if (! $version || ! Storage::disk(self::DISQUE)->exists($version->chemin)) {
            return $this->refus(404, 'VERSION_INTROUVABLE', 'Fichier introuvable.');
        }
        if (! $this->service->peutLire($request->user(), $item)) {
            return $this->refus(403, 'DROITS_INSUFFISANTS', "Vous n'avez pas accès à ce document.");
        }

        return Storage::disk(self::DISQUE)->download($version->chemin, $version->nom_fichier, [
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

    private function refusEcriture(User $utilisateur, SupportPedagogique $support): ?JsonResponse
    {
        if ($this->service->peutEcrire($utilisateur, $support->id_cours, 'modifier')) {
            return null;
        }

        return $this->service->estEnseignant($utilisateur)
            ? $this->refus(403, 'SUPPORT_NON_AFFECTE', "Ce support relève d'un cours qui ne vous est pas affecté.")
            : $this->refus(403, 'DROITS_INSUFFISANTS', 'Vous ne disposez pas de la permission nécessaire pour cette action.');
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

    private function enregistrerVersion(SupportPedagogique $support, int $numero, UploadedFile $fichier, string $note, User $auteur): void
    {
        VersionSupport::create([
            'id_support' => $support->id,
            'numero' => $numero,
            'chemin' => $fichier->store("supports/{$support->id}", self::DISQUE),
            'nom_fichier' => $fichier->getClientOriginalName(),
            'format' => strtoupper($fichier->getClientOriginalExtension()),
            'taille_octets' => (int) $fichier->getSize(),
            'note' => $note,
            'depose_par' => $auteur->id,
        ]);
    }

    private function prochainNumero(SupportPedagogique $support): int
    {
        return (int) VersionSupport::query()->where('id_support', $support->id)->lockForUpdate()->max('numero') + 1;
    }

    private function reponseEcriture(SupportPedagogique $support, User $utilisateur, int $statut = 200): JsonResponse
    {
        $support->unsetRelation('versions');

        return response()->json([
            'support' => $this->service->support($support),
            'quota' => $this->service->estEnseignant($utilisateur) ? $this->service->quota($utilisateur) : null,
        ], $statut);
    }

    private function refus(int $statut, string $code, string $message): JsonResponse
    {
        return response()->json(['code' => $code, 'message' => $message], $statut);
    }
}

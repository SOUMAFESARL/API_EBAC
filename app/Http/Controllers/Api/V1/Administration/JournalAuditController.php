<?php

namespace App\Http\Controllers\Api\V1\Administration;

use App\Http\Controllers\Controller;
use App\Models\JournalAudit;
use App\Models\User;
use App\Support\Audit\Journal;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class JournalAuditController extends Controller
{
    private const PERIODES = ['jour', 'semaine', 'mois'];

    public function index(Request $request): JsonResponse
    {
        $donnees = $request->validate([
            'periode' => ['required', Rule::in(self::PERIODES)],
            'ancre' => ['required', 'date_format:Y-m-d'],
            'categorie' => ['nullable', Rule::in(array_keys(config('audit.categories')))],
            'criticite_minimale' => ['nullable', Rule::in(config('audit.criticites'))],
            'recherche' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'par_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $lisibles = $this->categoriesLisibles($request->user());
        $categorie = $donnees['categorie'] ?? null;
        if ($categorie && ! in_array($categorie, $lisibles, true)) {
            return response()->json([
                'code' => 'CATEGORIE_FERMEE',
                'message' => 'Cette catégorie du journal n’est pas ouverte à votre profil.',
            ], 403);
        }

        [$debut, $fin] = $this->bornes($donnees['periode'], CarbonImmutable::createFromFormat('!Y-m-d', $donnees['ancre'], 'UTC'));

        $requete = JournalAudit::query()
            ->where('horodatage', '>=', $debut->format('Y-m-d H:i:s'))
            ->where('horodatage', '<', $fin->addDay()->format('Y-m-d H:i:s'))
            ->whereIn('categorie', $categorie ? [$categorie] : $lisibles)
            ->when($donnees['criticite_minimale'] ?? null, function (Builder $requete, string $seuil) {
                $criticites = config('audit.criticites');
                $requete->whereIn('criticite', array_slice($criticites, array_search($seuil, $criticites, true)));
            })
            ->when(trim((string) ($donnees['recherche'] ?? '')), function (Builder $requete, string $terme) {
                $motif = '%'.addcslashes($terme, '%_\\').'%';
                $requete->where(fn (Builder $ou) => $ou
                    ->where('acteur_nom', 'like', $motif)
                    ->orWhere('action', 'like', $motif)
                    ->orWhere('objet_type', 'like', $motif)
                    ->orWhere('objet_libelle', 'like', $motif)
                    ->orWhere('adresse_ip', 'like', $motif));
            });

        $parPage = (int) ($donnees['par_page'] ?? 10);
        $total = (clone $requete)->count();
        $pages = max(1, (int) ceil($total / $parPage));
        $page = min((int) ($donnees['page'] ?? 1), $pages);

        $entrees = $requete->with('utilisateur:id')
            ->orderByDesc('horodatage')->orderByDesc('id')
            ->forPage($page, $parPage)
            ->get()
            ->map(fn (JournalAudit $entree) => $this->entree($entree))
            ->all();

        return response()->json([
            'entrees' => $entrees,
            'bornes' => ['debut' => $debut->toDateString(), 'fin' => $fin->toDateString()],
            'page' => $page,
            'pages' => $pages,
            'total' => $total,
            'parPage' => $parPage,
            'categoriesLisibles' => $lisibles,
        ]);
    }

    public function exporter(Request $request): JsonResponse
    {
        $donnees = $request->validate([
            'periode' => ['required', Rule::in(self::PERIODES)],
            'debut' => ['required', 'date_format:Y-m-d'],
            'fin' => ['required', 'date_format:Y-m-d', 'after_or_equal:debut'],
            'lignes' => ['required', 'integer', 'min:0'],
            'categorie' => ['nullable', Rule::in(array_keys(config('audit.categories')))],
        ]);

        $categorie = $donnees['categorie'] ?? null;
        if ($categorie && ! in_array($categorie, $this->categoriesLisibles($request->user()), true)) {
            return response()->json([
                'code' => 'CATEGORIE_FERMEE',
                'message' => 'Cette catégorie du journal n’est pas ouverte à votre profil.',
            ], 403);
        }

        $periode = CarbonImmutable::parse($donnees['debut'])->format('d/m/Y').' → '.CarbonImmutable::parse($donnees['fin'])->format('d/m/Y');
        $entree = Journal::consigner('exports', 'Export du journal d’audit (CSV)', objet: [
            'type' => 'Journal d’audit',
            'libelle' => $categorie ? config("audit.libelles.{$categorie}") : 'Toutes catégories',
        ], apres: [
            'Période' => $periode,
            'Fenêtre' => ucfirst($donnees['periode']),
            'Lignes' => (string) $donnees['lignes'],
        ]);

        return response()->json(['id' => $entree ? (string) $entree->id : null], 201);
    }

    /** @return list<string> */
    private function categoriesLisibles(User $utilisateur): array
    {
        $utilisateur->loadMissing('role');

        return $utilisateur->role?->code === 'ADMIN'
            ? array_keys(config('audit.categories'))
            : config('audit.categories_restreintes');
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    private function bornes(string $periode, CarbonImmutable $ancre): array
    {
        return match ($periode) {
            'jour' => [$ancre, $ancre],
            'semaine' => [$ancre->startOfWeek(CarbonImmutable::MONDAY), $ancre->startOfWeek(CarbonImmutable::MONDAY)->addDays(6)],
            default => [$ancre->startOfMonth(), $ancre->endOfMonth()->startOfDay()],
        };
    }

    /** @return array<string, mixed> */
    private function entree(JournalAudit $entree): array
    {
        return [
            'id' => (string) $entree->id,
            'horodatage' => $entree->horodatage->utc()->format('Y-m-d\TH:i:s.v\Z'),
            'acteur' => [
                'id' => $entree->utilisateur ? (string) $entree->utilisateur->id : null,
                'nom' => $entree->acteur_nom,
                'profil' => $entree->acteur_profil,
            ],
            'action' => $entree->action,
            'categorie' => $entree->categorie,
            'criticite' => $entree->criticite,
            'objet' => $entree->objet_type ? ['type' => $entree->objet_type, 'libelle' => (string) $entree->objet_libelle] : null,
            'valeurAvant' => $entree->valeur_avant ?: null,
            'valeurApres' => $entree->valeur_apres ?: null,
            'adresseIp' => $entree->adresse_ip,
        ];
    }
}

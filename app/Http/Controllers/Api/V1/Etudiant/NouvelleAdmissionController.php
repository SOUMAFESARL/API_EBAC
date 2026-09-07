<?php

namespace App\Http\Controllers\Api\V1\Etudiant;

use App\Http\Controllers\Controller;
use App\Models\NouvelleAdmission;
use App\Services\ImportAdmissions;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use OpenApi\Attributes as OA;

class NouvelleAdmissionController extends Controller
{
    private function filtres(Request $request): array
    {
        return $request->validate([
            'annee_entree' => ['sometimes', 'integer', 'min:1900', 'max:9998'],
            'recherche' => ['sometimes', 'nullable', 'string', 'max:255'],
            'par_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);
    }

    private function requete(array $filtres)
    {
        return NouvelleAdmission::query()->where('annee_entree', $filtres['annee_entree'] ?? now()->year)
            ->when($filtres['recherche'] ?? null, fn ($query, $recherche) => $query->where(function ($query) use ($recherche) {
                $query->where('nom_prenoms', 'like', '%'.$recherche.'%')
                    ->orWhere('region', 'like', '%'.$recherche.'%')->orWhere('paroisse', 'like', '%'.$recherche.'%');
            }));
    }

    #[OA\Get(path: '/administration/nouvelles-admissions', summary: 'Liste paginée, statistiques et arrêtés des admis par rentrée', tags: ['Nouvelles admissions'], security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'annee_entree', in: 'query', schema: new OA\Schema(type: 'integer')), new OA\Parameter(name: 'recherche', in: 'query', schema: new OA\Schema(type: 'string')), new OA\Parameter(name: 'par_page', in: 'query', schema: new OA\Schema(type: 'integer', maximum: 100))], responses: [new OA\Response(response: 200, description: 'Liste et statistiques de la rentrée'), new OA\Response(response: 403, description: 'Rôle non autorisé')])]
    public function index(Request $request)
    {
        $filtres = $this->filtres($request);
        $annee = (int) ($filtres['annee_entree'] ?? now()->year);
        $base = NouvelleAdmission::query()->where('annee_entree', $annee);
        $imports = DB::table('imports_admissions')->where('annee_entree', $annee)->orderByDesc('id')->get()
            ->map(fn ($import) => [
                'id' => $import->id, 'nom_fichier' => $import->nom_fichier, 'created_at' => $import->created_at,
                'arrete_url' => $import->arrete_chemin ? route('api.v1.administration.nouvelles-admissions.arrete', ['id' => $import->id]) : null,
            ]);

        return response()->json([
            'annee_entree' => $annee, 'rentree' => $annee.'-'.($annee + 1),
            'annees_disponibles' => NouvelleAdmission::query()->distinct()->orderByDesc('annee_entree')->pluck('annee_entree'),
            'statistiques' => [
                'total' => (clone $base)->count(),
                'dossier_depose' => (clone $base)->where('dossier_depose', true)->count(),
                'sans_coordonnees' => (clone $base)->whereNull('telephone')->whereNull('adresse')->count(),
                'dernier_enregistrement' => (clone $base)->max('created_at'),
            ],
            'imports' => $imports,
            'admis' => $this->requete($filtres)->orderBy('id')->paginate($filtres['par_page'] ?? 15)->withQueryString(),
        ]);
    }

    #[OA\Post(path: '/administration/nouvelles-admissions/importer', summary: 'Importer les admis depuis Excel ou CSV', tags: ['Nouvelles admissions'], security: [['sanctum' => []]], requestBody: new OA\RequestBody(required: true, content: new OA\MediaType(mediaType: 'multipart/form-data', schema: new OA\Schema(required: ['annee_entree', 'liste'], properties: [new OA\Property(property: 'annee_entree', type: 'integer', example: 2026), new OA\Property(property: 'liste', type: 'string', format: 'binary', description: 'XLSX, XLS ou CSV ; 10 Mo, 5000 lignes maximum. Colonnes : Nom et Prénoms, Région, Paroisse, Situation matrimoniale.'), new OA\Property(property: 'arrete', type: 'string', format: 'binary', description: 'PDF signé facultatif, 10 Mo maximum')]))), responses: [new OA\Response(response: 201, description: 'Import terminé, nombres importés et doublons ignorés'), new OA\Response(response: 422, description: 'Fichier ou ligne invalide ; aucun admis enregistré'), new OA\Response(response: 403, description: 'Rôle non autorisé')])]
    public function importer(Request $request, ImportAdmissions $service)
    {
        $donnees = $request->validate([
            'annee_entree' => ['required', 'integer', 'min:1900', 'max:9998'],
            'liste' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'extensions:xlsx,xls,csv', 'max:10240'],
            'arrete' => ['sometimes', 'nullable', 'file', 'mimes:pdf', 'extensions:pdf', 'max:10240'],
        ]);
        $lignes = $service->lire($request->file('liste'));
        $chemin = null;
        try {
            if ($request->hasFile('arrete')) {
                $chemin = $request->file('arrete')->store('admissions/'.$donnees['annee_entree'], 'local');
                if (! $chemin) {
                    throw new \RuntimeException('Impossible de conserver l’arrêté signé.');
                }
            }
            $resultat = DB::transaction(function () use ($request, $donnees, $lignes, $chemin) {
                $importId = DB::table('imports_admissions')->insertGetId([
                    'annee_entree' => $donnees['annee_entree'], 'nom_fichier' => $request->file('liste')->getClientOriginalName(),
                    'arrete_chemin' => $chemin, 'created_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now(),
                ]);
                $importes = 0;
                foreach ($lignes as $ligne) {
                    $admis = NouvelleAdmission::query()->firstOrCreate([
                        'annee_entree' => $donnees['annee_entree'], 'empreinte' => $ligne['empreinte'],
                    ], [...$ligne, 'import_admission_id' => $importId]);
                    $importes += (int) $admis->wasRecentlyCreated;
                }

                return ['import_id' => $importId, 'importes' => $importes, 'doublons_ignores' => count($lignes) - $importes];
            });
        } catch (\Throwable $exception) {
            if ($chemin) {
                Storage::disk('local')->delete($chemin);
            }
            throw $exception;
        }

        return response()->json(['message' => 'Import terminé.', 'annee_entree' => (int) $donnees['annee_entree'], ...$resultat], 201);
    }

    #[OA\Patch(path: '/administration/nouvelles-admissions/{id}', summary: 'Compléter les coordonnées et le dépôt du dossier', tags: ['Nouvelles admissions'], security: [['sanctum' => []]], parameters: [new OA\PathParameter(name: 'id', required: true, schema: new OA\Schema(type: 'integer'))], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(properties: [new OA\Property(property: 'telephone', type: 'string', nullable: true), new OA\Property(property: 'adresse', type: 'string', nullable: true), new OA\Property(property: 'email', type: 'string', format: 'email', nullable: true), new OA\Property(property: 'dossier_depose', type: 'boolean')])), responses: [new OA\Response(response: 200, description: 'Admis mis à jour'), new OA\Response(response: 404, description: 'Admis introuvable'), new OA\Response(response: 422, description: 'Coordonnées invalides')])]
    public function update(Request $request, int $id)
    {
        $donnees = $request->validate([
            'telephone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'adresse' => ['sometimes', 'nullable', 'string', 'max:255'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'dossier_depose' => ['sometimes', 'boolean'],
        ]);
        $admis = NouvelleAdmission::query()->findOrFail($id);
        $admis->update([...$donnees, 'updated_by' => $request->user()->id]);

        return response()->json(['message' => 'Admission mise à jour.', 'admis' => $admis->fresh()]);
    }

    #[OA\Get(path: '/administration/nouvelles-admissions/imports/{id}/arrete', summary: 'Télécharger l’arrêté signé d’un import', tags: ['Nouvelles admissions'], security: [['sanctum' => []]], parameters: [new OA\PathParameter(name: 'id', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 200, description: 'PDF signé'), new OA\Response(response: 404, description: 'Arrêté absent')])]
    public function arrete(int $id)
    {
        $import = DB::table('imports_admissions')->find($id);
        abort_unless($import?->arrete_chemin && Storage::disk('local')->exists($import->arrete_chemin), 404);

        return Storage::disk('local')->download($import->arrete_chemin, 'arrete-'.$import->annee_entree.'-'.$id.'.pdf', ['Content-Type' => 'application/pdf', 'Cache-Control' => 'private, no-store']);
    }

    #[OA\Get(path: '/administration/nouvelles-admissions/pdf', summary: 'Exporter la liste des admis en PDF', tags: ['Nouvelles admissions'], security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'annee_entree', in: 'query', schema: new OA\Schema(type: 'integer')), new OA\Parameter(name: 'recherche', in: 'query', schema: new OA\Schema(type: 'string'))], responses: [new OA\Response(response: 200, description: 'Liste PDF')])]
    public function pdf(Request $request)
    {
        $filtres = $this->filtres($request);
        $annee = (int) ($filtres['annee_entree'] ?? now()->year);

        return Pdf::loadView('pdf.nouvelles-admissions', [
            'admis' => $this->requete($filtres)->orderBy('id')->get(), 'rentree' => $annee.'-'.($annee + 1),
        ])->setPaper('a4', 'landscape')->download('admis-'.$annee.'.pdf');
    }
}

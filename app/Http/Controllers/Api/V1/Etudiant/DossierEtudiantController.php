<?php

namespace App\Http\Controllers\Api\V1\Etudiant;

use App\Http\Controllers\Controller;
use App\Models\DossierEtudiant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

class DossierEtudiantController extends Controller
{
    #[OA\Get(path: '/administration/dossiers-etudiants', operationId: 'listerDossiersEtudiants', summary: 'Lister les dossiers étudiants par église et par statut', tags: ['Administration des étudiants'], security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'eglise_id', in: 'query', schema: new OA\Schema(type: 'integer')), new OA\Parameter(name: 'statut', in: 'query', description: 'Exemple : Incomplet, Complet ou Validé', schema: new OA\Schema(type: 'string')), new OA\Parameter(name: 'recherche', in: 'query', schema: new OA\Schema(type: 'string')), new OA\Parameter(name: 'par_page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100, default: 15))], responses: [new OA\Response(response: 200, description: 'Liste paginée des dossiers', content: new OA\JsonContent(type: 'object', properties: [new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/DossierEtudiantListe'))])), new OA\Response(response: 401, description: 'Non authentifié'), new OA\Response(response: 403, description: 'Accès interdit aux rôles ENSEIGNANT et ETUDIANT'), new OA\Response(response: 422, description: 'Filtres invalides')])]
    public function index(Request $request): AnonymousResourceCollection
    {
        $filtres = $request->validate([
            'eglise_id' => ['sometimes', 'integer', Rule::exists('eglises', 'id')->whereNull('deleted_at')],
            'statut' => ['sometimes', 'string', 'max:30'],
            'recherche' => ['sometimes', 'string', 'max:150'],
            'par_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $dossiers = DossierEtudiant::query()
            ->with(['etudiant.eglise', 'etudiant.civilite', 'etudiant.user.role', 'fichiers'])
            ->when($filtres['eglise_id'] ?? null, fn ($query, int $egliseId) => $query->whereHas('etudiant', fn ($query) => $query->where('eglise_id', $egliseId)))
            ->when($filtres['statut'] ?? null, fn ($query, string $statut) => $query->where('statut', $statut))
            ->when($filtres['recherche'] ?? null, function ($query, string $recherche) {
                $query->where(function ($query) use ($recherche) {
                    $query->where('numero_dossier', 'like', "%{$recherche}%")
                        ->orWhereHas('etudiant', function ($query) use ($recherche) {
                            $query->where('nom', 'like', "%{$recherche}%")
                                ->orWhere('prenoms', 'like', "%{$recherche}%")
                                ->orWhere('matricule', 'like', "%{$recherche}%");
                        });
                });
            })
            ->latest('date_ouverture')
            ->latest('id')
            ->paginate($filtres['par_page'] ?? 15)
            ->withQueryString();

        return JsonResource::collection($dossiers);
    }
}

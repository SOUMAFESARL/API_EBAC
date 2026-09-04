<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class FichierPreinscriptionController extends Controller
{
    #[OA\Get(path: '/fichiers-preinscriptions/{chemin}', operationId: 'afficherFichierPreinscription', summary: 'Afficher une photo ou un document de préinscription', tags: ['Fichiers'], security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'chemin', in: 'path', required: true, description: 'Chemin complet retourné par photo_identite_url ou documents[].url', schema: new OA\Schema(type: 'string'))], responses: [new OA\Response(response: 200, description: 'Contenu binaire du fichier'), new OA\Response(response: 401, description: 'Non authentifié'), new OA\Response(response: 403, description: 'Compte inactif ou rôle interdit'), new OA\Response(response: 404, description: 'Fichier introuvable')])]
    public function __invoke(string $chemin): BinaryFileResponse
    {
        abort_if(
            str_contains($chemin, '..') || ! Storage::disk('public')->exists($chemin),
            404,
        );

        return response()->file(Storage::disk('public')->path($chemin), [
            'Cache-Control' => 'public, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}

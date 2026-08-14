<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifierCompteActif
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response|JsonResponse
    {
        $utilisateur = $request->user();

        if (! $utilisateur || ! $utilisateur->is_active || $utilisateur->statut !== 'Actif') {
            return response()->json([
                'message' => 'Ce compte est inactif ou ne peut pas accéder à cette ressource.',
            ], 403);
        }

        return $next($request);
    }
}

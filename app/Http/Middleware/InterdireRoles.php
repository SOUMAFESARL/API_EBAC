<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InterdireRoles
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string ...$rolesInterdits): Response
    {
        $utilisateur = $request->user()?->loadMissing('role');

        if (! $utilisateur) {
            return response()->json(['message' => 'Non authentifié.'], 401);
        }

        if (in_array($utilisateur->role?->code, $rolesInterdits, true)) {
            return response()->json([
                'message' => 'Votre rôle ne permet pas d’accéder à la gestion des préinscriptions.',
            ], 403);
        }

        return $next($request);
    }
}

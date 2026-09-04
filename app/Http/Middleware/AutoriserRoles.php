<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AutoriserRoles
{
    public function handle(Request $request, Closure $next, string ...$rolesAutorises): Response
    {
        $utilisateur = $request->user()?->loadMissing('role');

        if (! $utilisateur) {
            return response()->json(['message' => 'Non authentifié.'], 401);
        }

        if (! in_array($utilisateur->role?->code, $rolesAutorises, true)) {
            return response()->json([
                'message' => 'Votre rôle ne permet pas d’accéder à cette ressource.',
                'roles_autorises' => $rolesAutorises,
            ], 403);
        }

        return $next($request);
    }
}

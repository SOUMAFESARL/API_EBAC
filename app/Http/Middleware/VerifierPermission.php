<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifierPermission
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $utilisateur = $request->user()?->loadMissing('role.permissions');

        if (! $utilisateur) {
            return response()->json(['message' => 'Non authentifié.'], 401);
        }

        if ($utilisateur->role?->code === 'ADMIN') {
            return $next($request);
        }

        $permissionsActives = $utilisateur->role?->permissions
            ->filter(fn ($permission) => (bool) $permission->pivot->actif)
            ->pluck('code')
            ->all() ?? [];

        if (array_intersect($permissions, $permissionsActives) === []) {
            return response()->json([
                'message' => 'Vous ne disposez pas de la permission nécessaire pour cette action.',
                'permissions_requises' => $permissions,
            ], 403);
        }

        return $next($request);
    }
}

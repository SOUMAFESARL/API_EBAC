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
        $utilisateur = $request->user()?->load('role.permissions');

        if (! $utilisateur) {
            return response()->json(['message' => 'Non authentifié.'], 401);
        }

        if ($utilisateur->role?->code === 'ADMIN') {
            return $next($request);
        }

        if (! ($utilisateur->role?->actif ?? true)) {
            return response()->json(['message' => 'Votre rôle est inactif.'], 403);
        }

        // `@CODE_ROLE` ouvre la route à un rôle, quels que soient ses droits :
        // c'est ainsi que les référentiels gardent leurs lecteurs d'avant la
        // matrice, à côté du droit « consulter » qui les ouvre aux autres.
        $rolesAdmis = array_map(
            fn (string $jeton) => substr($jeton, 1),
            array_filter($permissions, fn (string $jeton) => str_starts_with($jeton, '@')),
        );
        if (in_array($utilisateur->role?->code, $rolesAdmis, true)) {
            return $next($request);
        }
        $permissions = array_values(array_filter($permissions, fn (string $jeton) => ! str_starts_with($jeton, '@')));

        $permissionsActives = $utilisateur->codesPermissions();

        if (array_intersect($permissions, $permissionsActives) === []) {
            return response()->json([
                'message' => 'Vous ne disposez pas de la permission nécessaire pour cette action.',
                'permissions_requises' => $permissions,
            ], 403);
        }

        return $next($request);
    }
}

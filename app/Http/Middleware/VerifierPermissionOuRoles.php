<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifierPermissionOuRoles
{
    public function handle(Request $request, Closure $next, string $permission, string ...$rolesAutorises): Response
    {
        $utilisateur = $request->user()?->loadMissing('role.permissions');

        if (! $utilisateur) {
            return response()->json(['message' => 'Non authentifié.'], 401);
        }

        $role = $utilisateur->role;
        $roleAutorise = $role?->code === 'ADMIN'
            || in_array($role?->code, $rolesAutorises, true);
        $permissionAccordee = $role?->permissions->contains(
            fn ($permissionRole) => $permissionRole->code === $permission
                && (bool) $permissionRole->pivot->actif,
        ) ?? false;

        if (! $roleAutorise && ! $permissionAccordee) {
            return response()->json([
                'message' => 'Vous ne disposez pas de la permission nécessaire pour cette action.',
                'permissions_requises' => [$permission],
                'roles_autorises' => $rolesAutorises,
            ], 403);
        }

        return $next($request);
    }
}

<?php

namespace App\Rules;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;

class UtilisateurEstEnseignant implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $utilisateur = User::query()->with('role')->find($value);
        $role = $utilisateur?->role;

        if (! $role || ! in_array('enseignant', [
            Str::lower((string) $role->code),
            Str::lower((string) $role->libelle),
        ], true)) {
            $fail('L’utilisateur sélectionné doit avoir le rôle Enseignant.');
        }
    }
}

<?php

namespace Tests;

use App\Models\Permission;
use App\Models\Role;
use Database\Seeders\CatalogueDroitsSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Un rôle semé (Direction, Secrétariat, Gestionnaire) créé par un test
     * reçoit ses droits par défaut, comme en production où la migration
     * `semer_catalogue_droits` les pose. Un test qui veut un rôle sans droit
     * l'écrit avec `sansDroitsParDefaut()`.
     */
    protected bool $droitsParDefaut = true;

    /** @var array<string, list<string>> droits accordés en plus, par code de rôle */
    private array $droitsAccordes = [];

    protected function setUp(): void
    {
        parent::setUp();

        Role::created(function (Role $role) {
            if ($this->droitsParDefaut) {
                CatalogueDroitsSeeder::poserDroitsParDefaut($role);
            }
            $this->poserDroitsAccordes($role);
        });
    }

    /**
     * Accorde des droits à un rôle, qu'il existe déjà ou soit créé plus tard
     * dans le test — comme l'Administrateur le ferait dans « Rôles & droits ».
     */
    protected function accorderDroits(string $codeRole, string ...$droits): static
    {
        $this->droitsAccordes[$codeRole] = [...($this->droitsAccordes[$codeRole] ?? []), ...$droits];
        $role = Role::query()->where('code', $codeRole)->first();
        if ($role) {
            $this->poserDroitsAccordes($role);
        }

        return $this;
    }

    private function poserDroitsAccordes(Role $role): void
    {
        $codes = $this->droitsAccordes[$role->code] ?? [];
        if ($codes === []) {
            return;
        }
        $ids = Permission::query()->whereIn('code', $codes)->pluck('id')->all();
        $role->permissions()->syncWithoutDetaching(array_fill_keys($ids, ['actif' => true]));
        $role->unsetRelation('permissions');
    }

    protected function sansDroitsParDefaut(): static
    {
        $this->droitsParDefaut = false;

        return $this;
    }
}

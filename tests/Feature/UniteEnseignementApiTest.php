<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UniteEnseignementApiTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/v1/parametres/unites-enseignement';

    private function connecter(string $code = 'ADMIN'): User
    {
        $role = Role::query()->create(['code' => $code, 'libelle' => $code]);
        $user = User::factory()->create(['id_role' => $role->id]);
        Sanctum::actingAs($user);

        return $user;
    }

    private function payload(): array
    {
        return ['libelle' => 'Connaissance de la Bible', 'code' => 'BIB', 'statut' => 'Actif'];
    }

    public function test_crud_filtres_et_audit(): void
    {
        $user = $this->connecter();
        $id = $this->postJson(self::URL, $this->payload())
            ->assertCreated()->assertJsonPath('unite_enseignement.created_by', $user->id)
            ->json('unite_enseignement.id');
        $this->getJson(self::URL.'?q=Bible&statut=Actif&code=BIB')->assertOk()->assertJsonCount(1, 'unites_enseignement');
        $this->getJson(self::URL.'?q=inconnu')->assertOk()->assertJsonCount(0, 'unites_enseignement');
        $this->getJson(self::URL."/{$id}")->assertOk()->assertJsonPath('unite_enseignement.code', 'BIB');
        $this->patchJson(self::URL."/{$id}", ['description' => 'Introduction', 'statut' => 'Archive'])
            ->assertOk()->assertJsonPath('unite_enseignement.code', 'BIB')
            ->assertJsonPath('unite_enseignement.updated_by', $user->id);
        $this->getJson(self::URL.'?statut=Actif')->assertOk()->assertJsonCount(0, 'unites_enseignement');
        $this->putJson(self::URL."/{$id}", [...$this->payload(), 'description' => null])
            ->assertOk()->assertJsonPath('unite_enseignement.description', null);
        $this->deleteJson(self::URL."/{$id}")->assertOk();
        $this->assertSoftDeleted('unites_enseignement', ['id' => $id, 'deleted_by' => $user->id]);
        $this->getJson(self::URL."/{$id}")->assertNotFound();
        $this->getJson(self::URL)->assertOk()->assertJsonCount(0, 'unites_enseignement');
        $this->patchJson(self::URL."/{$id}", ['libelle' => 'Autre'])->assertNotFound();
        $this->deleteJson(self::URL."/{$id}")->assertNotFound();
    }

    public function test_validation_et_unicite_du_code(): void
    {
        $this->connecter();
        $this->postJson(self::URL, [])->assertUnprocessable()->assertJsonValidationErrors(['libelle', 'code', 'statut']);
        $id = $this->postJson(self::URL, $this->payload())->assertCreated()->json('unite_enseignement.id');
        $this->postJson(self::URL, $this->payload())->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->patchJson(self::URL."/{$id}", ['code' => 'BIB'])->assertOk();
        $autre = $this->postJson(self::URL, [...$this->payload(), 'code' => 'THEO'])->assertCreated()->json('unite_enseignement.id');
        $this->patchJson(self::URL."/{$autre}", ['code' => 'BIB'])->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->patchJson(self::URL."/{$id}", ['statut' => 'invalide'])->assertUnprocessable()->assertJsonValidationErrors('statut');
        $this->getJson(self::URL.'?statut=invalide')->assertUnprocessable();
    }

    public function test_authentification_et_role_obligatoires(): void
    {
        $this->getJson(self::URL)->assertUnauthorized();
        $user = $this->connecter('ETUDIANT');
        $this->getJson(self::URL)->assertForbidden();
        $this->postJson(self::URL, $this->payload())->assertForbidden();
        $this->getJson(self::URL.'/1')->assertForbidden();
        $this->patchJson(self::URL.'/1', ['statut' => 'Archive'])->assertForbidden();
        $this->deleteJson(self::URL.'/1')->assertForbidden();
        $user->update(['is_active' => false]);
        $this->getJson(self::URL)->assertForbidden();
    }

    public function test_les_roles_autorises_peuvent_effectuer_le_crud(): void
    {
        foreach (['ADMIN', 'SECRETAIRE_ACADEMIQUE', 'SECRETARIAT', 'DIRECTION'] as $role) {
            $this->connecter($role);
            $id = $this->postJson(self::URL, [...$this->payload(), 'code' => $role])
                ->assertCreated()->json('unite_enseignement.id');
            $this->getJson(self::URL)->assertOk();
            $this->getJson(self::URL."/{$id}")->assertOk();
            $this->putJson(self::URL."/{$id}", ['statut' => 'Archive'])->assertOk();
            $this->patchJson(self::URL."/{$id}", ['statut' => 'Actif'])->assertOk();
            $this->deleteJson(self::URL."/{$id}")->assertOk();
        }
    }

    public function test_les_autres_roles_ne_peuvent_pas_acceder_au_crud(): void
    {
        foreach (['GESTIONNAIRE', 'ENSEIGNANT', 'ETUDIANT'] as $role) {
            $this->connecter($role);
            $this->getJson(self::URL)->assertForbidden();
            $this->postJson(self::URL, $this->payload())->assertForbidden();
            $this->getJson(self::URL.'/1')->assertForbidden();
            $this->putJson(self::URL.'/1', ['statut' => 'Archive'])->assertForbidden();
            $this->patchJson(self::URL.'/1', ['statut' => 'Archive'])->assertForbidden();
            $this->deleteJson(self::URL.'/1')->assertForbidden();
        }
    }
}

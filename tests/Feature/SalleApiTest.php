<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Salle;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SalleApiTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/v1/parametres/salles';

    private function connecter(): User
    {
        $role = Role::query()->create(['code' => 'ADMIN', 'libelle' => 'Administrateur']);
        $user = User::factory()->create(['id_role' => $role->id]);
        Sanctum::actingAs($user);

        return $user;
    }

    public function test_crud_audit_et_reutilisation_du_code(): void
    {
        $user = $this->connecter();
        $id = $this->postJson(self::URL, ['nom' => 'Salle A', 'code' => ' a ', 'created_by' => 999])
            ->assertCreated()->assertJsonPath('salle.code', 'A')->assertJsonPath('salle.statut', 'Actif')
            ->assertJsonPath('salle.created_by', $user->id)->assertJsonPath('salle.seances_par_semaine', null)->json('salle.id');
        $url = self::URL.'/'.$id;
        $this->getJson($url)->assertOk()->assertJsonPath('salle.nom', 'Salle A');
        $this->patchJson($url, ['statut' => 'Inactif'])->assertOk()->assertJsonPath('salle.updated_by', $user->id);
        $this->putJson($url, ['nom' => 'Salle B', 'code' => 'B', 'statut' => 'Actif'])->assertOk()->assertJsonPath('salle.code', 'B');
        $this->deleteJson($url, ['nom' => null])->assertOk();
        $this->assertSoftDeleted('salles', ['id' => $id, 'deleted_by' => $user->id]);
        $this->getJson($url)->assertNotFound();
        $this->patchJson($url, ['nom' => 'Autre'])->assertNotFound();
        $this->deleteJson($url)->assertNotFound();
        $this->postJson(self::URL, ['nom' => 'Nouvelle salle B', 'code' => 'B'])->assertCreated();
        $this->getJson(self::URL)->assertOk()->assertJsonCount(1, 'salles')->assertJsonPath('salles_en_service', 1);
    }

    public function test_recherche_tri_filtres_et_pagination(): void
    {
        $this->connecter();
        foreach ([['A', 'Salle A', 'Actif'], ['B', 'Salle B', 'Inactif'], ['AMPHI', 'Amphithéâtre', 'Actif']] as [$code, $nom, $statut]) {
            $this->postJson(self::URL, compact('code', 'nom', 'statut'))->assertCreated();
        }
        $this->getJson(self::URL.'?recherche=salle&tri=nom&direction=desc&per_page=1&page=2')
            ->assertOk()->assertJsonPath('meta.total', 2)->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('salles.0.code', 'A')->assertJsonPath('salles_en_service', 2);
        $this->getJson(self::URL.'?statut=Inactif')->assertOk()->assertJsonCount(1, 'salles')->assertJsonPath('salles.0.code', 'B');
        $this->getJson(self::URL.'?recherche=amphi')->assertOk()->assertJsonPath('salles.0.code', 'AMPHI');
        $this->getJson(self::URL.'?recherche=%25')->assertOk()->assertJsonCount(0, 'salles');
        $this->getJson(self::URL.'?page=99')->assertOk()->assertJsonCount(0, 'salles');
        foreach (['per_page=101', 'page=0', 'tri=unknown', 'direction=invalid', 'statut=Archive'] as $query) {
            $this->getJson(self::URL.'?'.$query)->assertUnprocessable();
        }
    }

    public function test_validation_et_doublons(): void
    {
        $this->connecter();
        $id = $this->postJson(self::URL, ['nom' => 'Salle A', 'code' => 'A'])->assertCreated()->json('salle.id');
        $this->postJson(self::URL, ['nom' => 'Doublon', 'code' => 'a'])->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->patchJson(self::URL.'/'.$id, ['code' => 'a'])->assertOk();
        $autreId = $this->postJson(self::URL, ['nom' => 'Salle B', 'code' => 'B'])->assertCreated()->json('salle.id');
        $this->patchJson(self::URL.'/'.$autreId, ['code' => 'A'])->assertUnprocessable();
        foreach ([[], ['nom' => ' ', 'code' => 'C'], ['nom' => 'C', 'code' => []], ['nom' => 'C', 'code' => 'C', 'statut' => 'Invalide']] as $data) {
            $this->postJson(self::URL, $data)->assertUnprocessable();
        }
        $this->putJson(self::URL.'/'.$id, ['statut' => 'Actif'])->assertUnprocessable();
        $this->patchJson(self::URL.'/'.$id, ['nom' => null])->assertUnprocessable();
        $this->assertDatabaseCount('salles', 2);
    }

    public function test_authentification_et_compte_actif(): void
    {
        $this->getJson(self::URL)->assertUnauthorized();
        $this->postJson(self::URL, [])->assertUnauthorized();
        $this->getJson(self::URL.'/1')->assertUnauthorized();
        $this->patchJson(self::URL.'/1', [])->assertUnauthorized();
        $this->deleteJson(self::URL.'/1')->assertUnauthorized();
        $user = $this->connecter();
        $this->getJson(self::URL.'/999')->assertNotFound();
        $this->getJson(self::URL.'/abc')->assertNotFound();
        $user->update(['is_active' => false]);
        $this->getJson(self::URL)->assertForbidden();
        $this->postJson(self::URL, ['nom' => 'Salle A', 'code' => 'A'])->assertForbidden();
        $this->patchJson(self::URL.'/1', ['statut' => 'Inactif'])->assertForbidden();
        $this->deleteJson(self::URL.'/1')->assertForbidden();
    }

    public function test_unicite_en_base(): void
    {
        Salle::query()->create(['nom' => 'Salle A', 'code' => 'A']);
        $this->expectException(UniqueConstraintViolationException::class);
        Salle::query()->create(['nom' => 'Doublon', 'code' => 'A']);
    }
}

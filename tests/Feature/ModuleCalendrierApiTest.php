<?php

namespace Tests\Feature;

use App\Models\AnneeAcademique;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ModuleCalendrierApiTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/v1/parametres/modules-calendrier';

    private function calendrier(): int
    {
        $role = Role::create(['code' => 'ADMIN', 'libelle' => 'Administrateur']);
        Sanctum::actingAs(User::factory()->create(['id_role' => $role->id]));
        $annee = AnneeAcademique::create([
            'libelle' => '2026-2027',
            'date_debut' => '2026-09-01',
            'date_fin' => '2027-07-31',
        ]);

        return $annee->calendrier()->create([])->id;
    }

    public function test_crud_module_calendrier(): void
    {
        $calendrierId = $this->calendrier();
        $payload = [
            'id_calendrier' => $calendrierId,
            'libelle' => 'Premier semestre',
            'ordre' => 1,
            'date_debut' => '2026-09-01',
            'date_fin' => '2026-12-20',
        ];

        $id = $this->postJson(self::URL, $payload)
            ->assertCreated()
            ->assertJsonPath('module.libelle', 'Premier semestre')
            ->json('module.id');
        $this->getJson(self::URL.'?id_calendrier='.$calendrierId)
            ->assertOk()->assertJsonCount(1, 'modules');
        $this->getJson(self::URL.'/'.$id)->assertOk()->assertJsonPath('module.ordre', 1);
        $this->patchJson(self::URL.'/'.$id, ['libelle' => 'Module 1'])
            ->assertOk()->assertJsonPath('module.libelle', 'Module 1');
        $this->putJson(self::URL.'/'.$id, [...$payload, 'libelle' => 'Semestre 1'])
            ->assertOk()->assertJsonPath('module.libelle', 'Semestre 1');
        $this->deleteJson(self::URL.'/'.$id)->assertOk();
        $this->assertDatabaseMissing('modules_calendrier', ['id' => $id]);
    }

    public function test_validation_des_bornes_du_chevauchement_et_de_l_ordre(): void
    {
        $calendrierId = $this->calendrier();
        $base = ['id_calendrier' => $calendrierId, 'libelle' => 'Module 1', 'ordre' => 1,
            'date_debut' => '2026-09-01', 'date_fin' => '2026-12-20'];
        $this->postJson(self::URL, $base)->assertCreated();
        $this->postJson(self::URL, [...$base, 'libelle' => 'Doublon', 'ordre' => 2, 'date_debut' => '2026-12-20', 'date_fin' => '2027-01-31'])
            ->assertUnprocessable()->assertJsonValidationErrors('date_debut');
        $this->postJson(self::URL, [...$base, 'libelle' => 'Ordre doublon', 'date_debut' => '2027-01-01', 'date_fin' => '2027-02-01'])
            ->assertUnprocessable()->assertJsonValidationErrors('ordre');
        $this->postJson(self::URL, [...$base, 'ordre' => 2, 'date_debut' => '2026-08-01'])
            ->assertUnprocessable()->assertJsonValidationErrors('date_debut');
    }

    public function test_authentification_requise(): void
    {
        $this->getJson(self::URL)->assertUnauthorized();
        $this->postJson(self::URL, [])->assertUnauthorized();
    }
}

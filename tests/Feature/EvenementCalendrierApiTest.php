<?php

namespace Tests\Feature;

use App\Models\AnneeAcademique;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EvenementCalendrierApiTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/v1/parametres/evenements-calendrier';

    private function contexte(): array
    {
        $role = Role::create(['code' => 'ADMIN', 'libelle' => 'Administrateur']);
        Sanctum::actingAs(User::factory()->create(['id_role' => $role->id]));
        $annee = AnneeAcademique::create(['libelle' => '2026-2027', 'date_debut' => '2026-09-01', 'date_fin' => '2027-07-31']);
        $calendrier = $annee->calendrier()->create([]);
        $module = $calendrier->modules()->create(['libelle' => 'Module 1', 'ordre' => 1, 'date_debut' => '2026-09-01', 'date_fin' => '2026-12-20']);
        return [$calendrier, $module];
    }

    public function test_crud_et_filtres(): void
    {
        [$calendrier, $module] = $this->contexte();
        $payload = ['id_calendrier' => $calendrier->id, 'id_module_calendrier' => $module->id, 'type' => 'examen',
            'libelle' => 'Examen final', 'date_debut' => '2026-12-15', 'date_fin' => '2026-12-20'];
        $id = $this->postJson(self::URL, $payload)->assertCreated()->assertJsonPath('evenement.type', 'examen')->json('evenement.id');
        $this->getJson(self::URL.'?id_calendrier='.$calendrier->id.'&type=examen')->assertOk()->assertJsonCount(1, 'evenements');
        $this->getJson(self::URL.'/'.$id)->assertOk()->assertJsonPath('evenement.libelle', 'Examen final');
        $this->patchJson(self::URL.'/'.$id, ['libelle' => 'Partiel'])->assertOk()->assertJsonPath('evenement.libelle', 'Partiel');
        $this->putJson(self::URL.'/'.$id, [...$payload, 'libelle' => 'Examen'])->assertOk();
        $this->deleteJson(self::URL.'/'.$id)->assertOk();
        $this->assertDatabaseMissing('evenements_calendrier', ['id' => $id]);
    }

    public function test_regles_metier_des_types_et_dates(): void
    {
        [$calendrier, $module] = $this->contexte();
        $base = ['id_calendrier' => $calendrier->id, 'id_module_calendrier' => $module->id, 'type' => 'examen',
            'libelle' => null, 'date_debut' => '2026-12-15', 'date_fin' => '2026-12-20'];
        $this->postJson(self::URL, [...$base, 'date_debut' => '2027-01-01', 'date_fin' => '2027-01-02'])
            ->assertUnprocessable()->assertJsonValidationErrors('date_debut');
        $this->postJson(self::URL, [...$base, 'type' => 'rattrapage', 'date_debut' => '2026-12-21', 'date_fin' => '2026-12-22'])->assertCreated();
        $this->postJson(self::URL, ['id_calendrier' => $calendrier->id, 'type' => 'jour_ferie', 'libelle' => 'Fête', 'date_debut' => '2027-01-01', 'date_fin' => '2027-01-01'])->assertCreated();
        $vacances = ['id_calendrier' => $calendrier->id, 'type' => 'grandes_vacances', 'libelle' => 'Vacances', 'date_debut' => '2027-08-01', 'date_fin' => '2027-08-31'];
        $this->postJson(self::URL, $vacances)->assertCreated();
        $this->postJson(self::URL, $vacances)->assertUnprocessable()->assertJsonValidationErrors('type');
        $this->postJson(self::URL, [...$base, 'type' => 'inconnu'])->assertUnprocessable()->assertJsonValidationErrors('type');
    }

    public function test_authentification_requise(): void
    {
        $this->getJson(self::URL)->assertUnauthorized();
        $this->postJson(self::URL, [])->assertUnauthorized();
    }
}

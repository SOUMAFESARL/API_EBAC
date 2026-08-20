<?php

namespace Tests\Feature;

use App\Models\Matiere;
use App\Models\Module;
use App\Models\Niveau;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CoursApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_crud_filtres_et_relations_des_cours(): void
    {
        $role = Role::query()->create(['code' => 'ADMIN', 'libelle' => 'Administrateur']);
        $utilisateur = User::factory()->create(['id_role' => $role->id]);
        Sanctum::actingAs($utilisateur);
        $niveau = Niveau::query()->create(['libelle' => 'Première année', 'code' => 'A1', 'rang' => 1]);
        $matiere = Matiere::query()->create(['code' => 'MAT-001', 'libelle' => 'Bible', 'id_niveau' => $niveau->id]);
        $module = Module::query()->create(['id_matiere' => $matiere->id, 'code' => 'MOD-001', 'libelle' => 'Introduction']);

        $id = $this->postJson('/api/v1/parametres/cours', [
            'id_module' => $module->id,
            'code' => 'CRS-001',
            'libelle' => 'Contexte biblique',
            'volume_horaire' => 12.5,
            'coefficient' => 2,
            'ordre' => 1,
            'actif' => true,
        ])->assertCreated()
            ->assertJsonPath('cours.module.id', $module->id)
            ->assertJsonPath('cours.module.matiere.id', $matiere->id)
            ->assertJsonPath('cours.module.matiere.niveau.id', $niveau->id)
            ->json('cours.id');

        $this->getJson("/api/v1/parametres/cours?module={$module->id}&matiere={$matiere->id}&niveau={$niveau->id}&actif=1&ordre=1&q=Contexte")
            ->assertOk()->assertJsonCount(1, 'cours');
        $this->getJson("/api/v1/parametres/cours/{$id}")->assertOk();
        $this->patchJson("/api/v1/parametres/cours/{$id}", ['coefficient' => 3, 'actif' => false])
            ->assertOk()->assertJsonPath('cours.actif', false);
        $this->deleteJson("/api/v1/parametres/cours/{$id}")->assertOk();
        $this->assertSoftDeleted('cours', ['id' => $id, 'deleted_by' => $utilisateur->id]);
    }

    public function test_libelle_est_unique_dans_un_module(): void
    {
        $role = Role::query()->create(['code' => 'ADMIN', 'libelle' => 'Administrateur']);
        Sanctum::actingAs(User::factory()->create(['id_role' => $role->id]));
        $niveau = Niveau::query()->create(['libelle' => 'Première année', 'code' => 'A1', 'rang' => 1]);
        $matiere = Matiere::query()->create(['code' => 'MAT-001', 'libelle' => 'Bible', 'id_niveau' => $niveau->id]);
        $module = Module::query()->create(['id_matiere' => $matiere->id, 'libelle' => 'Introduction']);
        $payload = ['id_module' => $module->id, 'libelle' => 'Contexte'];

        $this->postJson('/api/v1/parametres/cours', $payload)->assertCreated();
        $this->postJson('/api/v1/parametres/cours', $payload)
            ->assertUnprocessable()->assertJsonValidationErrors(['libelle']);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Matiere;
use App\Models\Niveau;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ModuleApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_crud_filtres_et_relations_des_modules(): void
    {
        $role = Role::query()->create(['code' => 'ADMIN', 'libelle' => 'Administrateur']);
        $utilisateur = User::factory()->create(['id_role' => $role->id]);
        Sanctum::actingAs($utilisateur);
        $niveau = Niveau::query()->create(['libelle' => 'Première année', 'code' => 'A1', 'rang' => 1]);
        $matiere = Matiere::query()->create(['code' => 'MAT-001', 'libelle' => 'Bible', 'id_niveau' => $niveau->id]);

        $id = $this->postJson('/api/v1/parametres/modules', [
            'id_matiere' => $matiere->id,
            'code' => 'MOD-001',
            'libelle' => 'Introduction',
            'ordre' => 1,
            'description' => 'Présentation générale.',
        ])->assertCreated()
            ->assertJsonPath('module.matiere.id', $matiere->id)
            ->assertJsonPath('module.matiere.niveau.id', $niveau->id)
            ->assertJsonPath('module.nombre_cours', 0)
            ->json('module.id');

        $this->getJson("/api/v1/parametres/modules?matiere={$matiere->id}&niveau={$niveau->id}&ordre=1&q=Intro")
            ->assertOk()->assertJsonCount(1, 'modules');
        $this->getJson("/api/v1/parametres/modules/{$id}")->assertOk();
        $this->patchJson("/api/v1/parametres/modules/{$id}", ['libelle' => 'Fondements'])
            ->assertOk()->assertJsonPath('module.libelle', 'Fondements');
        $this->deleteJson("/api/v1/parametres/modules/{$id}")->assertOk();
        $this->assertSoftDeleted('modules', ['id' => $id, 'deleted_by' => $utilisateur->id]);
    }

    public function test_libelle_est_unique_dans_une_matiere(): void
    {
        $role = Role::query()->create(['code' => 'ADMIN', 'libelle' => 'Administrateur']);
        Sanctum::actingAs(User::factory()->create(['id_role' => $role->id]));
        $niveau = Niveau::query()->create(['libelle' => 'Première année', 'code' => 'A1', 'rang' => 1]);
        $matiere = Matiere::query()->create(['code' => 'MAT-001', 'libelle' => 'Bible', 'id_niveau' => $niveau->id]);
        $payload = ['id_matiere' => $matiere->id, 'libelle' => 'Introduction'];

        $this->postJson('/api/v1/parametres/modules', $payload)->assertCreated();
        $this->postJson('/api/v1/parametres/modules', $payload)
            ->assertUnprocessable()->assertJsonValidationErrors(['libelle']);
    }
}

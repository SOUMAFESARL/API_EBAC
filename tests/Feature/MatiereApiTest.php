<?php

namespace Tests\Feature;

use App\Models\Niveau;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MatiereApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_crud_et_filtres_des_matieres(): void
    {
        $role = Role::query()->create(['code' => 'ADMIN', 'libelle' => 'Administrateur']);
        $utilisateur = User::factory()->create(['id_role' => $role->id]);
        Sanctum::actingAs($utilisateur);
        $niveau = Niveau::query()->create(['libelle' => 'Première année', 'code' => 'A1', 'rang' => 1]);

        $id = $this->postJson('/api/v1/parametres/matieres', [
            'code' => 'MAT-BIB-001',
            'libelle' => 'Introduction biblique',
            'id_niveau' => $niveau->id,
            'coefficient' => 2.5,
            'type' => 'Fondamentale',
            'note_validation' => 10,
            'obligatoire' => true,
            'active' => true,
            'version' => 1,
        ])->assertCreated()
            ->assertJsonPath('matiere.code', 'MAT-BIB-001')
            ->assertJsonPath('matiere.created_by', $utilisateur->id)
            ->json('matiere.id');

        $this->getJson("/api/v1/parametres/matieres?niveau={$niveau->id}&type=Fondamentale&active=1&obligatoire=1&version=1&q=biblique")
            ->assertOk()->assertJsonCount(1, 'matieres');
        $this->getJson("/api/v1/parametres/matieres/{$id}")
            ->assertOk()->assertJsonPath('matiere.niveau.id', $niveau->id);
        $this->patchJson("/api/v1/parametres/matieres/{$id}", ['coefficient' => 3, 'active' => false])
            ->assertOk()->assertJsonPath('matiere.active', false);
        $this->deleteJson("/api/v1/parametres/matieres/{$id}")->assertOk();
        $this->assertSoftDeleted('matieres', ['id' => $id, 'deleted_by' => $utilisateur->id]);
    }

    public function test_creation_valide_code_niveau_et_valeurs_numeriques(): void
    {
        $role = Role::query()->create(['code' => 'ADMIN', 'libelle' => 'Administrateur']);
        Sanctum::actingAs(User::factory()->create(['id_role' => $role->id]));

        $this->postJson('/api/v1/parametres/matieres', [
            'code' => 'MAT-X', 'libelle' => 'Matière invalide', 'id_niveau' => 999,
            'coefficient' => 0, 'note_validation' => 101, 'version' => 0,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['id_niveau', 'coefficient', 'note_validation', 'version']);
    }
}

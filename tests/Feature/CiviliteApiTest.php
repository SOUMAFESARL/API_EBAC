<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CiviliteApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_utilisateur_authentifie_peut_gerer_les_civilites(): void
    {
        $role = Role::query()->create(['code' => 'ADMIN', 'libelle' => 'Administrateur']);
        $utilisateur = User::factory()->create(['id_role' => $role->id]);
        Sanctum::actingAs($utilisateur);

        $this->getJson('/api/v1/parametres/civilites/create')
            ->assertOk()
            ->assertJsonPath('valeurs_par_defaut.actif', true);

        $id = $this->postJson('/api/v1/parametres/civilites', [
            'code' => 'mme',
            'name' => 'Madame',
            'abreviation' => 'Mme',
            'description' => 'Civilité féminine',
        ])->assertCreated()
            ->assertJsonPath('civilite.code', 'MME')
            ->assertJsonPath('civilite.created_by', $utilisateur->id)
            ->json('civilite.id');

        $this->getJson('/api/v1/parametres/civilites')
            ->assertOk()
            ->assertJsonCount(1, 'civilites')
            ->assertJsonPath('civilites.0.id', $id);

        $this->getJson("/api/v1/parametres/civilites/{$id}")
            ->assertOk()
            ->assertJsonPath('civilite.name', 'Madame');

        $this->getJson("/api/v1/parametres/civilites/{$id}/edit")
            ->assertOk()
            ->assertJsonPath('civilite.id', $id);

        $this->patchJson("/api/v1/parametres/civilites/{$id}", [
            'name' => 'Madame / Mademoiselle',
            'actif' => false,
        ])->assertOk()
            ->assertJsonPath('civilite.name', 'Madame / Mademoiselle')
            ->assertJsonPath('civilite.actif', false)
            ->assertJsonPath('civilite.updated_by', $utilisateur->id);

        $this->deleteJson("/api/v1/parametres/civilites/{$id}")
            ->assertOk()
            ->assertJsonPath('message', 'Civilité supprimée avec succès.');

        $this->assertSoftDeleted('civilite', ['id' => $id, 'deleted_by' => $utilisateur->id]);
    }

    public function test_le_code_doit_etre_unique(): void
    {
        $role = Role::query()->create(['code' => 'ADMIN', 'libelle' => 'Administrateur']);
        Sanctum::actingAs(User::factory()->create(['id_role' => $role->id]));

        $this->postJson('/api/v1/parametres/civilites', ['code' => 'M', 'name' => 'Monsieur'])->assertCreated();
        $this->postJson('/api/v1/parametres/civilites', ['code' => 'm', 'name' => 'Autre'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');
    }

    public function test_les_routes_des_civilites_exigent_une_authentification(): void
    {
        $this->getJson('/api/v1/parametres/civilites')->assertUnauthorized();
        $this->postJson('/api/v1/parametres/civilites', [])->assertUnauthorized();
    }
}

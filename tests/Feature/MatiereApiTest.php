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
            'volume_horaire' => 30,
            'type' => 'Fondamentale',
            'note_validation' => 10,
            'obligatoire' => true,
            'active' => true,
            'version' => 1,
        ])->assertCreated()
            ->assertJsonPath('matiere.code', 'MAT-BIB-001')
            ->assertJsonPath('matiere.volume_horaire', '30.00')
            ->assertJsonPath('matiere.created_by', $utilisateur->id)
            ->json('matiere.id');

        $this->getJson("/api/v1/parametres/matieres?niveau={$niveau->id}&type=Fondamentale&active=1&obligatoire=1&version=1&q=biblique")
            ->assertOk()->assertJsonCount(1, 'matieres');
        $this->getJson("/api/v1/parametres/matieres/{$id}")
            ->assertOk()->assertJsonPath('matiere.niveau.id', $niveau->id);
        $this->patchJson("/api/v1/parametres/matieres/{$id}", ['coefficient' => 3, 'volume_horaire' => 36, 'active' => false])
            ->assertOk()
            ->assertJsonPath('matiere.volume_horaire', '36.00')
            ->assertJsonPath('matiere.active', false);
        $this->deleteJson("/api/v1/parametres/matieres/{$id}")->assertOk();
        $this->assertSoftDeleted('matieres', ['id' => $id, 'deleted_by' => $utilisateur->id]);
    }

    public function test_creation_valide_code_niveau_et_valeurs_numeriques(): void
    {
        $role = Role::query()->create(['code' => 'ADMIN', 'libelle' => 'Administrateur']);
        Sanctum::actingAs(User::factory()->create(['id_role' => $role->id]));

        $this->postJson('/api/v1/parametres/matieres', [
            'code' => 'MAT-X', 'libelle' => 'Matière invalide', 'id_niveau' => 999,
            'coefficient' => 0, 'volume_horaire' => -1, 'note_validation' => 101, 'version' => 0,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['id_niveau', 'coefficient', 'volume_horaire', 'note_validation', 'version']);
    }

    public function test_une_matiere_est_creee_avec_plusieurs_modules_et_cours(): void
    {
        $role = Role::query()->create(['code' => 'ADMIN', 'libelle' => 'Administrateur']);
        $utilisateur = User::factory()->create(['id_role' => $role->id]);
        Sanctum::actingAs($utilisateur);
        $niveau = Niveau::query()->create(['libelle' => 'Première année', 'code' => 'A1', 'rang' => 1]);

        $id = $this->postJson('/api/v1/parametres/matieres', [
            'code' => 'MAT-CHRISTO-001',
            'libelle' => 'Doctrine chrétienne',
            'id_niveau' => $niveau->id,
            'volume_horaire' => 45,
            'modules' => [
                [
                    'libelle' => 'Théologie',
                    'cours' => [
                        ['libelle' => 'La Trinité', 'coefficient' => 1],
                        ['libelle' => 'La révélation', 'coefficient' => 2],
                    ],
                ],
                [
                    'libelle' => 'Christologie',
                    'cours' => [
                        ['libelle' => 'La personne du Christ', 'coefficient' => 1.5],
                        ['libelle' => 'L’œuvre du Christ', 'coefficient' => 1],
                    ],
                ],
            ],
        ])->assertCreated()
            ->assertJsonCount(2, 'matiere.modules')
            ->assertJsonCount(2, 'matiere.modules.0.cours')
            ->assertJsonCount(2, 'matiere.modules.1.cours')
            ->assertJsonPath('matiere.modules.0.ordre', 1)
            ->assertJsonPath('matiere.modules.1.ordre', 2)
            ->assertJsonPath('matiere.modules.1.cours.0.coefficient', '1.50')
            ->assertJsonPath('matiere.nombre_modules', 2)
            ->json('matiere.id');

        $this->getJson('/api/v1/parametres/matieres')
            ->assertOk()
            ->assertJsonCount(1, 'matieres')
            ->assertJsonPath('matieres.0.id', $id)
            ->assertJsonPath('matieres.0.niveau.id', $niveau->id)
            ->assertJsonPath('matieres.0.nombre_modules', 2)
            ->assertJsonCount(2, 'matieres.0.modules')
            ->assertJsonCount(2, 'matieres.0.modules.0.cours')
            ->assertJsonPath('matieres.0.modules.1.libelle', 'Christologie');

        $this->getJson("/api/v1/parametres/matieres/{$id}")
            ->assertOk()
            ->assertJsonPath('matiere.niveau.id', $niveau->id)
            ->assertJsonPath('matiere.nombre_modules', 2)
            ->assertJsonCount(2, 'matiere.modules')
            ->assertJsonCount(2, 'matiere.modules.1.cours')
            ->assertJsonPath('matiere.modules.0.cours.1.libelle', 'La révélation');

        $this->assertDatabaseCount('matieres', 1);
        $this->assertDatabaseCount('modules', 2);
        $this->assertDatabaseCount('cours', 4);
    }

    public function test_un_module_imbrique_exige_au_moins_un_cours(): void
    {
        $role = Role::query()->create(['code' => 'ADMIN', 'libelle' => 'Administrateur']);
        Sanctum::actingAs(User::factory()->create(['id_role' => $role->id]));
        $niveau = Niveau::query()->create(['libelle' => 'Première année', 'code' => 'A1', 'rang' => 1]);

        $this->postJson('/api/v1/parametres/matieres', [
            'code' => 'MAT-INVALIDE',
            'libelle' => 'Matière invalide',
            'id_niveau' => $niveau->id,
            'modules' => [['libelle' => 'Module sans cours', 'cours' => []]],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['modules.0.cours']);

        $this->assertDatabaseCount('matieres', 0);
    }
}

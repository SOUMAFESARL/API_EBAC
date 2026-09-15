<?php

namespace Tests\Feature;

use App\Models\AnneeAcademique;
use App\Models\Niveau;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MatiereApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_enseignant_synchronise_sans_annee_et_changements_historises(): void
    {
        $role = Role::create(['code' => 'ADMIN', 'libelle' => 'Admin']);
        $admin = User::factory()->create(['id_role' => $role->id]);
        Sanctum::actingAs($admin);
        $role = Role::create(['code' => 'ENSEIGNANT', 'libelle' => 'Enseignant']);
        $enseignant = User::factory()->create(['id_role' => $role->id]);
        $suivant = User::factory()->create(['id_role' => $role->id]);
        $niveau = Niveau::create(['code' => 'N1', 'libelle' => 'Niveau 1', 'rang' => 1]);
        $id = $this->postJson('/api/v1/parametres/matieres', ['code' => 'MAT-SYNC', 'libelle' => 'Matière',
            'id_niveau' => $niveau->id, 'enseignant_id' => $enseignant->id])->assertCreated()->json('matiere.id');
        $url = '/api/v1/parametres/matieres/'.$id;
        $this->assertDatabaseCount('annees_academiques', 0);
        $this->assertDatabaseHas('affectations_enseignants', ['id_matiere' => $id, 'enseignant_id' => $enseignant->id,
            'id_annee_academique' => null, 'portee' => 'matiere', 'date_fin' => null]);
        $this->getJson('/api/v1/administration/affectations-enseignants')->assertOk()
            ->assertJsonCount(1, 'affectations')->assertJsonMissingPath('affectations.0.id_annee_academique');
        $this->patchJson($url, ['enseignant_id' => $enseignant->id])->assertOk();
        $this->assertDatabaseCount('affectations_enseignants', 1);

        Sanctum::actingAs($enseignant);
        $this->getJson('/api/v1/enseignant/mes-cours')->assertOk()->assertJsonPath('matieres.0.id', $id);
        $this->getJson('/api/v1/enseignant/mes-cours/'.$id)->assertOk()->assertJsonPath('matiere.id', $id);
        $annee = AnneeAcademique::create(['libelle' => 'Autre année', 'date_debut' => '2030-09-01', 'date_fin' => '2031-07-31']);
        $this->getJson('/api/v1/enseignant/mes-cours?id_annee_academique='.$annee->id)->assertOk()->assertJsonPath('matieres.0.id', $id);

        Sanctum::actingAs($admin);
        $this->patchJson($url, ['enseignant_id' => $suivant->id])->assertOk()->assertJsonPath('matiere.enseignant.id', $suivant->id);
        $this->assertDatabaseCount('affectations_enseignants', 2);
        $this->assertDatabaseHas('affectations_enseignants', ['enseignant_id' => $enseignant->id, 'date_fin' => now()->toDateString()]);
        $this->patchJson($url, ['enseignant_id' => null])->assertOk()->assertJsonPath('matiere.enseignant', null);
        $this->assertDatabaseHas('affectations_enseignants', ['enseignant_id' => $suivant->id, 'date_fin' => now()->toDateString()]);
        $this->getJson('/api/v1/administration/affectations-enseignants?statut=en_cours')->assertOk()->assertJsonCount(0, 'affectations');
        $this->getJson('/api/v1/administration/affectations-enseignants?statut=terminee')->assertOk()->assertJsonCount(2, 'affectations');
        Sanctum::actingAs($suivant);
        $this->getJson('/api/v1/enseignant/mes-cours/'.$id)->assertNotFound();
    }

    public function test_enseignant_inactif_ne_cree_ni_matiere_ni_affectation(): void
    {
        $role = Role::create(['code' => 'ADMIN', 'libelle' => 'Admin']);
        Sanctum::actingAs(User::factory()->create(['id_role' => $role->id]));
        $role = Role::create(['code' => 'ENSEIGNANT', 'libelle' => 'Enseignant']);
        $enseignant = User::factory()->create(['id_role' => $role->id, 'is_active' => false]);
        $niveau = Niveau::create(['code' => 'N1', 'libelle' => 'Niveau 1', 'rang' => 1]);
        $this->postJson('/api/v1/parametres/matieres', ['code' => 'MAT-INACTIVE', 'libelle' => 'Matière',
            'id_niveau' => $niveau->id, 'enseignant_id' => $enseignant->id])->assertUnprocessable()->assertJsonValidationErrors('enseignant_id');
        $this->assertDatabaseCount('matieres', 0);
        $this->assertDatabaseCount('affectations_enseignants', 0);
    }

    public function test_crud_et_filtres_des_matieres(): void
    {
        $role = Role::query()->create(['code' => 'ADMIN', 'libelle' => 'Administrateur']);
        $utilisateur = User::factory()->create(['id_role' => $role->id]);
        Sanctum::actingAs($utilisateur);
        $roleEnseignant = Role::query()->create(['code' => 'ENSEIGNANT', 'libelle' => 'Enseignant']);
        $enseignant = User::factory()->create(['id_role' => $roleEnseignant->id]);
        $niveau = Niveau::query()->create(['libelle' => 'Première année', 'code' => 'A1', 'rang' => 1]);

        $id = $this->postJson('/api/v1/parametres/matieres', [
            'code' => 'MAT-BIB-001',
            'libelle' => 'Introduction biblique',
            'id_niveau' => $niveau->id,
            'enseignant_id' => $enseignant->id,
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
            ->assertJsonPath('matiere.enseignant.id', $enseignant->id)
            ->assertJsonPath('matiere.enseignant.role.code', 'ENSEIGNANT')
            ->assertJsonPath('matiere.created_by', $utilisateur->id)
            ->json('matiere.id');

        $this->getJson("/api/v1/parametres/matieres?niveau={$niveau->id}&enseignant_id={$enseignant->id}&type=Fondamentale&active=1&obligatoire=1&version=1&q=biblique")
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
        $utilisateur = User::factory()->create(['id_role' => $role->id]);
        Sanctum::actingAs($utilisateur);

        $this->postJson('/api/v1/parametres/matieres', [
            'code' => 'MAT-X', 'libelle' => 'Matière invalide', 'id_niveau' => 999,
            'enseignant_id' => $utilisateur->id,
            'coefficient' => 0, 'volume_horaire' => -1, 'note_validation' => 101, 'version' => 0,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['id_niveau', 'enseignant_id', 'coefficient', 'volume_horaire', 'note_validation', 'version']);
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
            ->assertJsonPath('matiere.modules.0.libelle', 'Théologie')
            ->assertJsonPath('matiere.modules.1.libelle', 'Christologie')
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

    public function test_associe_et_synchronise_plusieurs_modules_calendrier(): void
    {
        $role = Role::create(['code' => 'ADMIN', 'libelle' => 'Administrateur']);
        Sanctum::actingAs(User::factory()->create(['id_role' => $role->id]));
        $niveau = Niveau::create(['libelle' => 'Première année', 'code' => 'A1', 'rang' => 1]);
        $annee = AnneeAcademique::create(['libelle' => '2026-2027', 'date_debut' => '2026-09-01', 'date_fin' => '2027-07-31']);
        $calendrier = $annee->calendrier()->create([]);
        $module1 = $calendrier->modules()->create(['libelle' => 'Module 1', 'ordre' => 1, 'date_debut' => '2026-09-01', 'date_fin' => '2026-12-20']);
        $module2 = $calendrier->modules()->create(['libelle' => 'Module 2', 'ordre' => 2, 'date_debut' => '2027-01-01', 'date_fin' => '2027-03-31']);

        $id = $this->postJson('/api/v1/parametres/matieres', [
            'code' => 'MAT-CAL', 'libelle' => 'Matière planifiée', 'id_niveau' => $niveau->id,
            'module_calendrier_id' => [$module1->id, $module2->id],
        ])->assertCreated()->assertJsonPath('matiere.module_calendrier_id', [$module1->id, $module2->id])
            ->assertJsonCount(2, 'matiere.modules_calendrier')->json('matiere.id');
        $this->assertDatabaseHas('matiere_module_calendrier', ['id_matiere' => $id, 'id_module_calendrier' => $module1->id]);
        $this->getJson('/api/v1/parametres/matieres?module_calendrier_id[]='.$module2->id)->assertOk()->assertJsonCount(1, 'matieres');

        $this->patchJson('/api/v1/parametres/matieres/'.$id, ['module_calendrier_id' => [$module2->id]])
            ->assertOk()->assertJsonPath('matiere.module_calendrier_id', [$module2->id]);
        $this->assertDatabaseMissing('matiere_module_calendrier', ['id_matiere' => $id, 'id_module_calendrier' => $module1->id]);
        $this->patchJson('/api/v1/parametres/matieres/'.$id, ['module_calendrier_id' => []])
            ->assertOk()->assertJsonPath('matiere.module_calendrier_id', []);
        $this->assertDatabaseMissing('matiere_module_calendrier', ['id_matiere' => $id]);
    }

    public function test_refuse_modules_calendrier_invalides_ou_dupliques(): void
    {
        $role = Role::create(['code' => 'ADMIN', 'libelle' => 'Administrateur']);
        Sanctum::actingAs(User::factory()->create(['id_role' => $role->id]));
        $niveau = Niveau::create(['libelle' => 'Première année', 'code' => 'A1', 'rang' => 1]);
        $base = ['code' => 'MAT-CAL', 'libelle' => 'Matière planifiée', 'id_niveau' => $niveau->id];
        $this->postJson('/api/v1/parametres/matieres', [...$base, 'module_calendrier_id' => [999]])
            ->assertUnprocessable()->assertJsonValidationErrors('module_calendrier_id.0');
        $this->postJson('/api/v1/parametres/matieres', [...$base, 'module_calendrier_id' => [1, 1]])
            ->assertUnprocessable()->assertJsonValidationErrors('module_calendrier_id.0');
    }
}

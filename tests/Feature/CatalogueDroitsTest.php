<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\CatalogueDroitsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Droits module · sous-module · action (demande front §69) et droits du
 * compte connecté dans `GET /auth/profil` (§4).
 */
class CatalogueDroitsTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_migration_seme_les_112_droits_du_catalogue(): void
    {
        $this->assertSame(112, Permission::query()->whereNotNull('module')->count());

        $this->assertDatabaseHas('permissions', [
            'code' => 'planification.calendrier-academique.activer-annee',
            'libelle' => 'Calendrier académique — Activer une année',
            'module' => 'planification',
            'sous_module' => 'calendrier-academique',
            'action' => 'activer-annee',
        ]);

        foreach (CatalogueDroitsSeeder::droits() as $droit) {
            $this->assertMatchesRegularExpression('/^[a-z-]+\.[a-z-]+\.[a-z-]+$/', $droit['code']);
        }
    }

    public function test_le_seeder_est_idempotent_et_ne_touche_pas_aux_droits_des_roles(): void
    {
        $this->sansDroitsParDefaut();
        $admin = Role::query()->create(['code' => 'ADMIN', 'libelle' => 'Administrateur']);
        $direction = Role::query()->create(['code' => 'DIRECTION', 'libelle' => 'Direction']);
        $droit = Permission::query()->where('code', 'notes.validation-notes.valider')->firstOrFail();
        $direction->permissions()->attach($droit->id, ['actif' => true]);

        $droit->update(['libelle' => 'Libellé modifié à la main']);
        Permission::query()->where('code', 'accueil.tableau-de-bord.consulter')->firstOrFail()->delete();

        (new CatalogueDroitsSeeder)->run();
        (new CatalogueDroitsSeeder)->run();

        $this->assertSame(112, Permission::query()->whereNotNull('module')->count());
        $this->assertSame(1, Permission::query()->where('code', 'notes.validation-notes.valider')->count());
        $this->assertSame('Validation des notes — Valider', $droit->fresh()->libelle);
        $this->assertNotNull(Permission::query()->where('code', 'accueil.tableau-de-bord.consulter')->first());
        $this->assertSame(['notes.validation-notes.valider'], $direction->permissions()->pluck('code')->all());
        $this->assertSame(112, $admin->permissions()->whereNotNull('module')->count());
    }

    public function test_la_liste_des_permissions_sert_tout_le_catalogue_en_une_page_avec_son_rangement(): void
    {
        $role = Role::query()->create(['code' => 'ADMIN', 'libelle' => 'Administrateur']);
        Sanctum::actingAs(User::factory()->create(['id_role' => $role->id]));

        $reponse = $this->getJson('/api/v1/administration/permissions?par_page=200')->assertOk();

        $this->assertSame(200, $reponse->json('per_page'));
        $this->assertCount(Permission::query()->count(), $reponse->json('data'));
        $reponse->assertJsonFragment([
            'code' => 'gestion-academique.etudiants.consulter',
            'module' => 'gestion-academique',
            'sous_module' => 'etudiants',
            'action' => 'consulter',
        ]);

        $this->getJson('/api/v1/administration/permissions?par_page=200&module=finances')
            ->assertOk()
            ->assertJsonCount(9, 'data');
    }

    public function test_un_role_recoit_des_droits_du_catalogue_par_le_remplacement_complet(): void
    {
        $admin = Role::query()->create(['code' => 'ADMIN', 'libelle' => 'Administrateur']);
        $gestionnaire = Role::query()->create(['code' => 'GESTIONNAIRE', 'libelle' => 'Gestionnaire']);
        Sanctum::actingAs(User::factory()->create(['id_role' => $admin->id]));

        $ids = Permission::query()->where('module', 'finances')->pluck('id')->all();

        $this->putJson("/api/v1/administration/roles/{$gestionnaire->id}/permissions", ['permission_ids' => $ids])
            ->assertOk()
            ->assertJsonCount(9, 'role.permissions');
    }

    public function test_le_profil_sert_les_droits_actifs_du_role(): void
    {
        $this->sansDroitsParDefaut();
        $role = Role::query()->create(['code' => 'GESTIONNAIRE', 'libelle' => 'Gestionnaire']);
        $actif = Permission::query()->where('code', 'finances.dossiers.consulter')->firstOrFail();
        $inactif = Permission::query()->where('code', 'finances.dossiers.encaisser')->firstOrFail();
        $role->permissions()->attach([
            $actif->id => ['actif' => true],
            $inactif->id => ['actif' => false],
        ]);
        Sanctum::actingAs(User::factory()->create(['id_role' => $role->id]));

        $this->getJson('/api/v1/auth/profil')
            ->assertOk()
            ->assertJsonPath('utilisateur.role.code', 'GESTIONNAIRE')
            ->assertJsonPath('utilisateur.role.permissions', ['finances.dossiers.consulter']);
    }

    public function test_le_profil_sert_tous_les_droits_a_l_administrateur(): void
    {
        $role = Role::query()->create(['code' => 'ADMIN', 'libelle' => 'Administrateur']);
        Sanctum::actingAs(User::factory()->create(['id_role' => $role->id]));

        $permissions = $this->getJson('/api/v1/auth/profil')->assertOk()->json('utilisateur.role.permissions');

        $this->assertCount(Permission::query()->count(), $permissions);
        $this->assertContains('securite-comptes.roles-droits.modifier', $permissions);
    }

    public function test_le_profil_ne_sert_aucun_droit_a_un_role_inactif(): void
    {
        $this->sansDroitsParDefaut();
        $role = Role::query()->create(['code' => 'DIRECTION', 'libelle' => 'Direction', 'actif' => false]);
        $role->permissions()->attach(Permission::query()->where('code', 'notes.validation-notes.valider')->value('id'), ['actif' => true]);
        Sanctum::actingAs(User::factory()->create(['id_role' => $role->id]));

        $this->getJson('/api/v1/auth/profil')
            ->assertOk()
            ->assertJsonPath('utilisateur.role.permissions', []);
    }

    public function test_les_autres_lectures_d_utilisateur_ne_servent_pas_les_droits(): void
    {
        $role = Role::query()->create(['code' => 'ADMIN', 'libelle' => 'Administrateur']);
        $compte = User::factory()->create(['id_role' => $role->id]);
        Sanctum::actingAs($compte);

        $role = $this->getJson("/api/v1/administration/comptes/{$compte->id}")->assertOk()->json('compte.role');

        $this->assertIsArray($role);
        $this->assertSame('ADMIN', $role['code']);
        $this->assertArrayNotHasKey('permissions', $role);
    }
}

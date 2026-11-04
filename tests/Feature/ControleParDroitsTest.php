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
 * Les routes de gestion obéissent aux droits cochés dans « Rôles & droits » :
 * cocher ouvre, décocher ferme, sans redéploiement.
 */
class ControleParDroitsTest extends TestCase
{
    use RefreshDatabase;

    private function compte(string $codeRole): User
    {
        $role = Role::query()->firstOrCreate(['code' => $codeRole], ['libelle' => $codeRole]);
        $utilisateur = User::factory()->create(['id_role' => $role->id]);
        Sanctum::actingAs($utilisateur);

        return $utilisateur;
    }

    private function retirer(string $codeRole, string $droit): void
    {
        $role = Role::query()->where('code', $codeRole)->firstOrFail();
        $role->permissions()->detach(Permission::query()->where('code', $droit)->value('id'));
    }

    public function test_les_roles_semes_recoivent_leurs_droits_par_defaut(): void
    {
        foreach (['DIRECTION' => 26, 'SECRETARIAT' => 38, 'SECRETAIRE_ACADEMIQUE' => 38, 'GESTIONNAIRE' => 11, 'ENSEIGNANT' => 0, 'PERSONNALISE' => 0] as $code => $attendus) {
            $role = Role::query()->create(['code' => $code, 'libelle' => $code]);
            $this->assertSame($attendus, $role->permissions()->whereNotNull('module')->count(), $code);
        }
    }

    public function test_la_migration_pose_les_droits_par_defaut_des_roles_deja_en_base(): void
    {
        // Cas de la production : les rôles existent avant la migration.
        $this->sansDroitsParDefaut();
        foreach (['ADMIN', 'DIRECTION', 'ENSEIGNANT', 'GESTIONNAIRE', 'SECRETARIAT', 'SECRETAIRE_ACADEMIQUE'] as $code) {
            Role::query()->create(['code' => $code, 'libelle' => $code]);
        }

        (new CatalogueDroitsSeeder)->run();

        $compter = fn (string $code) => Role::query()->where('code', $code)->firstOrFail()
            ->permissions()->whereNotNull('module')->count();
        $this->assertSame(103, $compter('ADMIN'));
        $this->assertSame(26, $compter('DIRECTION'));
        $this->assertSame(0, $compter('ENSEIGNANT'));
        $this->assertSame(11, $compter('GESTIONNAIRE'));
        $this->assertSame(38, $compter('SECRETARIAT'));
        $this->assertSame(38, $compter('SECRETAIRE_ACADEMIQUE'));
    }

    public function test_les_droits_par_defaut_n_ecrasent_pas_un_reglage(): void
    {
        $this->sansDroitsParDefaut();
        $role = Role::query()->create(['code' => 'GESTIONNAIRE', 'libelle' => 'Gestionnaire']);
        $role->permissions()->attach(Permission::query()->where('code', 'finances.dossiers.consulter')->value('id'), ['actif' => true]);

        $this->assertFalse(CatalogueDroitsSeeder::poserDroitsParDefaut($role));
        $this->assertSame(['finances.dossiers.consulter'], $role->permissions()->pluck('code')->all());
    }

    public function test_decocher_un_droit_ferme_la_route_et_le_recocher_la_rouvre(): void
    {
        $this->compte('SECRETAIRE_ACADEMIQUE');
        $this->getJson('/api/v1/administration/registre-etudiants')->assertOk();

        $this->retirer('SECRETAIRE_ACADEMIQUE', 'gestion-academique.etudiants.consulter');
        $this->getJson('/api/v1/administration/registre-etudiants')
            ->assertForbidden()
            ->assertJsonPath('permissions_requises', ['gestion-academique.etudiants.consulter']);

        $this->accorderDroits('SECRETAIRE_ACADEMIQUE', 'gestion-academique.etudiants.consulter');
        $this->getJson('/api/v1/administration/registre-etudiants')->assertOk();
    }

    public function test_un_role_personnalise_n_accede_qu_a_ce_qui_est_coche(): void
    {
        $this->compte('SCOLARITE');
        $this->getJson('/api/v1/administration/registre-etudiants')->assertForbidden();
        $this->getJson('/api/v1/administration/notes-transmises')->assertForbidden();

        $this->accorderDroits('SCOLARITE', 'gestion-academique.etudiants.consulter');
        $this->getJson('/api/v1/administration/registre-etudiants')->assertOk();
        $this->getJson('/api/v1/administration/notes-transmises')->assertForbidden();
        $this->patchJson('/api/v1/administration/registre-etudiants/1', [])->assertForbidden();
    }

    public function test_la_lecture_d_un_referentiel_garde_ses_lecteurs_et_s_ouvre_au_droit(): void
    {
        $this->compte('DIRECTION');
        $this->getJson('/api/v1/parametres/salles')->assertOk();

        $this->compte('SCOLARITE');
        $this->getJson('/api/v1/parametres/salles')->assertForbidden();
        $this->accorderDroits('SCOLARITE', 'planification.salles.consulter');
        $this->getJson('/api/v1/parametres/salles')->assertOk();
        $this->postJson('/api/v1/parametres/salles', ['nom' => 'Salle A', 'code' => 'SA'])->assertForbidden();
    }

    public function test_les_ecritures_des_referentiels_exigent_le_droit(): void
    {
        $this->compte('ETUDIANT');
        $this->getJson('/api/v1/parametres/niveaux')->assertOk();
        $this->postJson('/api/v1/parametres/niveaux', ['code' => 'N9', 'libelle' => 'Niveau 9'])->assertForbidden();
        $this->postJson('/api/v1/parametres/promotions', [])->assertForbidden();
        $this->postJson('/api/v1/parametres/matieres', [])->assertForbidden();
        $this->deleteJson('/api/v1/parametres/niveaux/1')->assertForbidden();
    }

    public function test_ouvrir_l_acces_ne_cree_qu_un_compte_etudiant(): void
    {
        $etudiant = Role::query()->create(['code' => 'ETUDIANT', 'libelle' => 'Étudiant']);
        $direction = Role::query()->create(['code' => 'DIRECTION', 'libelle' => 'Direction']);
        $this->compte('SECRETAIRE_ACADEMIQUE');

        $this->postJson('/api/v1/administration/comptes', ['id_role' => $direction->id])
            ->assertForbidden()
            ->assertJsonPath('permissions_requises', ['securite-comptes.utilisateurs.creer']);
        $this->postJson('/api/v1/administration/comptes', ['id_role' => $etudiant->id])
            ->assertUnprocessable();
    }

    public function test_un_role_inactif_est_refuse_meme_avec_ses_droits(): void
    {
        $this->compte('SECRETAIRE_ACADEMIQUE');
        Role::query()->where('code', 'SECRETAIRE_ACADEMIQUE')->update(['actif' => false]);

        $this->getJson('/api/v1/administration/registre-etudiants')->assertForbidden();
    }

    public function test_l_administrateur_passe_partout(): void
    {
        $this->compte('ADMIN');
        $this->getJson('/api/v1/administration/registre-etudiants')->assertOk();
        $this->getJson('/api/v1/administration/notes-transmises')->assertOk();
        $this->getJson('/api/v1/parametres/salles')->assertOk();
    }
}

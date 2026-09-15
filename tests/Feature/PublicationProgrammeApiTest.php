<?php

namespace Tests\Feature;

use App\Models\AnneeAcademique;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PublicationProgrammeApiTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/v1/administration/publication-programme';

    private function contexte(): array
    {
        $role = Role::create(['code' => 'ADMIN', 'libelle' => 'Administrateur']);
        $admin = User::factory()->create(['id_role' => $role->id]);
        Sanctum::actingAs($admin);
        $annee = AnneeAcademique::create(['libelle' => '2026-2027', 'date_debut' => '2026-09-01', 'date_fin' => '2027-07-31', 'active' => true]);
        $calendrier = $annee->calendrier()->create([]);
        // Des identifiants différents détectent une confusion calendrier/module.
        $premier = $calendrier->modules()->make(['libelle' => 'Module 1', 'ordre' => 1, 'date_debut' => '2026-09-01', 'date_fin' => '2026-12-20']);
        $premier->id = 101;
        $premier->save();
        $second = $calendrier->modules()->create(['libelle' => 'Module 2', 'ordre' => 2, 'date_debut' => '2027-01-11', 'date_fin' => '2027-06-30']);
        foreach ([$premier, $second] as $module) {
            $calendrier->evenements()->create(['id_module_calendrier' => $module->id, 'type' => 'examen',
                'libelle' => 'Examen '.$module->libelle, 'date_debut' => $module->date_fin, 'date_fin' => $module->date_fin]);
        }
        $calendrier->evenements()->create(['id_module_calendrier' => $premier->id, 'type' => 'rattrapage',
            'libelle' => 'Session complémentaire', 'date_debut' => '2027-01-05', 'date_fin' => '2027-01-10']);
        foreach (['jour_ferie' => ['2026-11-01', '2026-11-01'], 'conge' => ['2026-12-21', '2027-01-04'], 'grandes_vacances' => ['2027-08-01', '2027-08-31']] as $type => [$debut, $fin]) {
            $calendrier->evenements()->create(['type' => $type, 'libelle' => $type, 'date_debut' => $debut, 'date_fin' => $fin]);
        }

        return compact('admin', 'annee', 'calendrier', 'premier', 'second');
    }

    public function test_publie_le_calendrier_complet_sans_creneau_pour_tous_les_roles(): void
    {
        $data = $this->contexte();
        $id = $data['calendrier']->id;
        $this->getJson(self::URL)->assertOk()->assertJsonCount(1, 'programmes')
            ->assertJsonPath('programmes.0.calendrier.id', $id)->assertJsonPath('programmes.0.statut', 'non_publie');
        $this->postJson(self::URL.'/'.$id.'/publier')->assertOk()
            ->assertJsonPath('programme.calendrier.id', $id)->assertJsonCount(2, 'programme.calendrier.modules')
            ->assertJsonPath('programme.statut', 'publie')->assertJsonPath('programme.version', 1);
        $this->assertDatabaseCount('publication_calendriers', 1);
        $this->assertDatabaseCount('creneaux', 0);
        $this->assertSame($data['premier']->fresh()->publication->id, $data['second']->fresh()->publication->id);

        foreach (['ENSEIGNANT', 'ETUDIANT', 'SECRETAIRE_ACADEMIQUE', 'GESTIONNAIRE'] as $code) {
            $role = Role::create(['code' => $code, 'libelle' => $code]);
            Sanctum::actingAs(User::factory()->create(['id_role' => $role->id]));
            $this->getJson('/api/v1/programme')->assertOk()
                ->assertJsonPath('calendrier.id', $id)->assertJsonCount(2, 'calendrier.modules')
                ->assertJsonPath('calendrier.modules.0.id', $data['premier']->id)
                ->assertJsonPath('calendrier.modules.1.id', $data['second']->id)
                ->assertJsonCount(1, 'calendrier.modules.0.examens')->assertJsonCount(1, 'calendrier.modules.1.examens')
                ->assertJsonCount(1, 'calendrier.modules.0.rattrapages')
                ->assertJsonCount(1, 'calendrier.jours_feries')->assertJsonCount(1, 'calendrier.conges')
                ->assertJsonPath('calendrier.grandes_vacances.libelle', 'grandes_vacances');
        }
    }

    public function test_retrait_global_idempotence_et_republication(): void
    {
        $data = $this->contexte();
        $url = self::URL.'/'.$data['calendrier']->id;
        $this->postJson($url.'/retire')->assertUnprocessable()->assertJsonValidationErrors('calendrier');
        $this->postJson($url.'/publier')->assertOk()->assertJsonPath('programme.version', 1);
        $this->postJson($url.'/publier')->assertOk()->assertJsonPath('programme.version', 1);
        $this->postJson($url.'/retire')->assertOk()->assertJsonPath('programme.visible_utilisateurs', false);
        $this->assertSame('non_publie', $data['premier']->fresh()->publication->statut);
        $this->assertSame('non_publie', $data['second']->fresh()->publication->statut);
        $role = Role::create(['code' => 'ENSEIGNANT', 'libelle' => 'Enseignant']);
        Sanctum::actingAs(User::factory()->create(['id_role' => $role->id]));
        $this->getJson('/api/v1/programme')->assertOk()->assertJsonPath('calendrier', null);
        $this->getJson('/api/v1/enseignant/emploi-du-temps')->assertOk()->assertJsonCount(0, 'modules_disponibles');
        Sanctum::actingAs($data['admin']);
        $this->postJson($url.'/publier')->assertOk()->assertJsonPath('programme.version', 2);
    }

    public function test_un_calendrier_non_publie_est_masque_pour_tous_les_roles(): void
    {
        $this->contexte();
        foreach (['ENSEIGNANT', 'ETUDIANT', 'SECRETAIRE_ACADEMIQUE', 'GESTIONNAIRE'] as $code) {
            $role = Role::create(['code' => $code, 'libelle' => $code]);
            Sanctum::actingAs(User::factory()->create(['id_role' => $role->id]));
            $this->getJson('/api/v1/programme')->assertOk()->assertJsonPath('calendrier', null)
                ->assertJsonPath('publication.statut', 'non_publie');
        }
    }

    public function test_les_calendriers_des_autres_annees_restent_non_publies(): void
    {
        $data = $this->contexte();
        $autreAnnee = AnneeAcademique::create(['libelle' => '2027-2028', 'date_debut' => '2027-09-01', 'date_fin' => '2028-07-31']);
        $autre = $autreAnnee->calendrier()->create([]);
        $autre->modules()->create(['libelle' => 'Autre module', 'ordre' => 1, 'date_debut' => '2027-09-01', 'date_fin' => '2027-12-20']);
        $this->postJson(self::URL.'/'.$data['calendrier']->id.'/publier')->assertOk();
        $this->getJson(self::URL.'?id_annee_academique='.$autreAnnee->id)->assertOk()->assertJsonPath('programmes.0.statut', 'non_publie');
        $role = Role::create(['code' => 'ENSEIGNANT', 'libelle' => 'Enseignant']);
        Sanctum::actingAs(User::factory()->create(['id_role' => $role->id]));
        $this->getJson('/api/v1/programme?id_annee_academique='.$autreAnnee->id)->assertOk()->assertJsonPath('calendrier', null);
        $this->getJson('/api/v1/programme?id_module_calendrier='.$data['premier']->id)->assertUnprocessable()->assertJsonValidationErrors('id_module_calendrier');
    }

    public function test_publication_exige_admin_et_identifiant_du_calendrier(): void
    {
        $data = $this->contexte();
        $this->postJson(self::URL.'/'.$data['premier']->id.'/publier')->assertNotFound();
        $this->getJson(self::URL.'?id_module_calendrier='.$data['premier']->id)->assertUnprocessable();
        $role = Role::create(['code' => 'ENSEIGNANT', 'libelle' => 'Enseignant']);
        Sanctum::actingAs(User::factory()->create(['id_role' => $role->id]));
        $this->postJson(self::URL.'/'.$data['calendrier']->id.'/publier')->assertForbidden();
        Sanctum::actingAs($data['admin']);
        $data['annee']->delete();
        $this->postJson(self::URL.'/'.$data['calendrier']->id.'/publier')->assertNotFound();
        $this->assertDatabaseCount('publication_calendriers', 0);
    }

    public function test_calendrier_sans_module_non_publiable(): void
    {
        $data = $this->contexte();
        $data['calendrier']->modules()->delete();
        $this->postJson(self::URL.'/'.$data['calendrier']->id.'/publier')->assertUnprocessable()->assertJsonValidationErrors('calendrier');
    }

    public function test_modification_des_evenements_ou_modules_retire_la_publication_globale(): void
    {
        $data = $this->contexte();
        $url = self::URL.'/'.$data['calendrier']->id.'/publier';
        $this->postJson($url)->assertOk();
        $event = $data['calendrier']->evenements()->where('type', 'jour_ferie')->first();
        $this->patchJson('/api/v1/parametres/evenements-calendrier/'.$event->id, ['libelle' => 'Fête modifiée'])->assertOk();
        $this->assertDatabaseHas('publication_calendriers', ['id_calendrier' => $data['calendrier']->id, 'statut' => 'non_publie', 'retire_par' => $data['admin']->id]);
        $this->postJson($url)->assertOk();
        $this->deleteJson('/api/v1/parametres/evenements-calendrier/'.$event->id)->assertOk();
        $this->assertSame('non_publie', $data['calendrier']->fresh()->publication->statut);
        $this->postJson($url)->assertOk();
        $this->patchJson('/api/v1/parametres/modules-calendrier/'.$data['second']->id, ['libelle' => 'Nouveau nom'])->assertOk();
        $this->assertSame('non_publie', $data['premier']->fresh()->publication->statut);
    }

    public function test_authentification_requise(): void
    {
        $this->getJson(self::URL)->assertUnauthorized();
        $this->postJson(self::URL.'/1/publier')->assertUnauthorized();
        $this->postJson(self::URL.'/1/retire')->assertUnauthorized();
        $this->getJson('/api/v1/programme')->assertUnauthorized();
    }
}

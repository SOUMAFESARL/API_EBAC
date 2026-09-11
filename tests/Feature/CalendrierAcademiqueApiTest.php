<?php

namespace Tests\Feature;

use App\Models\AnneeAcademique;
use App\Models\EvenementCalendrier;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CalendrierAcademiqueApiTest extends TestCase
{
    use RefreshDatabase;

    private function annee(): AnneeAcademique
    {
        $role = Role::query()->firstOrCreate(['code' => 'ADMIN'], ['libelle' => 'Administrateur']);
        Sanctum::actingAs(User::factory()->create(['id_role' => $role->id]));

        return AnneeAcademique::query()->create([
            'libelle' => '2026-2027', 'date_debut' => '2026-09-01', 'date_fin' => '2027-07-30',
        ]);
    }

    private function payload(): array
    {
        return [
            'modules' => [
                ['libelle' => 'Module 1', 'date_debut' => '2026-09-01', 'date_fin' => '2026-12-20',
                    'examens' => [['libelle' => 'Examen final Module 1', 'date_debut' => '2026-12-15', 'date_fin' => '2026-12-20']],
                    'rattrapages' => [['libelle' => 'Rattrapage Module 1', 'date_debut' => '2027-01-05', 'date_fin' => '2027-01-10']]],
                ['libelle' => 'Module 2', 'date_debut' => '2027-01-11', 'date_fin' => '2027-06-30',
                    'examens' => [], 'rattrapages' => [['libelle' => null, 'date_debut' => '2027-07-01', 'date_fin' => '2027-07-10']]],
            ],
            'jours_feries' => [['libelle' => 'Toussaint', 'date' => '2026-11-01']],
            'conges' => [['libelle' => 'Noël', 'date_debut' => '2026-12-21', 'date_fin' => '2027-01-04']],
            'grandes_vacances' => ['libelle' => 'Vacances estivales 2027', 'date_debut' => '2027-07-31', 'date_fin' => '2027-08-31'],
        ];
    }

    public function test_crud_et_remplacement_complet(): void
    {
        $annee = $this->annee();
        $url = "/api/v1/parametres/annees-academiques/$annee->id/calendrier";
        $this->getJson($url)->assertNotFound();
        $this->postJson($url, $this->payload())->assertCreated()->assertJsonCount(2, 'calendrier.modules');
        $this->postJson($url, $this->payload())->assertConflict();
        $this->getJson($url)->assertOk()->assertJsonPath('calendrier.jours_feries.0.date', '2026-11-01');
        $data = $this->payload();
        $data['jours_feries'] = [];
        $data['grandes_vacances'] = null;
        $data['modules'][0]['libelle'] = 'Premier module';
        $this->putJson($url, $data)->assertOk()->assertJsonPath('calendrier.modules.0.libelle', 'Premier module')
            ->assertJsonCount(0, 'calendrier.jours_feries')->assertJsonPath('calendrier.grandes_vacances', null);
        $this->assertDatabaseCount('modules_calendrier', 2);
        $this->assertDatabaseCount('evenements_calendrier', 4);
        $this->deleteJson($url)->assertOk();
        $this->assertDatabaseCount('calendriers_academiques', 0);
        $this->assertDatabaseCount('modules_calendrier', 0);
        $this->assertDatabaseCount('evenements_calendrier', 0);
        $this->getJson($url)->assertNotFound();
        $this->putJson($url, $data)->assertNotFound();
        $this->postJson($url, $data)->assertCreated();
    }

    public function test_regles_metier_et_atomicite(): void
    {
        $annee = $this->annee();
        $url = "/api/v1/parametres/annees-academiques/$annee->id/calendrier";
        $this->postJson($url, $this->payload())->assertCreated();
        $cas = [
            ['modules', [], 'modules'],
            ['modules.1.date_debut', '2026-12-20', 'modules.1.date_debut'],
            ['modules.0.date_debut', '2026-08-01', 'modules.0.date_debut'],
            ['modules.0.examens.0.date_fin', '2026-12-21', 'modules.0.examens.0.date_debut'],
            ['modules.0.rattrapages.0.date_debut', '2026-12-20', 'modules.0.rattrapages.0.date_debut'],
            ['grandes_vacances.date_debut', '2027-07-10', 'grandes_vacances.date_debut'],
            ['jours_feries.0.date', '2028-01-01', 'jours_feries.0.date'],
            ['conges.0.date_fin', '2026-12-01', 'conges.0.date_fin'],
            ['modules.0.date_debut', 'nonsense', 'modules.0.date_debut'],
            ['grandes_vacances', [], 'grandes_vacances'],
        ];
        $avant = $this->getJson($url)->json('calendrier');
        foreach ($cas as [$key, $value, $error]) {
            $data = $this->payload();
            data_set($data, $key, $value);
            $this->putJson($url, $data)->assertUnprocessable()->assertJsonValidationErrors($error);
            $this->assertSame($avant, $this->getJson($url)->json('calendrier'));
        }
    }

    public function test_annee_supprimee_inaccessible_et_bornes_protegees(): void
    {
        $annee = $this->annee();
        $url = "/api/v1/parametres/annees-academiques/$annee->id";
        $this->postJson($url.'/calendrier', $this->payload())->assertCreated();
        $this->patchJson($url, ['date_fin' => '2027-06-01'])->assertUnprocessable();
        $this->assertSame('2027-07-30', $annee->fresh()->date_fin->toDateString());
        $this->deleteJson($url)->assertOk();
        $this->getJson($url.'/calendrier')->assertNotFound();
        $this->putJson($url.'/calendrier', $this->payload())->assertNotFound();
        $this->deleteJson($url.'/calendrier')->assertNotFound();
    }

    public function test_activation_change_l_annee_courante(): void
    {
        $annee = $this->annee();
        $annee->update(['active' => true]);
        $url = '/api/v1/parametres/annees-academiques';
        $id = $this->postJson($url, ['libelle' => '2027-2028', 'date_debut' => '2027-09-01', 'date_fin' => '2028-07-30', 'active' => true])
            ->assertCreated()->json('annee_academique.id');
        $this->assertFalse($annee->fresh()->active);
        $this->patchJson($url.'/'.$annee->id, ['active' => true])->assertOk();
        $this->assertFalse(AnneeAcademique::findOrFail($id)->active);
    }

    public function test_authentification_obligatoire(): void
    {
        $url = '/api/v1/parametres/annees-academiques/1/calendrier';
        $this->getJson($url)->assertUnauthorized();
        $this->postJson($url, $this->payload())->assertUnauthorized();
        $this->putJson($url, $this->payload())->assertUnauthorized();
        $this->deleteJson($url)->assertUnauthorized();
    }

    public function test_ajout_de_plusieurs_lignes_puis_suppression_d_un_module(): void
    {
        $annee = $this->annee();
        $url = "/api/v1/parametres/annees-academiques/$annee->id/calendrier";
        $data = $this->payload();
        $data['modules'][0]['examens'][] = ['date_debut' => '2026-12-10', 'date_fin' => '2026-12-11'];
        $data['modules'][0]['rattrapages'][] = ['date_debut' => '2027-01-11', 'date_fin' => '2027-01-12'];
        $data['modules'][] = ['libelle' => 'Module 3', 'date_debut' => '2027-07-11', 'date_fin' => '2027-07-20', 'examens' => [], 'rattrapages' => []];
        $data['jours_feries'][] = ['libelle' => 'Nouvel an', 'date' => '2027-01-01'];
        $data['conges'][] = ['libelle' => 'Pause', 'date_debut' => '2027-02-01', 'date_fin' => '2027-02-02'];
        $this->postJson($url, $data)->assertCreated();
        $this->getJson($url)->assertOk()->assertJsonCount(3, 'calendrier.modules')
            ->assertJsonCount(2, 'calendrier.modules.0.examens')->assertJsonCount(2, 'calendrier.modules.0.rattrapages')
            ->assertJsonCount(2, 'calendrier.jours_feries')->assertJsonCount(2, 'calendrier.conges');
        array_shift($data['modules']);
        $this->putJson($url, $data)->assertOk()->assertJsonCount(2, 'calendrier.modules')
            ->assertJsonPath('calendrier.modules.0.libelle', 'Module 2');
        $this->assertDatabaseMissing('modules_calendrier', ['libelle' => 'Module 1']);
        $this->assertDatabaseMissing('evenements_calendrier', ['type' => 'examen']);
        $this->assertDatabaseCount('modules_calendrier', 2);
        $this->assertDatabaseCount('evenements_calendrier', 6);
    }

    public function test_calendriers_de_deux_annees_restent_independants(): void
    {
        $annee = $this->annee();
        $autre = AnneeAcademique::query()->create(['libelle' => 'Autre année', 'date_debut' => '2026-09-01', 'date_fin' => '2027-07-30']);
        $url = "/api/v1/parametres/annees-academiques/$annee->id/calendrier";
        $autreUrl = "/api/v1/parametres/annees-academiques/$autre->id/calendrier";
        $this->postJson($url, $this->payload())->assertCreated();
        $this->postJson($autreUrl, $this->payload())->assertCreated();
        $avant = $this->getJson($autreUrl)->assertOk()->json();
        $data = $this->payload();
        $data['modules'][0]['libelle'] = 'Modifié';
        $this->putJson($url, $data)->assertOk();
        $this->deleteJson($url)->assertOk();
        $this->assertSame($avant, $this->getJson($autreUrl)->assertOk()->json());
        $this->assertDatabaseCount('calendriers_academiques', 1);
        $this->assertDatabaseCount('modules_calendrier', 2);
        $this->assertDatabaseCount('evenements_calendrier', 6);
    }

    public function test_payloads_incomplets_ou_malformes_ne_creent_rien(): void
    {
        $annee = $this->annee();
        $url = "/api/v1/parametres/annees-academiques/$annee->id/calendrier";
        foreach (['modules', 'jours_feries', 'conges', 'grandes_vacances', 'modules.0.examens', 'modules.0.rattrapages', 'modules.0.date_fin'] as $key) {
            $data = $this->payload();
            Arr::forget($data, $key);
            $this->postJson($url, $data)->assertUnprocessable()->assertJsonValidationErrors($key);
        }
        foreach ([
            ['modules', null], ['modules', 'incorrect'], ['modules.0', null],
            ['modules.0.libelle', ''], ['modules.0.date_debut', '2026-02-30'],
            ['modules.0.examens', null], ['modules.0.examens.0.date_fin', '2026-12-01'],
            ['modules.0.rattrapages.0.date_fin', '2027-01-01'],
            ['jours_feries.1', ['libelle' => 'Doublon', 'date' => '2026-11-01']],
            ['conges', null], ['grandes_vacances.date_fin', '2027-07-01'],
            ['grandes_vacances', [['date_debut' => '2027-08-01', 'date_fin' => '2027-08-31']]],
            ['modules.0.id_calendrier', 999],
        ] as [$key, $value]) {
            $data = $this->payload();
            data_set($data, $key, $value);
            $this->postJson($url, $data)->assertUnprocessable();
        }
        $this->assertDatabaseCount('calendriers_academiques', 0);
        $this->assertDatabaseCount('modules_calendrier', 0);
        $this->assertDatabaseCount('evenements_calendrier', 0);
    }

    public function test_compte_inactif_et_annee_inexistante(): void
    {
        $annee = $this->annee();
        $url = '/api/v1/parametres/annees-academiques/999999/calendrier';
        $this->getJson($url)->assertNotFound();
        $this->postJson($url, $this->payload())->assertNotFound();
        $this->putJson($url, $this->payload())->assertNotFound();
        $this->deleteJson($url)->assertNotFound();
        auth()->user()->update(['is_active' => false]);
        $url = "/api/v1/parametres/annees-academiques/$annee->id/calendrier";
        $this->getJson($url)->assertForbidden();
        $this->postJson($url, $this->payload())->assertForbidden();
        $this->putJson($url, $this->payload())->assertForbidden();
        $this->deleteJson($url)->assertForbidden();
    }

    public function test_erreur_pendant_ecriture_restaure_toutes_les_lignes(): void
    {
        $annee = $this->annee();
        $url = "/api/v1/parametres/annees-academiques/$annee->id/calendrier";
        $this->postJson($url, $this->payload())->assertCreated();
        $avant = $this->getJson($url)->json('calendrier');
        $event = 'eloquent.creating: '.EvenementCalendrier::class;
        Event::listen($event, function () {
            throw new \RuntimeException('Panne simulée pendant la sauvegarde');
        });
        try {
            $this->putJson($url, $this->payload())->assertStatus(500);
        } finally {
            Event::forget($event);
        }
        $this->assertSame($avant, $this->getJson($url)->assertOk()->json('calendrier'));
        $this->assertDatabaseCount('modules_calendrier', 2);
        $this->assertDatabaseCount('evenements_calendrier', 6);
    }

    public function test_libelles_examens_rattrapages_et_grandes_vacances_saisis_par_front(): void
    {
        $annee = $this->annee();
        $url = "/api/v1/parametres/annees-academiques/$annee->id/calendrier";
        $data = $this->payload();

        $reponse = $this->postJson($url, $data)->assertCreated();
        $reponse
            ->assertJsonPath('calendrier.modules.0.examens.0.libelle', 'Examen final Module 1')
            ->assertJsonPath('calendrier.modules.0.rattrapages.0.libelle', 'Rattrapage Module 1')
            ->assertJsonPath('calendrier.modules.1.rattrapages.0.libelle', null)
            ->assertJsonPath('calendrier.grandes_vacances.libelle', 'Vacances estivales 2027');

        $calendrier = $this->getJson($url)->json('calendrier');
        $this->assertSame('Examen final Module 1', $calendrier['modules'][0]['examens'][0]['libelle']);
        $this->assertSame('Rattrapage Module 1', $calendrier['modules'][0]['rattrapages'][0]['libelle']);
        $this->assertNull($calendrier['modules'][1]['rattrapages'][0]['libelle']);
        $this->assertSame('Vacances estivales 2027', $calendrier['grandes_vacances']['libelle']);

        $data['modules'][0]['examens'][0]['libelle'] = 'Nouvel examen libellé';
        $data['grandes_vacances']['libelle'] = null;
        $this->putJson($url, $data)->assertOk()
            ->assertJsonPath('calendrier.modules.0.examens.0.libelle', 'Nouvel examen libellé')
            ->assertJsonPath('calendrier.grandes_vacances.libelle', null);
    }

    public function test_libelles_modules_calendrier_saisis_par_front_sans_generation_auto(): void
    {
        $annee = $this->annee();
        $url = "/api/v1/parametres/annees-academiques/$annee->id/calendrier";
        $data = [
            'modules' => [
                ['libelle' => 'Herméneutique biblique — S1', 'date_debut' => '2026-09-01', 'date_fin' => '2026-12-20',
                    'examens' => [], 'rattrapages' => []],
                ['libelle' => 'Histoire de l\'Église — S2', 'date_debut' => '2027-01-11', 'date_fin' => '2027-06-30',
                    'examens' => [], 'rattrapages' => []],
                ['libelle' => 'Pratique pastorale', 'date_debut' => '2027-07-01', 'date_fin' => '2027-07-30',
                    'examens' => [], 'rattrapages' => []],
            ],
            'jours_feries' => [],
            'conges' => [],
            'grandes_vacances' => ['libelle' => null, 'date_debut' => '2027-08-01', 'date_fin' => '2027-08-31'],
        ];

        $this->postJson($url, $data)->assertCreated()
            ->assertJsonPath('calendrier.modules.0.libelle', 'Herméneutique biblique — S1')
            ->assertJsonPath('calendrier.modules.1.libelle', 'Histoire de l\'Église — S2')
            ->assertJsonPath('calendrier.modules.2.libelle', 'Pratique pastorale')
            ->assertJsonCount(3, 'calendrier.modules');

        $calendrier = $this->getJson($url)->json('calendrier');
        $this->assertSame('Herméneutique biblique — S1', $calendrier['modules'][0]['libelle']);
        $this->assertSame('Histoire de l\'Église — S2', $calendrier['modules'][1]['libelle']);
        $this->assertSame('Pratique pastorale', $calendrier['modules'][2]['libelle']);
        $this->assertNull($calendrier['grandes_vacances']['libelle']);

        $this->assertDatabaseMissing('modules_calendrier', ['libelle' => 'Module 1']);
        $this->assertDatabaseMissing('modules_calendrier', ['libelle' => 'Module 2']);
        $this->assertDatabaseMissing('modules_calendrier', ['libelle' => 'Module 3']);
    }
}

<?php

namespace Tests\Feature;

use App\Models\EpreuveFinDeCycle;
use App\Models\Etudiant;
use App\Models\Inscription;
use App\Models\Niveau;
use App\Models\Permission;
use App\Models\Promotion;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FinDeCycleApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Promotion $promotion;

    private Etudiant $etudiant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'id_role' => Role::create(['code' => 'ADMIN', 'libelle' => 'Administrateur'])->id,
            'nom' => 'ADJOUA', 'prenoms' => 'Marie',
        ]);
        $niveau = Niveau::create(['code' => 'N3', 'libelle' => '3e Année', 'rang' => 3]);
        $this->promotion = Promotion::create(['code' => 'PROMO-13', 'num_promotion' => 13, 'annee_entree' => 2023, 'id_niveau' => $niveau->id]);
        $this->etudiant = $this->etudiantInscrit('KOUADIO', 'Yao Devallois', '411-2021', $this->promotion);
    }

    private function etudiantInscrit(string $nom, string $prenoms, string $matricule, Promotion $promotion): Etudiant
    {
        $etudiant = Etudiant::create(['nom' => $nom, 'prenoms' => $prenoms, 'matricule' => $matricule, 'date_inscription' => now()->toDateString()]);
        Inscription::create(['id_etudiant' => $etudiant->id, 'id_promotion' => $promotion->id, 'date_inscription' => now()->toDateString()]);

        return $etudiant;
    }

    private function epreuve(string $code): EpreuveFinDeCycle
    {
        return EpreuveFinDeCycle::query()->where('code', $code)->firstOrFail();
    }

    public function test_le_referentiel_porte_les_cinq_lignes_du_bulletin(): void
    {
        Sanctum::actingAs($this->admin);

        $this->getJson('/api/v1/fin-de-cycle/epreuves')->assertOk()
            ->assertJsonCount(5, 'epreuves')
            ->assertJsonPath('epreuves.0.libelle', 'Rapport de stage')
            ->assertJsonPath('epreuves.4.libelle', 'Moyenne générale annuelle')
            ->assertJsonPath('epreuves.0.coefficient', 1);
    }

    public function test_une_epreuve_se_cree_se_reprend_et_se_retire_sans_suppression(): void
    {
        Sanctum::actingAs($this->admin);

        $id = $this->postJson('/api/v1/fin-de-cycle/epreuves', ['libelle' => 'Oral de synthèse', 'coefficient' => 2])
            ->assertCreated()->assertJsonPath('epreuve.code', 'oral-de-synthese')
            ->assertJsonPath('epreuve.rang', 6)->json('epreuve.id');

        $this->patchJson("/api/v1/fin-de-cycle/epreuves/{$id}", ['libelle' => 'Oral final', 'coefficient' => 1])
            ->assertOk()->assertJsonPath('epreuve.libelle', 'Oral final')
            ->assertJsonPath('epreuve.code', 'oral-de-synthese');

        $this->postJson("/api/v1/fin-de-cycle/epreuves/{$id}/activation", ['actif' => false])
            ->assertOk()->assertJsonPath('epreuve.actif', false);
        $this->assertDatabaseHas('epreuves_fin_de_cycle', ['id' => $id, 'actif' => false]);
    }

    public function test_la_derniere_epreuve_active_ne_se_retire_pas(): void
    {
        Sanctum::actingAs($this->admin);
        EpreuveFinDeCycle::query()->where('code', '!=', 'predication')->update(['actif' => false]);

        $this->postJson('/api/v1/fin-de-cycle/epreuves/'.$this->epreuve('predication')->id.'/activation', ['actif' => false])
            ->assertStatus(409)->assertJsonPath('code', 'REFERENTIEL_VIDE');
    }

    public function test_la_saisie_garde_qui_a_saisi_et_ne_touche_que_les_notes_portees(): void
    {
        Sanctum::actingAs($this->admin);
        $autre = $this->etudiantInscrit('BAMBA', 'Awa', '412-2021', $this->promotion);
        $predication = $this->epreuve('predication');

        $this->putJson("/api/v1/fin-de-cycle/promotions/{$this->promotion->id}/epreuves/{$predication->id}/notes", [
            'notes' => [
                ['id_etudiant' => $this->etudiant->id, 'note' => 13.17],
                ['id_etudiant' => $autre->id, 'note' => null],
            ],
        ])->assertOk()
            ->assertJsonPath('promotion.niveau.libelle', '3e Année')
            ->assertJsonPath('promotion.effectif', 2)
            ->assertJsonCount(5, 'epreuves');

        $feuille = $this->getJson("/api/v1/fin-de-cycle/promotions/{$this->promotion->id}/notes")->assertOk();
        $lignes = collect($feuille->json('etudiants'))->keyBy('matricule');
        $this->assertSame(13.17, $lignes['411-2021']['notes'][0]['note']);
        $this->assertSame('ADJOUA', $lignes['411-2021']['notes'][0]['saisi_par']['nom']);
        $this->assertSame([], $lignes['412-2021']['notes']);
    }

    public function test_la_saisie_refuse_un_etudiant_hors_promotion_et_une_epreuve_retiree(): void
    {
        Sanctum::actingAs($this->admin);
        $ailleurs = Promotion::create(['code' => 'PROMO-14', 'num_promotion' => 14, 'annee_entree' => 2024, 'id_niveau' => $this->promotion->id_niveau]);
        $etranger = $this->etudiantInscrit('YAO', 'Kouamé', '500-2022', $ailleurs);
        $dissertation = $this->epreuve('dissertation');
        $url = "/api/v1/fin-de-cycle/promotions/{$this->promotion->id}/epreuves/{$dissertation->id}/notes";

        $this->putJson($url, ['notes' => [['id_etudiant' => $etranger->id, 'note' => 12]]])
            ->assertStatus(422)->assertJsonPath('code', 'ETUDIANT_HORS_PROMOTION');
        $this->putJson($url, ['notes' => [['id_etudiant' => $this->etudiant->id, 'note' => 21]]])
            ->assertStatus(422);

        $dissertation->update(['actif' => false]);
        $this->putJson($url, ['notes' => [['id_etudiant' => $this->etudiant->id, 'note' => 12]]])
            ->assertStatus(409)->assertJsonPath('code', 'EPREUVE_RETIREE');
    }

    public function test_sans_droit_la_saisie_est_refusee(): void
    {
        $role = Role::create(['code' => 'LECTEUR', 'libelle' => 'Lecteur']);
        $role->permissions()->syncWithoutDetaching([
            Permission::query()->where('code', 'notes.fin-de-cycle-notes.consulter')->value('id') => ['actif' => true],
        ]);
        Sanctum::actingAs(User::factory()->create(['id_role' => $role->id]));

        $this->getJson("/api/v1/fin-de-cycle/promotions/{$this->promotion->id}/notes")->assertOk();
        $this->putJson("/api/v1/fin-de-cycle/promotions/{$this->promotion->id}/epreuves/{$this->epreuve('predication')->id}/notes", [
            'notes' => [['id_etudiant' => $this->etudiant->id, 'note' => 12]],
        ])->assertForbidden();
        $this->postJson('/api/v1/fin-de-cycle/epreuves', ['libelle' => 'Oral', 'coefficient' => 1])->assertForbidden();
    }
}

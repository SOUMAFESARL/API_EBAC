<?php

namespace Tests\Feature;

use App\Models\AnneeAcademique;
use App\Models\Eglise;
use App\Models\Etudiant;
use App\Models\Inscription;
use App\Models\Niveau;
use App\Models\Promotion;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StageApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $direction;

    private User $compteEglise;

    private User $compteEtudiant;

    private Promotion $promotion;

    private Eglise $eglise;

    private Etudiant $etudiant;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $role = fn (string $code) => Role::create(['code' => $code, 'libelle' => $code])->id;
        $this->admin = User::factory()->create(['id_role' => $role('ADMIN'), 'nom' => 'ADOU', 'prenoms' => 'Céline']);
        $this->direction = User::factory()->create(['id_role' => $role('DIRECTION')]);
        $this->compteEglise = User::factory()->create(['id_role' => $role('EGLISE'), 'email' => 'm.brou@citegrace.ci', 'nom' => 'BROU', 'prenoms' => 'Michel']);
        $this->compteEtudiant = User::factory()->create(['id_role' => $role('ETUDIANT')]);

        $annee = AnneeAcademique::create(['libelle' => '2026-2027', 'date_debut' => '2026-09-01', 'date_fin' => '2027-07-31']);
        $niveau = Niveau::create(['code' => 'A3', 'libelle' => '3e Année', 'rang' => 3]);
        $this->promotion = Promotion::create(['num_promotion' => 3, 'annee_entree' => 2024, 'id_niveau' => $niveau->id]);
        $this->etudiant = Etudiant::create(['matricule' => 'EBAC-0142', 'nom' => 'KOUAMÉ', 'prenoms' => 'Yao', 'date_inscription' => '2024-09-01', 'user_id' => $this->compteEtudiant->id]);
        $autre = Etudiant::create(['matricule' => 'EBAC-0151', 'nom' => 'ADJOUA', 'prenoms' => 'Marie', 'date_inscription' => '2024-09-01']);
        foreach ([$this->etudiant, $autre] as $e) {
            Inscription::create(['id_etudiant' => $e->id, 'id_promotion' => $this->promotion->id, 'id_annee_academique' => $annee->id, 'date_inscription' => '2026-09-01']);
        }

        $this->eglise = Eglise::create([
            'code' => 'EGL-1', 'nom' => 'Église Cité de Grâce', 'ville_commune' => 'Abidjan', 'statut' => 'Active',
            'capacite_max_stagiaires' => 2,
            'representants' => [['nom' => 'BROU', 'prenoms' => 'Michel', 'email' => 'M.Brou@citegrace.ci', 'role' => 'encadreur-stage']],
        ]);
    }

    /** Campagne commencée il y a quatre semaines, de douze semaines. */
    private function ouvrir(): int
    {
        Sanctum::actingAs($this->admin);
        $debut = now()->startOfWeek()->subWeeks(4)->toDateString();
        $fin = now()->startOfWeek()->subWeeks(4)->addDays(12 * 7 - 1)->toDateString();

        return $this->postJson('/api/v1/stages/campagnes', ['promotion_id' => $this->promotion->id, 'debut' => $debut, 'fin' => $fin])
            ->assertCreated()
            ->assertJsonPath('campagne.effectif', 2)
            ->assertJsonPath('campagne.semaines_prevues', 12)
            ->assertJsonPath('campagne.statut', 'en-cours')
            ->assertJsonPath('campagne.ouverte_par.nom', 'ADOU')
            ->json('campagne.id');
    }

    private function affectationDe(int $campagne, Etudiant $etudiant): int
    {
        return collect($this->getJson("/api/v1/stages/campagnes/{$campagne}")->assertOk()->json('affectations'))
            ->firstWhere('etudiant.id', $etudiant->id)['id'];
    }

    public function test_une_campagne_s_ouvre_une_fois_par_promotion_et_se_supprime_tant_que_rien_n_est_engage(): void
    {
        $campagne = $this->ouvrir();

        $this->postJson('/api/v1/stages/campagnes', ['promotion_id' => $this->promotion->id, 'debut' => '2027-01-04', 'fin' => '2027-04-04'])
            ->assertStatus(409)->assertJsonPath('code', 'CAMPAGNE_EXISTE');
        $this->getJson('/api/v1/stages/campagnes/promotions-eligibles')->assertOk()
            ->assertJsonPath('promotions.0.id_campagne', $campagne)
            ->assertJsonPath('promotions.0.effectif', 2);

        $affectation = $this->affectationDe($campagne, $this->etudiant);
        $this->patchJson("/api/v1/stages/affectations/{$affectation}", ['eglise_id' => $this->eglise->id, 'tuteur_nom' => 'BROU Michel'])
            ->assertOk()->assertJsonPath('campagne.affectes', 1);

        // Une affectation saisie mais non validée n'engage rien.
        $this->deleteJson("/api/v1/stages/campagnes/{$campagne}")->assertOk();
        $this->assertDatabaseCount('campagnes_stage', 0);
        $this->assertDatabaseCount('affectations_stage', 0);
    }

    public function test_une_campagne_dont_une_affectation_est_validee_ne_se_supprime_plus(): void
    {
        $campagne = $this->ouvrir();
        $affectation = $this->affectationDe($campagne, $this->etudiant);
        $this->patchJson("/api/v1/stages/affectations/{$affectation}", ['eglise_id' => $this->eglise->id, 'tuteur_nom' => 'BROU Michel'])->assertOk();
        $this->postJson("/api/v1/stages/affectations/{$affectation}/validation")->assertOk()
            ->assertJsonPath('campagne.valides', 1);

        $this->deleteJson("/api/v1/stages/campagnes/{$campagne}")->assertStatus(409)->assertJsonPath('code', 'CAMPAGNE_ENGAGEE');
        $this->postJson("/api/v1/stages/affectations/{$affectation}/validation")->assertStatus(409)
            ->assertJsonPath('code', 'AFFECTATION_DEJA_VALIDEE');
    }

    public function test_la_suppression_demande_son_droit(): void
    {
        $campagne = $this->ouvrir();
        // Le gestionnaire ne reçoit pas les droits du stage par défaut.
        $gestionnaire = User::factory()->create(['id_role' => Role::create(['code' => 'GESTIONNAIRE', 'libelle' => 'Gestionnaire'])->id]);
        Sanctum::actingAs($gestionnaire);

        $this->deleteJson("/api/v1/stages/campagnes/{$campagne}")->assertForbidden();
        $this->assertDatabaseCount('campagnes_stage', 1);
    }

    public function test_les_dates_ne_bougent_plus_au_debut_une_fois_le_journal_entame(): void
    {
        $campagne = $this->ouvrir();
        $affectation = $this->affectationDe($campagne, $this->etudiant);
        $this->patchJson("/api/v1/stages/affectations/{$affectation}", ['eglise_id' => $this->eglise->id, 'tuteur_nom' => 'BROU Michel'])->assertOk();
        $this->postJson("/api/v1/stages/affectations/{$affectation}/validation")->assertOk();

        Sanctum::actingAs($this->compteEtudiant);
        $this->postJson('/api/v1/etudiant/stage/journal', ['numero' => 1, 'theme' => 'Intégration', 'activites' => str_repeat('Présentation aux responsables. ', 2), 'heures' => 24])->assertOk();

        Sanctum::actingAs($this->admin);
        $this->patchJson("/api/v1/stages/campagnes/{$campagne}", ['debut' => now()->toDateString(), 'fin' => now()->addWeeks(12)->toDateString()])
            ->assertStatus(409)->assertJsonPath('code', 'JOURNAL_ENTAME');
    }

    public function test_le_circuit_complet_du_journal_a_la_decision_de_la_direction(): void
    {
        $campagne = $this->ouvrir();
        $affectation = $this->affectationDe($campagne, $this->etudiant);
        $this->patchJson("/api/v1/stages/affectations/{$affectation}", ['eglise_id' => $this->eglise->id, 'tuteur_nom' => 'BROU Michel'])->assertOk();
        $this->postJson("/api/v1/stages/affectations/{$affectation}/validation")->assertOk();
        $stage = collect($this->getJson("/api/v1/stages/campagnes/{$campagne}")->json('affectations'))->firstWhere('id', $affectation)['id_stage'];

        // Le stagiaire consigne une semaine commencée, pas une semaine à venir.
        Sanctum::actingAs($this->compteEtudiant);
        $recit = str_repeat('Animation de deux classes. ', 2);
        $this->postJson('/api/v1/etudiant/stage/journal', ['numero' => 1, 'theme' => 'Intégration', 'activites' => $recit, 'heures' => 24])
            ->assertOk()->assertJsonPath('stage.semaines_consignees', 1);
        $this->postJson('/api/v1/etudiant/stage/journal', ['numero' => 9, 'theme' => 'Plus tard', 'activites' => $recit, 'heures' => 24])
            ->assertStatus(409)->assertJsonPath('code', 'SEMAINE_A_VENIR');
        $this->post('/api/v1/etudiant/stage/rapports', ['type' => 'mi-parcours', 'fichier' => UploadedFile::fake()->create('mi.pdf', 300)], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('rapports.0.nom_fichier', 'mi.pdf');

        // L'église voit le stage, commente puis valide la semaine.
        Sanctum::actingAs($this->compteEglise);
        $this->getJson('/api/v1/eglise/stages')->assertOk()
            ->assertJsonPath('eglise.nom', 'Église Cité de Grâce')
            ->assertJsonPath('stages.0.id', $stage);
        $semaine = $this->getJson("/api/v1/eglise/stages/{$stage}")->assertOk()->json('semaines.0.id');
        $this->postJson("/api/v1/eglise/journaux-stage/{$semaine}/decision", ['decision' => 'commenter'])
            ->assertStatus(409)->assertJsonPath('code', 'OBSERVATION_REQUISE');
        $this->postJson("/api/v1/eglise/journaux-stage/{$semaine}/decision", ['decision' => 'valider'])
            ->assertOk()->assertJsonPath('semaine.statut', 'validee')->assertJsonPath('semaine.statuee_par.nom', 'BROU');
        $this->postJson("/api/v1/eglise/journaux-stage/{$semaine}/decision", ['decision' => 'commenter', 'observation' => 'x'])
            ->assertStatus(409)->assertJsonPath('code', 'SEMAINE_DEJA_VALIDEE');
        $this->get("/api/v1/eglise/stages/{$stage}/rapports/mi-parcours/fichier")->assertOk();

        // Pas d'évaluation avant le terme.
        $grille = collect(['discipline' => 17, 'engagement' => 16, 'predication' => 14, 'maturite' => 15, 'leadership' => 15])
            ->map(fn ($note, $code) => ['code' => $code, 'note' => $note, 'appreciation' => 'Bien.'])->values()->all();
        $this->postJson("/api/v1/eglise/stages/{$stage}/evaluation", ['criteres' => $grille])
            ->assertStatus(409)->assertJsonPath('code', 'STAGE_EN_COURS');

        // Au terme : douze semaines consignées et validées.
        \App\Models\Stage::find($stage)->campagne->update([
            'date_debut' => now()->subWeeks(12)->toDateString(),
            'date_fin' => now()->subWeeks(12)->addDays(83)->toDateString(),
        ]);
        foreach (range(2, 12) as $n) {
            \App\Models\SemaineStage::create(['id_stage' => $stage, 'numero' => $n, 'theme' => 'Semaine', 'activites' => $recit, 'heures' => 24, 'statut' => 'validee']);
        }
        $this->postJson("/api/v1/eglise/stages/{$stage}/evaluation", ['criteres' => array_slice($grille, 0, 4)])
            ->assertStatus(409)->assertJsonPath('code', 'CRITERE_INCONNU');
        $this->postJson("/api/v1/eglise/stages/{$stage}/evaluation", ['criteres' => $grille, 'appreciationGenerale' => 'Sérieux.'])
            ->assertOk()->assertJsonPath('stage.evaluation.note', 15.4)->assertJsonPath('stage.statut', 'termine');

        // Le journal est clos pour le stagiaire.
        Sanctum::actingAs($this->compteEtudiant);
        $this->postJson('/api/v1/etudiant/stage/journal', ['numero' => 1, 'theme' => 'Reprise', 'activites' => $recit, 'heures' => 24])
            ->assertStatus(409)->assertJsonPath('code', 'STAGE_CLOS');

        // La Direction ne tranche pas sans la part de l'administration.
        Sanctum::actingAs($this->admin);
        $this->postJson("/api/v1/stages/{$stage}/validation", ['decision' => 'validee'])
            ->assertStatus(409)->assertJsonPath('code', 'NOTE_INCOMPLETE');
        $this->postJson("/api/v1/stages/{$stage}/part-administration", ['criteres' => [['code' => 'journal', 'note' => 16]], 'appreciation' => 'Tenu.'])
            ->assertOk()->assertJsonPath('stage.part_administration.portee_par.nom', 'ADOU');
        $this->postJson("/api/v1/stages/{$stage}/validation", ['decision' => 'non-validee'])
            ->assertStatus(409)->assertJsonPath('code', 'MOTIF_REQUIS');
        $this->postJson("/api/v1/stages/{$stage}/validation", ['decision' => 'validee'])
            ->assertOk()->assertJsonPath('stage.validation.decision', 'validee');
        $this->postJson("/api/v1/stages/{$stage}/validation", ['decision' => 'validee'])
            ->assertStatus(409)->assertJsonPath('code', 'STAGE_DEJA_VALIDE');
    }

    public function test_un_compte_eglise_ne_voit_que_ses_stagiaires(): void
    {
        $campagne = $this->ouvrir();
        $affectation = $this->affectationDe($campagne, $this->etudiant);
        $this->patchJson("/api/v1/stages/affectations/{$affectation}", ['eglise_id' => $this->eglise->id, 'tuteur_nom' => 'BROU Michel'])->assertOk();
        $this->postJson("/api/v1/stages/affectations/{$affectation}/validation")->assertOk();
        $stage = \App\Models\Stage::query()->value('id');

        $etranger = User::factory()->create(['id_role' => Role::where('code', 'EGLISE')->value('id'), 'email' => 'inconnu@ailleurs.ci']);
        Sanctum::actingAs($etranger);
        $this->getJson('/api/v1/eglise/stages')->assertNotFound()->assertJsonPath('code', 'EGLISE_NON_RATTACHEE');

        Eglise::create(['code' => 'EGL-2', 'nom' => 'Bonne Nouvelle', 'ville_commune' => 'Bouaké', 'email' => 'inconnu@ailleurs.ci']);
        $this->getJson('/api/v1/eglise/stages')->assertOk()->assertJsonCount(0, 'stages');
        $this->getJson("/api/v1/eglise/stages/{$stage}")->assertNotFound()->assertJsonPath('code', 'STAGE_INTROUVABLE');
    }

    public function test_une_eglise_complete_ou_suspendue_n_accueille_pas(): void
    {
        $campagne = $this->ouvrir();
        $this->eglise->update(['capacite_max_stagiaires' => 1]);
        $premier = $this->affectationDe($campagne, $this->etudiant);
        $second = collect($this->getJson("/api/v1/stages/campagnes/{$campagne}")->json('affectations'))->firstWhere('id', '!=', $premier)['id'];

        $this->patchJson("/api/v1/stages/affectations/{$premier}", ['eglise_id' => $this->eglise->id, 'tuteur_nom' => 'BROU Michel'])->assertOk();
        $this->patchJson("/api/v1/stages/affectations/{$second}", ['eglise_id' => $this->eglise->id, 'tuteur_nom' => 'BROU Michel'])
            ->assertStatus(409)->assertJsonPath('code', 'EGLISE_COMPLETE');

        $this->eglise->update(['statut' => 'Suspendue']);
        $this->patchJson("/api/v1/stages/affectations/{$premier}", ['eglise_id' => $this->eglise->id, 'tuteur_nom' => 'BROU Michel'])
            ->assertStatus(409)->assertJsonPath('code', 'EGLISE_INDISPONIBLE');
    }
}

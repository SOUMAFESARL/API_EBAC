<?php

namespace Tests\Feature;

use App\Models\AffectationEnseignant;
use App\Models\AnneeAcademique;
use App\Models\Bulletin;
use App\Models\Etudiant;
use App\Models\Inscription;
use App\Models\LigneBulletin;
use App\Models\Matiere;
use App\Models\Niveau;
use App\Models\Promotion;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MatieresTermineesApiTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/v1/administration/matieres-terminees';

    private function contexte(): array
    {
        $admin = User::factory()->create(['id_role' => Role::create(['code' => 'ADMIN', 'libelle' => 'Admin'])->id]);
        $roleEnseignant = Role::create(['code' => 'ENSEIGNANT', 'libelle' => 'Enseignant']);
        $enseignant = User::factory()->create(['id_role' => $roleEnseignant->id]);
        $autre = User::factory()->create(['id_role' => $roleEnseignant->id]);
        $roleEtudiant = Role::create(['code' => 'ETUDIANT', 'libelle' => 'Étudiant']);
        $compte = User::factory()->create(['id_role' => $roleEtudiant->id]);
        $annee = AnneeAcademique::create(['libelle' => '2026-2027', 'date_debut' => '2026-09-01', 'date_fin' => '2027-07-31', 'active' => true]);
        $n1 = Niveau::create(['code' => 'N1', 'libelle' => '1ère Année', 'rang' => 1]);
        $n2 = Niveau::create(['code' => 'N2', 'libelle' => '2e Année', 'rang' => 2]);
        $promotion = Promotion::create(['code' => 'P17', 'num_promotion' => 17, 'annee_entree' => 2025, 'id_niveau' => $n2->id, 'statut' => 'Active']);
        $ancienne = Matiere::create(['code' => 'M-1', 'libelle' => 'Introduction biblique', 'id_niveau' => $n1->id, 'note_validation' => 10]);
        $courante = Matiere::create(['code' => 'M-2', 'libelle' => 'Église et développement', 'id_niveau' => $n2->id, 'note_validation' => 10]);
        $etudiant = Etudiant::create(['user_id' => $compte->id, 'matricule' => 'ETU-1', 'nom' => 'BROU', 'prenoms' => 'Konan', 'date_inscription' => '2025-09-01']);
        $inscription = Inscription::create(['id_etudiant' => $etudiant->id, 'id_promotion' => $promotion->id, 'id_annee_academique' => $annee->id, 'date_inscription' => '2026-09-01']);
        AffectationEnseignant::create(['enseignant_id' => $enseignant->id, 'id_matiere' => $courante->id, 'portee' => 'matiere', 'date_debut' => '2026-09-01']);

        return compact('admin', 'enseignant', 'autre', 'compte', 'promotion', 'ancienne', 'courante', 'inscription', 'n1');
    }

    public function test_la_matiere_du_niveau_reste_en_cours_jusqu_a_sa_fin(): void
    {
        $d = $this->contexte();
        Sanctum::actingAs($d['compte']);
        $this->getJson('/api/v1/etudiant/mes-matieres')->assertOk()
            ->assertJsonPath('matieres.0.id', $d['ancienne']->id)->assertJsonPath('matieres.0.statut', 'a_completer')
            ->assertJsonPath('matieres.1.id', $d['courante']->id)->assertJsonPath('matieres.1.statut', 'en_cours');

        Sanctum::actingAs($d['admin']);
        $id = $this->postJson(self::URL, ['id_matiere' => $d['courante']->id, 'id_promotion' => $d['promotion']->id])
            ->assertCreated()->assertJsonPath('terminee.par', trim($d['admin']->nom.' '.$d['admin']->prenoms))->json('terminee.id');
        $this->postJson(self::URL, ['id_matiere' => $d['courante']->id, 'id_promotion' => $d['promotion']->id])->assertOk();
        $this->getJson(self::URL.'/'.$d['promotion']->id)->assertOk()
            ->assertJsonCount(1, 'matieres')->assertJsonPath('matieres.0.terminee.id', $id)
            ->assertJsonPath('annee_academique.libelle', '2026-2027');

        Sanctum::actingAs($d['compte']);
        $this->getJson('/api/v1/etudiant/mes-matieres')->assertOk()->assertJsonPath('matieres.1.statut', 'a_completer');

        Sanctum::actingAs($d['admin']);
        $this->deleteJson(self::URL.'/'.$id)->assertOk();
        Sanctum::actingAs($d['compte']);
        $this->getJson('/api/v1/etudiant/mes-matieres?statut=en_cours')->assertOk()->assertJsonPath('meta.total', 1);
    }

    public function test_le_bulletin_dit_en_cours_plutot_qu_a_reprendre(): void
    {
        $d = $this->contexte();
        $bulletin = Bulletin::create(['id_inscription' => $d['inscription']->id, 'periode' => 'Annuel', 'statut' => 'Publié', 'date_publication' => '2027-07-01 10:00:00']);
        LigneBulletin::create(['id_bulletin' => $bulletin->id, 'id_matiere' => $d['courante']->id, 'note' => 7, 'coefficient' => 1]);
        Sanctum::actingAs($d['compte']);
        $this->getJson('/api/v1/etudiant/bulletins/'.$bulletin->id)->assertOk()
            ->assertJsonPath('bulletin.matieres.0.en_cours', true)->assertJsonPath('bulletin.matieres.0.a_reprendre', false);

        Sanctum::actingAs($d['admin']);
        $this->postJson(self::URL, ['id_matiere' => $d['courante']->id, 'id_promotion' => $d['promotion']->id])->assertCreated();
        Sanctum::actingAs($d['compte']);
        $this->getJson('/api/v1/etudiant/bulletins/'.$bulletin->id)->assertOk()
            ->assertJsonPath('bulletin.matieres.0.en_cours', false)->assertJsonPath('bulletin.matieres.0.a_reprendre', true);
    }

    public function test_l_enseignant_affecte_termine_sans_pouvoir_rouvrir(): void
    {
        $d = $this->contexte();
        Sanctum::actingAs($d['autre']);
        $this->postJson('/api/v1/enseignant/mes-cours/'.$d['courante']->id.'/terminer', ['id_promotion' => $d['promotion']->id])->assertNotFound();

        Sanctum::actingAs($d['enseignant']);
        $id = $this->postJson('/api/v1/enseignant/mes-cours/'.$d['courante']->id.'/terminer', ['id_promotion' => $d['promotion']->id])
            ->assertCreated()->json('terminee.id');
        $this->deleteJson(self::URL.'/'.$id)->assertForbidden();
    }

    public function test_hors_niveau_et_apres_passage_refuse(): void
    {
        $d = $this->contexte();
        Sanctum::actingAs($d['admin']);
        $this->postJson(self::URL, ['id_matiere' => $d['ancienne']->id, 'id_promotion' => $d['promotion']->id])
            ->assertUnprocessable()->assertJsonPath('code', 'NIVEAU_DIFFERENT');

        $id = $this->postJson(self::URL, ['id_matiere' => $d['courante']->id, 'id_promotion' => $d['promotion']->id])->json('terminee.id');
        $d['promotion']->update(['id_niveau' => Niveau::create(['code' => 'N3', 'libelle' => '3e Année', 'rang' => 3])->id]);
        $this->deleteJson(self::URL.'/'.$id)->assertConflict()->assertJsonPath('code', 'PASSAGE_EFFECTUE');
    }

    public function test_droits_requis(): void
    {
        $d = $this->contexte();
        $secretariat = User::factory()->create(['id_role' => Role::create(['code' => 'CUSTOM', 'libelle' => 'Perso'])->id]);
        Sanctum::actingAs($secretariat);
        $this->getJson(self::URL)->assertForbidden();
        $this->postJson(self::URL, ['id_matiere' => $d['courante']->id, 'id_promotion' => $d['promotion']->id])->assertForbidden();

        $this->accorderDroits('CUSTOM', 'progression.matieres-terminees.terminer');
        $this->getJson(self::URL)->assertOk()->assertJsonPath('promotions.0.id', $d['promotion']->id);
        $this->postJson(self::URL, ['id_matiere' => $d['courante']->id, 'id_promotion' => $d['promotion']->id])->assertCreated();
    }
}

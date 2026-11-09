<?php

namespace Tests\Feature;

use App\Models\AnneeAcademique;
use App\Models\Cours;
use App\Models\CoursAFaire;
use App\Models\Etudiant;
use App\Models\FeuillePresence;
use App\Models\Inscription;
use App\Models\Matiere;
use App\Models\Module;
use App\Models\Niveau;
use App\Models\Promotion;
use App\Models\Role;
use App\Models\SeanceCahierTexte;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PassageNiveauApiTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/v1/administration/passages-niveau';

    private function contexte(bool $anneeSuivante = true): array
    {
        $admin = User::factory()->create(['id_role' => Role::create(['code' => 'ADMIN', 'libelle' => 'Admin'])->id]);
        $enseignant = User::factory()->create(['id_role' => Role::create(['code' => 'ENSEIGNANT', 'libelle' => 'Enseignant'])->id]);
        $n1 = Niveau::create(['code' => 'A1', 'libelle' => '1ère Année', 'rang' => 1]);
        $n2 = Niveau::create(['code' => 'A2', 'libelle' => '2e Année', 'rang' => 2]);
        $n4 = Niveau::create(['code' => 'A4', 'libelle' => '4e Année', 'rang' => 4]);
        $source = AnneeAcademique::create(['libelle' => '2025-2026', 'date_debut' => '2025-09-01', 'date_fin' => '2026-07-31', 'active' => true]);
        $cible = $anneeSuivante ? AnneeAcademique::create(['libelle' => '2026-2027', 'date_debut' => '2026-09-01', 'date_fin' => '2027-07-31', 'active' => false]) : null;
        $promotion = Promotion::create(['code' => 'P5', 'num_promotion' => 5, 'annee_entree' => 2025, 'id_niveau' => $n1->id, 'statut' => 'Active']);
        $terminale = Promotion::create(['code' => 'P2', 'num_promotion' => 2, 'annee_entree' => 2022, 'id_niveau' => $n4->id, 'statut' => 'Active']);

        $matiere = Matiere::create(['code' => 'M1', 'libelle' => 'Théologie', 'id_niveau' => $n1->id, 'enseignant_id' => $enseignant->id]);
        $cours = Cours::create(['id_module' => Module::create(['id_matiere' => $matiere->id, 'libelle' => 'Doctrine', 'ordre' => 1])->id,
            'code' => 'C1', 'libelle' => 'Conciles', 'ordre' => 1]);
        $seance = SeanceCahierTexte::create(['enseignant_id' => $enseignant->id, 'id_niveau' => $n1->id, 'id_promotion' => $promotion->id,
            'id_matiere' => $matiere->id, 'id_cours' => $cours->id, 'date_prevue' => '2025-10-14', 'heure_debut_prevue' => '08:00', 'statut' => 'realisee']);
        $feuille = FeuillePresence::create(['id_seance' => $seance->id, 'statut' => 'validee']);

        $etudiants = [];
        foreach ([['ETU-1', 'Aka', 'present', 'En formation'], ['ETU-2', 'Brou', 'absent', 'En formation'], ['ETU-3', 'Coulibaly', 'present', 'Abandon']] as [$matricule, $nom, $presence, $statut]) {
            $etudiant = Etudiant::create(['matricule' => $matricule, 'nom' => $nom, 'prenoms' => 'Test', 'date_inscription' => '2025-09-01']);
            Inscription::create(['id_etudiant' => $etudiant->id, 'id_promotion' => $promotion->id, 'id_annee_academique' => $source->id,
                'date_inscription' => '2025-09-01', 'statut' => $statut]);
            $feuille->presences()->create(['id_etudiant' => $etudiant->id, 'statut' => $presence]);
            if ($presence === 'absent') {
                CoursAFaire::create(['id_etudiant' => $etudiant->id, 'id_seance' => $seance->id, 'id_cours' => $cours->id,
                    'id_matiere' => $matiere->id, 'statut' => 'a_faire', 'motif' => 'absence']);
            }
            $etudiants[] = $etudiant;
        }
        Sanctum::actingAs($admin);

        return compact('admin', 'enseignant', 'n2', 'source', 'cible', 'promotion', 'terminale', 'etudiants');
    }

    public function test_la_derniere_annee_n_est_pas_proposee(): void
    {
        $data = $this->contexte();

        $this->getJson(self::URL)->assertOk()
            ->assertJsonCount(1, 'promotions')
            ->assertJsonPath('promotions.0.id', $data['promotion']->id)
            ->assertJsonPath('promotions.0.niveau_suivant.libelle', '2e Année')
            ->assertJsonPath('promotions.0.effectif', 3);

        $this->getJson(self::URL.'/'.$data['terminale']->id)->assertOk()->assertJsonPath('obstacles', ['dernier-niveau', 'promotion-vide']);
        $rapport = $this->getJson(self::URL.'/'.$data['terminale']->id)->json();
        $this->postJson(self::URL.'/'.$data['terminale']->id, ['rapport_vu_le' => $rapport['genere_le']])
            ->assertStatus(409)->assertJsonPath('code', 'DERNIER_NIVEAU');
    }

    public function test_rapport_puis_passage_collectif(): void
    {
        $data = $this->contexte();
        $url = self::URL.'/'.$data['promotion']->id;

        $rapport = $this->getJson($url)->assertOk()
            ->assertJsonPath('annee_academique', '2025-2026')
            ->assertJsonPath('annee_suivante', '2026-2027')
            ->assertJsonPath('niveau_suivant_libelle', '2e Année')
            ->assertJsonPath('obstacles', ['notes-en-attente'])
            ->assertJsonPath('notes_de_l_annee.0.validee', false)
            ->assertJsonCount(3, 'etudiants')
            ->json();
        $parMatricule = collect($rapport['etudiants'])->keyBy('matricule');
        $this->assertSame(1, $parMatricule['ETU-1']['matieres_validees']);
        $this->assertSame(1, $parMatricule['ETU-2']['matieres_non_commencees']);
        $this->assertCount(1, $parMatricule['ETU-2']['cours_a_faire']);
        $this->assertFalse($parMatricule['ETU-3']['suit_la_promotion']);

        $this->postJson($url, ['rapport_vu_le' => $rapport['genere_le']])->assertStatus(409)->assertJsonPath('code', 'NOTES_EN_ATTENTE');

        $this->postJson($url, ['rapport_vu_le' => $rapport['genere_le'], 'malgre_notes_en_attente' => true])->assertOk()
            ->assertJsonPath('bilan.niveau_atteint_libelle', '2e Année')
            ->assertJsonPath('bilan.etudiants_avances', 2)
            ->assertJsonPath('bilan.etudiants_non_avances', 1)
            ->assertJsonPath('bilan.cours_a_faire_reportes', 1);

        $this->assertSame($data['n2']->id, $data['promotion']->fresh()->id_niveau);
        $this->assertSame(2, Inscription::where('id_annee_academique', $data['cible']->id)->where('id_promotion', $data['promotion']->id)->count());
        $this->assertDatabaseMissing('inscriptions', ['id_etudiant' => $data['etudiants'][2]->id, 'id_annee_academique' => $data['cible']->id]);
        $this->assertDatabaseHas('inscriptions', ['id_etudiant' => $data['etudiants'][0]->id, 'id_annee_academique' => $data['source']->id,
            'decision_passage' => 'Passage au niveau supérieur']);
        $this->assertDatabaseHas('parcours_academiques', ['id_etudiant' => $data['etudiants'][0]->id, 'id_niveau' => $data['n2']->id, 'statut' => 'En cours']);
        $this->assertDatabaseHas('cours_a_faire', ['id_etudiant' => $data['etudiants'][1]->id, 'statut' => 'a_faire']);
        $this->assertDatabaseHas('passages_niveau', ['id_promotion' => $data['promotion']->id, 'id_annee_cible' => $data['cible']->id]);
        $this->assertDatabaseHas('journaux_audit', ['action' => 'Passage de niveau d’une promotion']);

        $this->postJson($url, ['rapport_vu_le' => $rapport['genere_le'], 'malgre_notes_en_attente' => true])
            ->assertStatus(409)->assertJsonPath('code', 'PASSAGE_DEJA_EFFECTUE');
    }

    public function test_rapport_perime_et_annee_suivante_absente(): void
    {
        $data = $this->contexte(false);
        $url = self::URL.'/'.$data['promotion']->id;

        $this->getJson($url)->assertOk()->assertJsonPath('obstacles', ['notes-en-attente', 'annee-suivante-absente']);
        $this->postJson($url, ['rapport_vu_le' => now()->getTimestampMs(), 'malgre_notes_en_attente' => true])
            ->assertStatus(409)->assertJsonPath('code', 'ANNEE_SUIVANTE_ABSENTE');

        AnneeAcademique::create(['libelle' => '2026-2027', 'date_debut' => '2026-09-01', 'date_fin' => '2027-07-31']);
        $this->postJson($url, ['rapport_vu_le' => now()->subMinutes(5)->getTimestampMs(), 'malgre_notes_en_attente' => true])
            ->assertStatus(409)->assertJsonPath('code', 'RAPPORT_PERIME');
    }

    public function test_droits_requis(): void
    {
        $data = $this->contexte();
        Sanctum::actingAs($data['enseignant']);

        $this->getJson(self::URL)->assertForbidden();
        $this->postJson(self::URL.'/'.$data['promotion']->id, ['rapport_vu_le' => now()->getTimestampMs()])->assertForbidden();
    }
}

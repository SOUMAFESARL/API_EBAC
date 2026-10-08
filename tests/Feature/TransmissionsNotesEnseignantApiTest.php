<?php

namespace Tests\Feature;

use App\Models\AnneeAcademique;
use App\Models\Cours;
use App\Models\FeuilleNotes;
use App\Models\Matiere;
use App\Models\Module;
use App\Models\Niveau;
use App\Models\Promotion;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TransmissionsNotesEnseignantApiTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/v1/enseignant/transmissions-notes';

    public function test_tableau_general_cumule_transmissions_sans_melanger_promotions(): void
    {
        $c = $this->contexte();
        $seance = \App\Models\SeanceCahierTexte::create(['enseignant_id' => $c['enseignant']->id,
            'id_niveau' => $c['promotion']->id_niveau, 'id_matiere' => $c['matiere']->id,
            'date_prevue' => '2026-09-14', 'heure_debut_prevue' => '08:00:00', 'statut' => 'realisee']);
        $c['feuille']->update(['id_seance' => $seance->id]);
        $etudiant = \App\Models\Etudiant::create(['matricule' => 'GENERAL', 'nom' => 'KONE', 'prenoms' => 'Test', 'date_inscription' => '2026-09-01']);
        $autreEtudiant = \App\Models\Etudiant::create(['matricule' => 'GENERAL2', 'nom' => 'YAO', 'prenoms' => 'Test', 'date_inscription' => '2026-09-01']);
        $c['feuille']->notes()->create(['id_etudiant' => $etudiant->id, 'evaluation' => 'dev1', 'note' => 12]);
        $c['feuille']->notes()->create(['id_etudiant' => $autreEtudiant->id, 'evaluation' => 'dev1', 'note' => 0]);
        $url = self::URL.'/tableau-general?id_matiere='.$c['matiere']->id;
        $premier = $this->getJson($url)->assertOk()->assertJsonCount(1, 'tableaux')
            ->assertJsonCount(1, 'tableaux.0.colonnes')->assertJsonPath('tableaux.0.lignes.0.moyenne', 12);
        $cle = $premier->json('tableaux.0.colonnes.0.cle');
        $nouvelle = $c['feuille']->replicate();
        $seance2 = $seance->replicate();
        $seance2->date_prevue = '2026-09-15';
        $seance2->save();
        $nouvelle->fill(['statut' => 'brouillon', 'id_seance' => $seance2->id,
            'id_matiere' => null, 'id_cours' => $c['cours']->id])->save();
        $nouvelle->notes()->create(['id_etudiant' => $etudiant->id, 'evaluation' => 'dev2', 'note' => 18]);
        $this->getJson($url)->assertJsonCount(1, 'tableaux.0.colonnes');
        $nouvelle->update(['statut' => 'transmise', 'date_transmission' => now()]);
        $response = $this->getJson($url)->assertOk()->assertJsonCount(2, 'tableaux.0.colonnes')
            ->assertJsonPath('tableaux.0.colonnes.0.cle', $cle)
            ->assertJsonPath('tableaux.0.colonnes.1.id_seance', $seance2->id)
            ->assertJsonPath('tableaux.0.colonnes.1.date_seance', '2026-09-15')
            ->assertJsonPath('tableaux.0.lignes.0.moyenne', 15)
            ->assertJsonPath('tableaux.0.lignes.1.moyenne', 0);
        $this->assertSame([0, null], array_values($response->json('tableaux.0.lignes.1.notes')));
        $etrangere = $c['feuille']->replicate();
        $etrangere->transmise_par = $c['autre']->id;
        $etrangere->id_seance = null;
        $etrangere->save();
        $etrangere->notes()->create(['id_etudiant' => $etudiant->id, 'note' => 20]);
        $this->getJson($url)->assertJsonCount(2, 'tableaux.0.colonnes');
        $promotion = Promotion::create(['num_promotion' => 2, 'annee_entree' => 2026, 'id_niveau' => $c['promotion']->id_niveau]);
        $separee = $c['feuille']->replicate();
        $separee->id_promotion = $promotion->id;
        $separee->save();
        $separee->notes()->create(['id_etudiant' => $etudiant->id, 'note' => 6]);
        $this->getJson($url)->assertJsonCount(2, 'tableaux');
        $this->getJson($url.'&id_promotion='.$promotion->id)->assertJsonCount(1, 'tableaux')
            ->assertJsonPath('tableaux.0.lignes.0.moyenne', 6);
        $this->getJson(self::URL.'/tableau-general?id_matiere=invalide')->assertUnprocessable();
        Sanctum::actingAs($c['admin']);
        $this->getJson($url)->assertForbidden();
    }

    public function test_tableau_et_feuilles_de_mes_notes_transmises_uniquement(): void
    {
        $c = $this->contexte();
        $etudiant = \App\Models\Etudiant::create(['matricule' => 'ETU-T', 'nom' => 'KONE', 'prenoms' => 'Test', 'date_inscription' => '2026-09-01']);
        $c['feuille']->notes()->create(['id_etudiant' => $etudiant->id, 'evaluation' => 'devoir', 'note' => 12]);
        $cours = $c['feuille']->replicate();
        $cours->id_matiere = null;
        $cours->id_cours = $c['cours']->id;
        $cours->save();
        $cours->notes()->create(['id_etudiant' => $etudiant->id, 'note' => 18]);
        $etrangere = $c['feuille']->replicate();
        $etrangere->transmise_par = $c['autre']->id;
        $etrangere->save();
        $etrangere->notes()->create(['id_etudiant' => $etudiant->id, 'note' => 20]);
        $brouillon = $c['feuille']->replicate();
        $brouillon->statut = 'brouillon';
        $brouillon->save();
        $brouillon->notes()->create(['id_etudiant' => $etudiant->id, 'note' => 19]);
        $this->getJson(self::URL.'/tableau')->assertOk()->assertJsonCount(2)
            ->assertJsonPath('0.id_feuille_notes', $cours->id)->assertJsonPath('0.notes.0.note', 18)
            ->assertJsonPath('1.notes.0.etudiant.matricule', 'ETU-T')
            ->assertJsonMissingPath('0.notes.0.matiere');
        $this->getJson(self::URL.'/feuilles')->assertOk()->assertJsonCount(2, 'feuilles_notes')
            ->assertJsonPath('feuilles_notes.0.notes.0.note', 18);
        foreach (['tableau', 'feuilles'] as $format) {
            $path = $format === 'tableau' ? null : 'feuilles_notes';
            $this->getJson(self::URL.'/'.$format.'?id_cours='.$c['cours']->id)->assertOk()->assertJsonCount(1, $path);
            $this->getJson(self::URL.'/'.$format.'?id_matiere='.$c['matiere']->id)->assertOk()->assertJsonCount(2, $path);
            $this->getJson(self::URL.'/'.$format.'?statut=validee_direction')->assertOk()->assertJsonCount(0, $path);
            $this->getJson(self::URL.'/'.$format.'?id_seance=invalide')->assertUnprocessable();
            Sanctum::actingAs($c['admin']);
            $this->getJson(self::URL.'/'.$format)->assertForbidden();
            Sanctum::actingAs($c['enseignant']);
        }
    }

    private function contexte(): array
    {
        $role = Role::create(['code' => 'ENSEIGNANT', 'libelle' => 'Enseignant']);
        $enseignant = User::factory()->create(['id_role' => $role->id]);
        $autre = User::factory()->create(['id_role' => $role->id]);
        $adminRole = Role::create(['code' => 'ADMIN', 'libelle' => 'Administrateur']);
        $admin = User::factory()->create(['id_role' => $adminRole->id]);
        $annee = AnneeAcademique::create(['libelle' => '2026-2027', 'date_debut' => '2026-09-01', 'date_fin' => '2027-07-31']);
        $niveau = Niveau::create(['code' => 'N1', 'libelle' => 'Niveau 1', 'rang' => 1]);
        $promotion = Promotion::create(['num_promotion' => 1, 'annee_entree' => 2026, 'id_niveau' => $niveau->id]);
        $matiere = Matiere::create(['code' => 'MAT', 'libelle' => 'Theologie', 'id_niveau' => $niveau->id]);
        $module = Module::create(['id_matiere' => $matiere->id, 'libelle' => 'Doctrine', 'ordre' => 1]);
        $cours = Cours::create(['id_module' => $module->id, 'code' => 'C1', 'libelle' => 'Cours 1', 'ordre' => 1]);
        $feuille = FeuilleNotes::create(['id_annee_academique' => $annee->id, 'id_promotion' => $promotion->id,
            'id_matiere' => $matiere->id, 'transmise_par' => $enseignant->id, 'updated_by' => $enseignant->id,
            'statut' => 'transmise', 'date_transmission' => now()]);
        Sanctum::actingAs($enseignant);

        return compact('enseignant', 'autre', 'admin', 'annee', 'promotion', 'matiere', 'cours', 'feuille');
    }

    public function test_suivi_conserve_acces_apres_decisions_administratives(): void
    {
        $c = $this->contexte();
        $id = $c['feuille']->id;
        $this->getJson(self::URL.'/'.$id)->assertOk()
            ->assertJsonPath('transmission.circuit_validation.etape_actuelle', 2)
            ->assertJsonPath('transmission.circuit_validation.etapes.0.statut', 'terminee')
            ->assertJsonPath('transmission.circuit_validation.etapes.1.statut', 'en_cours');
        Sanctum::actingAs($c['admin']);
        $base = '/api/v1/administration/notes-transmises/'.$id;
        $this->postJson($base.'/valider-secretariat')->assertOk();
        $this->postJson($base.'/transmettre-direction')->assertOk();
        $this->postJson($base.'/valider-direction')->assertOk();
        Sanctum::actingAs($c['enseignant']);
        $this->getJson(self::URL)->assertOk()->assertJsonPath('meta.total', 1);
        $response = $this->getJson(self::URL.'/'.$id)->assertOk()
            ->assertJsonPath('transmission.statut', 'validee_direction')
            ->assertJsonPath('transmission.transmise_par', $c['enseignant']->id)
            ->assertJsonPath('transmission.circuit_validation.validee_et_verrouillee', true)
            ->assertJsonPath('transmission.derniere_decision.id_acteur', $c['admin']->id)
            ->assertJsonCount(3, 'transmission.historique');
        $this->assertSame(['terminee', 'terminee', 'terminee', 'terminee'], array_column($response->json('transmission.circuit_validation.etapes'), 'statut'));
    }

    public function test_tous_statuts_et_motifs_rejet_sont_presentes(): void
    {
        $c = $this->contexte();
        $etapes = ['transmise' => 2, 'validee_secretariat' => 3, 'transmise_direction' => 3,
            'validee_direction' => 4, 'rejetee_secretariat' => 1, 'rejetee_direction' => 2];
        foreach ($etapes as $statut => $etape) {
            $rejet = str_starts_with($statut, 'rejetee');
            $c['feuille']->changerStatut($statut, $c['admin']->id, $statut, $rejet ? 'Verifier les notes' : null);
            $this->getJson(self::URL.'/'.$c['feuille']->id)->assertOk()
                ->assertJsonPath('transmission.circuit_validation.etape_actuelle', $etape)
                ->assertJsonPath('transmission.circuit_validation.rejetee', $rejet)
                ->assertJsonPath('transmission.circuit_validation.motif_rejet', $rejet ? 'Verifier les notes' : null)
                ->assertJsonPath('transmission.circuit_validation.correction_enseignant_requise', $statut === 'rejetee_secretariat');
        }
    }

    public function test_isolation_enseignants_et_brouillons_exclus(): void
    {
        $c = $this->contexte();
        Sanctum::actingAs($c['autre']);
        $this->getJson(self::URL.'?transmise_par='.$c['enseignant']->id.'&enseignant_id='.$c['enseignant']->id)
            ->assertOk()->assertJsonPath('meta.total', 0);
        $this->getJson(self::URL.'/'.$c['feuille']->id)->assertNotFound();
        $c['feuille']->update(['updated_by' => $c['autre']->id]);
        $this->getJson(self::URL.'/'.$c['feuille']->id)->assertNotFound();
        Sanctum::actingAs($c['enseignant']);
        $this->getJson(self::URL.'/'.$c['feuille']->id)->assertOk();
        $c['feuille']->update(['statut' => 'brouillon']);
        $this->getJson(self::URL)->assertOk()->assertJsonPath('meta.total', 0);
        $this->getJson(self::URL.'/'.$c['feuille']->id)->assertNotFound();
    }

    public function test_filtres_et_pagination_pour_cours_et_matiere(): void
    {
        $c = $this->contexte();
        $feuilleCours = $c['feuille']->replicate();
        $feuilleCours->fill(['id_matiere' => null, 'id_cours' => $c['cours']->id, 'statut' => 'validee_secretariat'])->save();
        $this->getJson(self::URL.'?id_matiere='.$c['matiere']->id.'&per_page=1')->assertOk()
            ->assertJsonCount(1, 'transmissions')->assertJsonPath('meta.total', 2)->assertJsonPath('meta.last_page', 2);
        $this->getJson(self::URL.'?id_cours='.$c['cours']->id)->assertOk()->assertJsonPath('meta.total', 1)
            ->assertJsonPath('transmissions.0.matiere.id', $c['matiere']->id);
        $this->getJson(self::URL.'?statut=transmise&id_annee_academique='.$c['annee']->id.'&id_promotion='.$c['promotion']->id)
            ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('transmissions.0.cours', null);
        foreach (['statut=inconnu', 'per_page=101', 'page=0', 'id_matiere=999999'] as $filtre) {
            $this->getJson(self::URL.'?'.$filtre)->assertUnprocessable();
        }
    }

    public function test_authentification_role_et_compte_actif(): void
    {
        $this->getJson(self::URL)->assertUnauthorized();
        $c = $this->contexte();
        Sanctum::actingAs($c['admin']);
        $this->getJson(self::URL)->assertForbidden();
        $this->getJson(self::URL.'/'.$c['feuille']->id)->assertForbidden();
        $c['enseignant']->update(['is_active' => false]);
        Sanctum::actingAs($c['enseignant']);
        $this->getJson(self::URL)->assertForbidden();
    }

    public function test_migration_retrouve_auteur_sans_attribuer_feuille_inconnue(): void
    {
        $c = $this->contexte();
        $c['feuille']->update(['updated_by' => $c['admin']->id]);
        $ancienne = $c['feuille']->replicate();
        $ancienne->fill(['id_matiere' => null, 'id_cours' => $c['cours']->id, 'updated_by' => $c['autre']->id])->save();
        $promotion = Promotion::create(['num_promotion' => 2, 'annee_entree' => 2026, 'id_niveau' => $c['promotion']->id_niveau]);
        $inconnue = $c['feuille']->replicate();
        $inconnue->fill(['id_promotion' => $promotion->id, 'updated_by' => $c['admin']->id])->save();
        $migration = require database_path('migrations/2026_10_06_170000_add_transmise_par_to_feuilles_notes.php');
        $migration->down();
        $c['feuille']->historique()->create(['id_acteur' => $c['enseignant']->id, 'action' => 'transmission_secretariat',
            'statut_avant' => 'brouillon', 'statut_apres' => 'transmise', 'created_at' => now()]);
        $migration->up();
        $this->assertSame($c['enseignant']->id, $c['feuille']->fresh()->transmise_par);
        $this->assertSame($c['autre']->id, $ancienne->fresh()->transmise_par);
        $this->assertNull($inconnue->fresh()->transmise_par);
        $this->getJson(self::URL)->assertOk()->assertJsonPath('meta.total', 1);
    }
}

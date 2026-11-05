<?php

namespace Tests\Feature;

use App\Models\{AnneeAcademique, Bulletin, Cours, Creneau, Etudiant, FeuilleNotes, Inscription, LigneBulletin, Matiere, Module, Niveau, Promotion, Role, SeanceCahierTexte, UniteEnseignement, User};
use App\Services\{BulletinQrCodeService, SuppressionProgrammeService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SuppressionProgrammeApiTest extends TestCase
{
    use RefreshDatabase;

    private function contexte(): array
    {
        $role = Role::create(['code' => 'ADMIN', 'libelle' => 'Admin']);
        $user = User::factory()->create(['id_role' => $role->id]);
        Sanctum::actingAs($user);
        $niveau = Niveau::create(['code' => 'N1', 'libelle' => 'Niveau 1', 'rang' => 1]);
        $unite = UniteEnseignement::create(['code' => 'BIB', 'libelle' => 'Bible', 'statut' => 'Actif']);
        $matiere = Matiere::create(['code' => 'MAT1', 'libelle' => 'Matiere 1', 'id_niveau' => $niveau->id, 'unite_ensigne_id' => $unite->id]);
        $autre = Matiere::create(['code' => 'MAT2', 'libelle' => 'Autre matiere', 'id_niveau' => $niveau->id]);
        $annee = AnneeAcademique::create(['libelle' => '2026-2027', 'date_debut' => '2026-09-01', 'date_fin' => '2027-07-31']);
        $promotion = Promotion::create(['num_promotion' => 1, 'annee_entree' => 2026, 'id_niveau' => $niveau->id]);
        $etudiant = Etudiant::create(['matricule' => 'ETU1', 'nom' => 'Kone', 'prenoms' => 'Jean', 'date_inscription' => '2026-09-01']);
        $inscription = Inscription::create(['id_etudiant' => $etudiant->id, 'id_promotion' => $promotion->id, 'id_annee_academique' => $annee->id, 'date_inscription' => '2026-09-01']);

        return compact('user', 'niveau', 'unite', 'matiere', 'autre', 'annee', 'promotion', 'etudiant', 'inscription');
    }

    public function test_suppression_matiere_retire_dependances_et_conserve_bulletin_publie(): void
    {
        extract($this->contexte());
        $module = Module::create(['id_matiere' => $matiere->id, 'code' => 'MOD1', 'libelle' => 'Module']);
        $cours = Cours::create(['id_module' => $module->id, 'code' => 'C1', 'libelle' => 'Cours']);
        $calendrier = $annee->calendrier()->create([]);
        $moduleCalendrier = $calendrier->modules()->create(['libelle' => 'Session', 'ordre' => 1, 'date_debut' => '2026-09-01', 'date_fin' => '2026-09-30']);
        $matiere->modulesCalendrier()->attach($moduleCalendrier->id);
        DB::table('publication_calendriers')->insert(['id_calendrier' => $calendrier->id, 'statut' => 'publie', 'version' => 1]);
        $salle = \App\Models\Salle::create(['code' => 'S1', 'nom' => 'Salle 1', 'statut' => 'Disponible']);
        $creneau = Creneau::create(['id_module_calendrier' => $moduleCalendrier->id, 'id_niveau' => $niveau->id,
            'id_matiere' => $matiere->id, 'id_cours' => $cours->id, 'enseignant_id' => $user->id,
            'id_salle' => $salle->id, 'jour' => 'lundi', 'heure_debut' => '08:00', 'heure_fin' => '10:00']);
        $seance = SeanceCahierTexte::create(['id_creneau' => $creneau->id, 'id_module_calendrier' => $moduleCalendrier->id,
            'id_niveau' => $niveau->id, 'id_matiere' => $matiere->id, 'id_cours' => $cours->id,
            'enseignant_id' => $user->id, 'date_prevue' => '2026-09-07', 'heure_debut_prevue' => '08:00', 'statut' => 'planifiee']);
        $presence = DB::table('feuilles_presence')->insertGetId(['id_seance' => $seance->id]);
        DB::table('presences')->insert(['id_feuille_presence' => $presence, 'id_etudiant' => $etudiant->id, 'statut' => 'absent']);
        $rattrapage = DB::table('cours_a_faire')->insertGetId(['id_matiere' => $matiere->id, 'id_cours' => $cours->id, 'id_seance' => $seance->id, 'id_etudiant' => $etudiant->id]);
        DB::table('programmations_rattrapage')->insert(['id_cours_a_faire' => $rattrapage, 'id_seance_rattrapage' => $seance->id,
            'date_prevue' => '2026-09-08', 'heure_debut' => '08:00', 'heure_fin' => '10:00', 'enseignant_id' => $user->id]);
        DB::table('affectations_enseignants')->insert(['id_matiere' => $matiere->id, 'id_cours' => $cours->id,
            'enseignant_id' => $user->id, 'portee' => 'matiere', 'date_debut' => '2026-09-01']);
        $feuille = FeuilleNotes::create(['id_matiere' => $matiere->id, 'id_cours' => $cours->id, 'id_seance' => $seance->id,
            'id_promotion' => $promotion->id, 'id_annee_academique' => $annee->id, 'statut' => 'validee_direction', 'updated_by' => $user->id]);
        $note = $feuille->notes()->create(['id_etudiant' => $etudiant->id, 'note' => 14]);
        DB::table('historique_feuilles_notes')->insert(['id_feuille_notes' => $feuille->id,
            'action' => 'validation', 'statut_avant' => 'transmise_direction', 'statut_apres' => 'validee_direction', 'created_at' => now()]);
        $feuilleCours = FeuilleNotes::create(['id_cours' => $cours->id, 'id_promotion' => $promotion->id,
            'id_annee_academique' => $annee->id, 'statut' => 'validee_direction', 'updated_by' => $user->id]);
        $feuilleCours->notes()->create(['id_etudiant' => $etudiant->id, 'note' => 12]);
        $correction = DB::table('corrections_notes')->insertGetId(['id_note' => $note->id, 'note_initiale' => 14, 'note_proposee' => 15, 'motif' => 'Test', 'demande_par' => $user->id]);
        DB::table('traces_corrections_notes')->insert(['id_correction' => $correction, 'id_acteur' => $user->id, 'action' => 'demande', 'created_at' => now()]);

        $publie = Bulletin::create(['id_inscription' => $inscription->id, 'periode' => 'Publie', 'statut' => 'Publié', 'date_publication' => now(), 'moyenne' => 14, 'rang' => 2]);
        $brouillon = Bulletin::create(['id_inscription' => $inscription->id, 'periode' => 'Annuel', 'statut' => 'Généré', 'moyenne' => 15, 'rang' => 1]);
        foreach ([$publie, $brouillon] as $bulletin) {
            LigneBulletin::create(['id_bulletin' => $bulletin->id, 'id_matiere' => $matiere->id, 'note' => 14, 'coefficient' => 2, 'moyenne_ponderee' => 28]);
        }
        LigneBulletin::create(['id_bulletin' => $brouillon->id, 'id_matiere' => $autre->id, 'note' => 16, 'coefficient' => 2, 'moyenne_ponderee' => 32]);
        $empreinte = app(BulletinQrCodeService::class)->empreinte($publie);
        $reclamation = DB::table('reclamations_notes')->insertGetId(['id_ligne_bulletin' => $publie->lignes()->first()->id,
            'id_etudiant' => $etudiant->id, 'note_contestee' => 14, 'motif' => 'Verification']);

        $this->deleteJson('/api/v1/parametres/matieres/'.$matiere->id)->assertOk();
        foreach (['matieres' => $matiere, 'modules' => $module, 'cours' => $cours, 'creneaux' => $creneau, 'seances_cahier_texte' => $seance] as $table => $model) {
            $this->assertSoftDeleted($table, ['id' => $model->id, 'deleted_by' => $user->id]);
        }
        foreach (['feuilles_notes', 'notes_cours', 'historique_feuilles_notes', 'corrections_notes', 'traces_corrections_notes', 'feuilles_presence', 'presences', 'cours_a_faire', 'programmations_rattrapage', 'affectations_enseignants', 'matiere_module_calendrier'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        $this->assertDatabaseHas('publication_calendriers', ['id_calendrier' => $calendrier->id, 'statut' => 'non_publie']);
        $this->assertFalse($autre->fresh()->trashed());
        $this->assertSame('16.00', $brouillon->fresh()->moyenne);
        $this->assertNull($brouillon->fresh()->rang);
        $this->assertSame(1, $brouillon->lignes()->count());
        $this->assertSame($empreinte, app(BulletinQrCodeService::class)->empreinte($publie->fresh()));
        $this->assertSame($matiere->libelle, $publie->lignes()->first()->matiere->libelle);
        $this->assertDatabaseHas('reclamations_notes', ['id' => $reclamation]);
        $this->getJson('/api/v1/parametres/matieres/'.$matiere->id)->assertNotFound();
        $this->postJson('/api/v1/parametres/modules', ['id_matiere' => $matiere->id, 'code' => 'NOUVEAU', 'libelle' => 'Nouveau module'])
            ->assertUnprocessable()->assertJsonValidationErrors('id_matiere');
        $this->postJson('/api/v1/parametres/cours', ['id_module' => $module->id, 'code' => 'NOUVEAU', 'libelle' => 'Nouveau cours'])
            ->assertUnprocessable()->assertJsonValidationErrors('id_module');
        $this->getJson('/api/v1/administration/bulletins/'.$publie->id)->assertOk()
            ->assertJsonPath('bulletin.lignes.0.matiere.id', $matiere->id);
    }

    public function test_suppression_unite_retire_toutes_ses_matieres_et_bulletins_vides(): void
    {
        extract($this->contexte());
        $deuxieme = Matiere::create(['code' => 'MAT3', 'libelle' => 'Matiere 3', 'id_niveau' => $niveau->id, 'unite_ensigne_id' => $unite->id]);
        $bulletin = Bulletin::create(['id_inscription' => $inscription->id, 'periode' => 'Annuel']);
        LigneBulletin::create(['id_bulletin' => $bulletin->id, 'id_matiere' => $matiere->id, 'coefficient' => 2]);
        $this->deleteJson('/api/v1/parametres/unites-enseignement/'.$unite->id)->assertOk();
        $this->assertSoftDeleted('unites_enseignement', ['id' => $unite->id, 'deleted_by' => $user->id]);
        foreach ([$matiere, $deuxieme] as $supprimee) {
            $this->assertSoftDeleted('matieres', ['id' => $supprimee->id, 'deleted_by' => $user->id]);
        }
        $this->assertDatabaseMissing('bulletins', ['id' => $bulletin->id]);
        $this->assertFalse($autre->fresh()->trashed());
    }

    public function test_suppression_est_annulee_si_une_etape_echoue(): void
    {
        extract($this->contexte());
        try {
            DB::transaction(function () use ($matiere, $user) {
                app(SuppressionProgrammeService::class)->supprimerMatiere($matiere, $user->id);
                throw new \RuntimeException('Annulation');
            });
        } catch (\RuntimeException $exception) {
            $this->assertSame('Annulation', $exception->getMessage());
        }
        $this->assertFalse($matiere->fresh()->trashed());
        $this->assertNull($matiere->fresh()->deleted_by);
    }
}

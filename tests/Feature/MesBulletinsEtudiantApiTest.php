<?php

namespace Tests\Feature;

use App\Models\AnneeAcademique;
use App\Models\Bulletin;
use App\Models\CoursAFaire;
use App\Models\Etudiant;
use App\Models\Inscription;
use App\Models\LigneBulletin;
use App\Models\Matiere;
use App\Models\Niveau;
use App\Models\Promotion;
use App\Models\Role;
use App\Models\SeanceCahierTexte;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MesBulletinsEtudiantApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_liste_et_detail_isoles_par_etudiant_et_publication(): void
    {
        $role = Role::create(['code' => 'ETUDIANT', 'libelle' => 'Etudiant']);
        $user = User::factory()->create(['id_role' => $role->id]);
        $etudiant = Etudiant::create(['user_id' => $user->id, 'matricule' => 'ETU-1', 'nom' => 'Koffi', 'prenoms' => 'Anne', 'date_inscription' => '2026-09-01']);
        $autre = Etudiant::create(['matricule' => 'ETU-2', 'nom' => 'Autre', 'prenoms' => 'Etudiant', 'date_inscription' => '2026-09-01']);
        $niveau = Niveau::create(['code' => 'N1', 'libelle' => 'Premiere annee', 'rang' => 1]);
        $promotion = Promotion::create(['num_promotion' => 4, 'annee_entree' => 2026, 'id_niveau' => $niveau->id]);
        $annee = AnneeAcademique::create(['libelle' => '2026-2027', 'date_debut' => '2026-09-01', 'date_fin' => '2027-07-31', 'active' => true]);
        $inscription = Inscription::create(['id_etudiant' => $etudiant->id, 'id_promotion' => $promotion->id, 'id_annee_academique' => $annee->id, 'date_inscription' => '2026-09-01']);
        $autreInscription = Inscription::create(['id_etudiant' => $autre->id, 'id_promotion' => $promotion->id, 'id_annee_academique' => $annee->id, 'date_inscription' => '2026-09-01']);
        $publie = Bulletin::create(['id_inscription' => $inscription->id, 'periode' => 'Annuel', 'statut' => 'Publié', 'date_publication' => '2026-10-01', 'moyenne' => 14.1, 'rang' => 3]);
        $brouillon = Bulletin::create(['id_inscription' => $inscription->id, 'periode' => 'Brouillon']);
        $retire = Bulletin::create(['id_inscription' => $inscription->id, 'periode' => 'Retire', 'statut' => 'Brouillon', 'date_publication' => '2026-10-01']);
        $etranger = Bulletin::create(['id_inscription' => $autreInscription->id, 'periode' => 'Annuel', 'statut' => 'Publié', 'date_publication' => '2026-10-01']);
        foreach ([14, 8, null] as $index => $note) {
            $matiere = Matiere::create(['code' => 'MAT-'.$index, 'libelle' => 'Matiere '.$index, 'id_niveau' => $niveau->id, 'note_validation' => 10]);
            LigneBulletin::create(['id_bulletin' => $publie->id, 'id_matiere' => $matiere->id, 'note' => $note, 'coefficient' => 2]);
        }
        Sanctum::actingAs($user);
        $base = '/api/v1/etudiant/bulletins';
        $this->getJson($base)->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('bulletins.0.id', $publie->id)
            ->assertJsonPath('bulletins.0.annee_academique.id', $annee->id)->assertJsonMissingPath('bulletins.0.fichier_chemin');
        $this->getJson($base.'/'.$publie->id)->assertOk()->assertJsonPath('bulletin.moyenne_generale', 14.1)
            ->assertJsonPath('bulletin.rang', 3)->assertJsonCount(3, 'bulletin.matieres')
            ->assertJsonPath('bulletin.recapitulatif_uv.validees', 1)->assertJsonPath('bulletin.recapitulatif_uv.non_validees', 1)
            ->assertJsonPath('bulletin.recapitulatif_uv.a_completer', 1)->assertJsonCount(0, 'bulletin.cours_a_faire');
        foreach ([$brouillon, $retire, $etranger] as $bulletin) {
            $this->getJson($base.'/'.$bulletin->id)->assertNotFound();
        }
        $this->getJson($base.'?per_page=101')->assertUnprocessable();
        $this->getJson($base.'?id_annee_academique='.$annee->id)->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson($base.'?page=2&per_page=1')->assertOk()->assertJsonCount(0, 'bulletins');

        $ancienneAnnee = AnneeAcademique::create(['libelle' => '2025-2026', 'date_debut' => '2025-09-01', 'date_fin' => '2026-07-31', 'active' => false]);
        $anciennePromotion = Promotion::create(['num_promotion' => 3, 'annee_entree' => 2025, 'id_niveau' => $niveau->id]);
        $ancienneInscription = Inscription::create(['id_etudiant' => $etudiant->id, 'id_promotion' => $anciennePromotion->id,
            'id_annee_academique' => $ancienneAnnee->id, 'date_inscription' => '2025-09-01']);
        $ancienBulletin = Bulletin::create(['id_inscription' => $ancienneInscription->id, 'periode' => 'Annuel', 'statut' => 'Publié', 'date_publication' => '2026-08-01']);
        $this->getJson($base)->assertOk()->assertJsonPath('bulletins.0.id', $publie->id)
            ->assertJsonPath('bulletins.1.id', $ancienBulletin->id);
        $this->getJson($base.'?id_annee_academique='.$ancienneAnnee->id)->assertOk()->assertJsonPath('meta.total', 1)
            ->assertJsonPath('bulletins.0.id', $ancienBulletin->id);

        foreach ([[$annee, $etudiant, 'a_faire'], [$ancienneAnnee, $etudiant, 'a_faire'],
            [$annee, $autre, 'a_faire'], [$annee, $etudiant, 'termine']] as $index => [$periode, $personne, $statut]) {
            $module = $periode->calendrier()->firstOrCreate([])->modules()->create([
                'libelle' => 'Module '.$index, 'ordre' => $index + 1, 'date_debut' => $periode->date_debut, 'date_fin' => $periode->date_fin,
            ]);
            $seance = SeanceCahierTexte::create(['enseignant_id' => $user->id, 'id_niveau' => $niveau->id,
                'id_matiere' => $matiere->id, 'id_module_calendrier' => $module->id,
                'date_prevue' => $periode->date_debut, 'heure_debut_prevue' => '08:00:00', 'statut' => 'realisee']);
            CoursAFaire::create(['id_etudiant' => $personne->id, 'id_matiere' => $matiere->id,
                'id_seance' => $seance->id, 'statut' => $statut, 'motif' => 'absence']);
        }
        $this->getJson($base.'/'.$publie->id)->assertOk()->assertJsonCount(1, 'bulletin.cours_a_faire');
        $this->getJson($base.'/'.$ancienBulletin->id)->assertOk()->assertJsonCount(1, 'bulletin.cours_a_faire');
    }

    public function test_authentification_role_fiche_et_liste_vide(): void
    {
        $base = '/api/v1/etudiant/bulletins';
        $this->getJson($base)->assertUnauthorized();
        $role = Role::create(['code' => 'ENSEIGNANT', 'libelle' => 'Enseignant']);
        Sanctum::actingAs(User::factory()->create(['id_role' => $role->id]));
        $this->getJson($base)->assertForbidden();
        $role = Role::create(['code' => 'ETUDIANT', 'libelle' => 'Etudiant']);
        $user = User::factory()->create(['id_role' => $role->id]);
        Sanctum::actingAs($user);
        $this->getJson($base)->assertNotFound();
        Etudiant::create(['user_id' => $user->id, 'matricule' => 'ETU-1', 'nom' => 'Koffi', 'prenoms' => 'Anne', 'date_inscription' => '2026-09-01']);
        $this->getJson($base)->assertOk()->assertJsonCount(0, 'bulletins');
        $user->update(['is_active' => false]);
        $this->getJson($base)->assertForbidden();
        $this->getJson($base.'/1')->assertForbidden();
    }
}

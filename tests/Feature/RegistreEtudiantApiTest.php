<?php

namespace Tests\Feature;

use App\Models\AnneeAcademique;
use App\Models\DossierEtudiant;
use App\Models\Etudiant;
use App\Models\Inscription;
use App\Models\Niveau;
use App\Models\Promotion;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RegistreEtudiantApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_et_secretariat_listent_filtrent_et_modifient_le_registre(): void
    {
        $role = Role::query()->create(['code' => 'SECRETARIAT', 'libelle' => 'Secrétariat académique']);
        Sanctum::actingAs(User::factory()->create(['id_role' => $role->id]));
        $annee = AnneeAcademique::query()->create([
            'libelle' => '2026-2027', 'date_debut' => '2026-09-01', 'date_fin' => '2027-07-31', 'active' => true,
        ]);
        $niveau1 = Niveau::query()->create(['code' => 'A1', 'libelle' => '1ère Année', 'rang' => 1]);
        $niveau2 = Niveau::query()->create(['code' => 'A2', 'libelle' => '2e Année', 'rang' => 2]);
        $promotion17 = Promotion::query()->create([
            'code' => 'PROMO-000017', 'num_promotion' => 17, 'annee_entree' => 2026,
            'id_niveau' => $niveau1->id, 'statut' => 'Active',
        ]);
        $promotion16 = Promotion::query()->create([
            'code' => 'PROMO-000016', 'num_promotion' => 16, 'annee_entree' => 2025,
            'id_niveau' => $niveau2->id, 'statut' => 'Active',
        ]);
        $affecte = Etudiant::query()->create([
            'matricule' => 'EBAC-0017-2026', 'nom' => 'ANGE', 'prenoms' => 'ANGE',
            'date_inscription' => '2026-09-04', 'statut' => 'En formation',
        ]);
        DossierEtudiant::query()->create([
            'id_etudiant' => $affecte->id, 'numero_dossier' => 'ANA0172026',
            'statut' => 'Incomplet', 'date_ouverture' => '2026-09-04',
            'pieces_requises' => ['Photo', 'Diplôme'],
        ]);
        Inscription::query()->create([
            'id_etudiant' => $affecte->id, 'id_promotion' => $promotion17->id,
            'id_annee_academique' => $annee->id, 'date_inscription' => '2026-09-04',
            'statut' => 'En formation',
        ]);
        Etudiant::query()->create([
            'matricule' => 'PRE-SANS-AFFECTATION', 'nom' => 'YAO', 'prenoms' => 'Anne',
            'date_inscription' => '2026-09-04', 'statut' => 'Préinscrit',
        ]);

        $this->getJson("/api/v1/administration/registre-etudiants?recherche=ANGE&niveau_id={$niveau1->id}&promotion_id={$promotion17->id}&annee_entree=2026&statut=En%20formation")
            ->assertOk()
            ->assertJsonPath('statistiques.total', 1)
            ->assertJsonPath('statistiques.en_formation', 1)
            ->assertJsonCount(1, 'registre.data')
            ->assertJsonPath('registre.data.0.matricule', 'EBAC-0017-2026')
            ->assertJsonPath('registre.data.0.promotion.num_promotion', 17)
            ->assertJsonPath('registre.data.0.niveau.libelle', '1ère Année')
            ->assertJsonPath('registre.data.0.dossier.nombre_pieces_manquantes', 2);

        $this->getJson("/api/v1/administration/registre-etudiants/{$affecte->id}/dossier")
            ->assertOk()
            ->assertJsonPath('dossier.numero_dossier', 'ANA0172026')
            ->assertJsonPath('id', $affecte->id)
            ->assertJsonMissingPath('dossier.informations_personnelles.id');

        $this->patchJson("/api/v1/administration/registre-etudiants/{$affecte->id}", [
            'id_promotion' => $promotion16->id,
            'statut' => 'Départ de la formation',
            'decision_passage' => 'Abandon',
            'date_decision' => '2026-10-01',
            'dossier_statut' => 'Clôturé',
            'observations' => 'Départ volontaire',
        ])->assertOk()
            ->assertJsonPath('etudiant.promotion.id', $promotion16->id)
            ->assertJsonPath('etudiant.niveau.libelle', '2e Année')
            ->assertJsonPath('etudiant.statut', 'Départ de la formation')
            ->assertJsonPath('etudiant.dossier.statut', 'Clôturé');
    }

    public function test_un_etudiant_ne_peut_pas_acceder_au_registre(): void
    {
        $role = Role::query()->create(['code' => 'ETUDIANT', 'libelle' => 'Étudiant']);
        Sanctum::actingAs(User::factory()->create(['id_role' => $role->id]));

        $this->getJson('/api/v1/administration/registre-etudiants')->assertForbidden();
        $this->getJson('/api/v1/administration/registre-etudiants/1/dossier')->assertForbidden();
        $this->patchJson('/api/v1/administration/registre-etudiants/1', [])->assertForbidden();
    }
}

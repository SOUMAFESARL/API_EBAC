<?php

namespace Tests\Feature;

use App\Models\AnneeAcademique;
use App\Models\DossierEtudiant;
use App\Models\Etudiant;
use App\Models\Niveau;
use App\Models\Promotion;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DossierEtudiantCompletApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_etudiant_connecte_accede_uniquement_a_son_propre_dossier(): void
    {
        Storage::fake('public');
        $role = Role::query()->create(['code' => 'ETUDIANT', 'libelle' => 'Étudiant']);
        $compte = User::factory()->create(['id_role' => $role->id]);
        $etudiant = Etudiant::query()->create([
            'user_id' => $compte->id,
            'matricule' => 'EBAC-0009-2026',
            'nom' => 'ANGE',
            'prenoms' => 'ANGE',
            'email' => $compte->email,
            'date_inscription' => now()->toDateString(),
            'statut' => 'En formation',
        ]);
        DossierEtudiant::query()->create([
            'id_etudiant' => $etudiant->id,
            'numero_dossier' => 'ANA0092026',
            'statut' => 'Incomplet',
            'date_ouverture' => now()->toDateString(),
        ]);
        Sanctum::actingAs($compte);

        $this->getJson('/api/v1/etudiant/dossier')
            ->assertOk()
            ->assertJsonPath('dossier.numero_dossier', 'ANA0092026')
            ->assertJsonPath('dossier.informations_personnelles.id', $etudiant->id)
            ->assertJsonPath('dossier.informations_personnelles.matricule', 'EBAC-0009-2026');

        $this->patchJson('/api/v1/etudiant/dossier', [
            'telephone' => '+2250708091011',
            'adresse' => 'Abidjan Cocody',
            'situation_matrimonial' => 'Marié',
            'nombre_enfant' => 1,
        ])->assertOk()
            ->assertJsonPath('dossier.informations_personnelles.telephone', '+2250708091011')
            ->assertJsonPath('dossier.informations_personnelles.adresse', 'Abidjan Cocody')
            ->assertJsonPath('dossier.informations_personnelles.nombre_enfant', 1);

        $this->patchJson('/api/v1/etudiant/dossier', [
            'matricule' => 'MATRICULE-INTERDIT',
            'statut' => 'Diplômé',
            'id_promotion' => 99,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['matricule', 'statut', 'id_promotion']);

        $this->assertDatabaseHas('etudiants', [
            'id' => $etudiant->id,
            'matricule' => 'EBAC-0009-2026',
            'statut' => 'En formation',
            'telephone' => '+2250708091011',
        ]);

        $this->post('/api/v1/etudiant/dossier', [
            'photo_identite' => UploadedFile::fake()->image('nouvelle-photo.jpg'),
            'documents' => [UploadedFile::fake()->create('attestation.pdf', 250, 'application/pdf')],
        ])->assertOk()
            ->assertJsonPath('dossier.documents.0.nom_original', 'attestation.pdf')
            ->assertJsonPath('dossier.documents.0.statut_validation', 'En attente');

        Storage::disk('public')->assertExists(
            Etudiant::query()->findOrFail($etudiant->id)->photo_identite,
        );

        $this->getJson("/api/v1/administration/etudiants/{$etudiant->id}/dossier-complet")
            ->assertForbidden();
    }

    public function test_le_secretariat_affecte_un_etudiant_et_ouvre_son_dossier_complet(): void
    {
        $role = Role::query()->create(['code' => 'SECRETAIRE_ACADEMIQUE', 'libelle' => 'Secrétaire académique']);
        $secretaire = User::factory()->create(['id_role' => $role->id]);
        $compteEtudiant = User::factory()->create();
        Sanctum::actingAs($secretaire);

        $etudiant = Etudiant::query()->create([
            'user_id' => $compteEtudiant->id,
            'matricule' => 'EBAC-0017-2026',
            'nom' => 'ANGE',
            'prenoms' => 'ANGE',
            'email' => 'ange@example.com',
            'date_inscription' => now()->toDateString(),
            'statut' => 'Inscrit',
        ]);
        DossierEtudiant::query()->create([
            'id_etudiant' => $etudiant->id,
            'numero_dossier' => 'ANA0172026',
            'statut' => 'Incomplet',
            'date_ouverture' => now()->toDateString(),
            'pieces_requises' => ['Photo', 'Diplôme'],
        ]);
        $annee = AnneeAcademique::query()->create([
            'libelle' => '2026-2027',
            'date_debut' => '2026-09-01',
            'date_fin' => '2027-07-31',
            'active' => true,
        ]);
        $niveau = Niveau::query()->create([
            'code' => 'A1',
            'libelle' => '1ère Année',
            'rang' => 1,
        ]);
        $promotion = Promotion::query()->create([
            'code' => 'PROMO-000017',
            'num_promotion' => 17,
            'annee_entree' => 2026,
            'id_niveau' => $niveau->id,
            'statut' => 'Active',
        ]);

        $this->postJson("/api/v1/administration/etudiants/{$etudiant->id}/affecter-promotion", [
            'id_promotion' => $promotion->id,
        ])->assertCreated()
            ->assertJsonPath('inscription.id_promotion', $promotion->id)
            ->assertJsonPath('inscription.id_annee_academique', $annee->id)
            ->assertJsonPath('inscription.promotion.niveau.libelle', '1ère Année')
            ->assertJsonPath('dossier.numero_dossier', 'ANA0172026')
            ->assertJsonPath('dossier.informations_personnelles.matricule', 'EBAC-0017-2026')
            ->assertJsonPath('dossier.inscription_actuelle.promotion.num_promotion', 17);

        $this->assertDatabaseHas('inscriptions', [
            'id_etudiant' => $etudiant->id,
            'id_promotion' => $promotion->id,
            'id_annee_academique' => $annee->id,
            'statut' => 'En formation',
        ]);

        $this->getJson("/api/v1/administration/etudiants/{$etudiant->id}/dossier-complet")
            ->assertOk()
            ->assertJsonPath('dossier.numero_dossier', 'ANA0172026')
            ->assertJsonPath('dossier.eglise_recommandante', null)
            ->assertJsonPath('dossier.situation_financiere.total_paye', 0)
            ->assertJsonCount(1, 'dossier.historique_inscriptions');
    }

    public function test_un_etudiant_ne_peut_pas_etre_affecte_deux_fois_dans_la_meme_annee(): void
    {
        $role = Role::query()->create(['code' => 'ADMIN', 'libelle' => 'Administrateur']);
        Sanctum::actingAs($createur = User::factory()->create(['id_role' => $role->id]));
        $etudiant = Etudiant::query()->create([
            'user_id' => User::factory()->create()->id,
            'matricule' => 'EBAC-0018-2026', 'nom' => 'YAO', 'prenoms' => 'Anne',
            'date_inscription' => now()->toDateString(), 'statut' => 'Inscrit',
        ]);
        $annee = AnneeAcademique::query()->create([
            'libelle' => '2026-2027', 'date_debut' => '2026-09-01', 'date_fin' => '2027-07-31', 'active' => true,
        ]);
        $niveau = Niveau::query()->create(['code' => 'A1', 'libelle' => '1ère Année', 'rang' => 1]);
        $promotion = Promotion::query()->create([
            'code' => 'PROMO-000017', 'num_promotion' => 17, 'annee_entree' => 2026,
            'id_niveau' => $niveau->id, 'statut' => 'Active',
        ]);
        \DB::table('inscriptions')->insert([
            'id_etudiant' => $etudiant->id, 'id_promotion' => $promotion->id,
            'id_annee_academique' => $annee->id, 'date_inscription' => now()->toDateString(),
            'statut' => 'En formation', 'created_by' => $createur->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->postJson("/api/v1/administration/etudiants/{$etudiant->id}/affecter-promotion", [
            'id_promotion' => $promotion->id,
        ])->assertUnprocessable();
    }
}

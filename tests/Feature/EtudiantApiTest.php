<?php

namespace Tests\Feature;

use App\Models\AnneeAcademique;
use App\Models\Civilite;
use App\Models\DossierEtudiant;
use App\Models\Eglise;
use App\Models\Etudiant;
use App\Models\Inscription;
use App\Models\Niveau;
use App\Models\Promotion;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EtudiantApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_le_secretariat_liste_et_filtre_tous_les_etudiants(): void
    {
        $role = Role::query()->create(['code' => 'SECRETARIAT', 'libelle' => 'Secrétariat académique']);
        Sanctum::actingAs(User::factory()->create(['id_role' => $role->id]));

        $monsieur = Civilite::query()->create(['code' => 'M', 'name' => 'Monsieur']);
        $madame = Civilite::query()->create(['code' => 'MME', 'name' => 'Madame']);
        $egliseA = Eglise::query()->create(['code' => 'EGL-A', 'nom' => 'Église A', 'ville_commune' => 'Abidjan']);
        $egliseB = Eglise::query()->create(['code' => 'EGL-B', 'nom' => 'Église B', 'ville_commune' => 'Bouaké']);
        $annee = AnneeAcademique::query()->create([
            'libelle' => '2026-2027',
            'date_debut' => '2026-09-01',
            'date_fin' => '2027-07-31',
            'active' => true,
        ]);

        $cible = Etudiant::query()->create([
            'matricule' => 'EBAC-0001-2026', 'nom' => 'KOFFI', 'prenoms' => 'Jean',
            'email' => 'jean@example.net', 'telephone' => '0101010101', 'civilite_id' => $monsieur->id,
            'eglise_id' => $egliseA->id, 'date_inscription' => '2026-09-15', 'statut' => 'Préinscrit',
        ]);
        $compte = User::factory()->create(['id_role' => $role->id, 'email' => 'compte.jean@example.net']);
        $cible->update(['user_id' => $compte->id]);
        DossierEtudiant::query()->create([
            'id_etudiant' => $cible->id,
            'numero_dossier' => 'DOS-0001',
            'statut' => 'Incomplet',
            'date_ouverture' => '2026-09-15',
        ]);
        $niveau = Niveau::query()->create(['code' => 'NIV-1', 'libelle' => 'Licence 1', 'rang' => 1, 'statut' => 'Actif']);
        $promotion = Promotion::query()->create([
            'code' => 'PROMO-1',
            'num_promotion' => 1,
            'annee_entree' => 2026,
            'id_niveau' => $niveau->id,
            'statut' => 'Active',
        ]);
        Inscription::query()->create([
            'id_etudiant' => $cible->id,
            'id_promotion' => $promotion->id,
            'date_inscription' => '2026-09-15',
            'statut' => 'En formation',
        ]);

        Etudiant::query()->create([
            'matricule' => 'EBAC-0002-2025', 'nom' => 'YAO', 'prenoms' => 'Anne',
            'email' => 'anne@example.net', 'telephone' => '0202020202', 'civilite_id' => $madame->id,
            'eglise_id' => $egliseB->id, 'date_inscription' => '2025-05-10', 'statut' => 'Inscrit',
        ]);

        $this->getJson('/api/v1/administration/etudiants')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->getJson('/api/v1/administration/etudiants?'.http_build_query([
            'annee_academique_id' => $annee->id,
            'eglise_id' => $egliseA->id,
            'civilite_id' => $monsieur->id,
            'date_debut' => '2026-09-01',
            'date_fin' => '2026-09-30',
            'statut' => 'Préinscrit',
            'compte_cree' => true,
            'avec_dossier' => true,
            'dossier_statut' => 'Incomplet',
            'niveau_id' => $niveau->id,
            'promotion_id' => $promotion->id,
            'annee_entree' => 2026,
        ]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $cible->id)
            ->assertJsonPath('data.0.annee_academique.id', $annee->id)
            ->assertJsonPath('data.0.eglise.id', $egliseA->id)
            ->assertJsonPath('data.0.civilite.id', $monsieur->id);

        $this->getJson("/api/v1/administration/dossiers-etudiants?eglise_id={$egliseA->id}&statut=Incomplet")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.etudiant.id', $cible->id)
            ->assertJsonPath('data.0.etudiant.eglise.id', $egliseA->id);
    }

    public function test_les_dates_invalides_sont_refusees(): void
    {
        $role = Role::query()->create(['code' => 'ADMIN', 'libelle' => 'Administrateur']);
        Sanctum::actingAs(User::factory()->create(['id_role' => $role->id]));

        $this->getJson('/api/v1/administration/etudiants?date_debut=2026-10-01&date_fin=2026-09-01')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('date_fin');
    }

    public function test_les_roles_enseignant_et_etudiant_ne_peuvent_pas_lister_les_etudiants_et_les_dossiers(): void
    {
        foreach (['ENSEIGNANT', 'ETUDIANT'] as $codeRole) {
            $role = Role::query()->create(['code' => $codeRole, 'libelle' => $codeRole]);
            Sanctum::actingAs(User::factory()->create(['id_role' => $role->id]));

            $this->getJson('/api/v1/administration/etudiants')->assertForbidden();
            $this->getJson('/api/v1/administration/dossiers-etudiants')->assertForbidden();
        }
    }
}

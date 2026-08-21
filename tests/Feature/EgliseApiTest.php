<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EgliseApiTest extends TestCase
{
    use RefreshDatabase;

    private function authentifierAdministrateur(): User
    {
        $role = Role::query()->firstOrCreate(
            ['code' => 'ADMIN'],
            ['libelle' => 'Administrateur'],
        );
        $administrateur = User::factory()->create(['id_role' => $role->id]);
        Sanctum::actingAs($administrateur);

        return $administrateur;
    }

    public function test_un_utilisateur_authentifie_peut_creer_une_eglise(): void
    {
        $administrateur = $this->authentifierAdministrateur();

        $this->postJson('/api/v1/eglises', [
            'nom' => 'Église Cité de la Grâce',
            'sigle' => 'ECG',
            'pasteur_principal' => 'Pasteur Yao Thomas',
            'ville_commune' => 'Abidjan',
            'telephone' => '+2250102030405',
            'email' => 'eglise@example.test',
            'capacite_max_stagiaires' => 25,
            'representants' => [
                ['nom' => 'Kouassi', 'prenoms' => 'Jean', 'fonction' => 'Secrétaire'],
                ['nom' => 'Yao', 'prenoms' => 'Marie', 'fonction' => 'Trésorière'],
            ],
        ])->assertCreated()
            ->assertJsonPath('message', 'Église créée avec succès.')
            ->assertJsonPath('eglise.code', 'EGL-000001')
            ->assertJsonPath('eglise.sigle', 'ECG')
            ->assertJsonPath('eglise.user_id', $administrateur->id)
            ->assertJsonPath('eglise.user_code', $administrateur->code)
            ->assertJsonPath('eglise.created_by', $administrateur->id)
            ->assertJsonCount(2, 'eglise.representants');

        $this->assertDatabaseHas('eglises', [
            'code' => 'EGL-000001',
            'nom' => 'Église Cité de la Grâce',
            'user_id' => $administrateur->id,
            'user_code' => $administrateur->code,
            'created_by' => $administrateur->id,
        ]);
    }

    public function test_un_utilisateur_peut_lister_afficher_modifier_et_supprimer_une_eglise(): void
    {
        $administrateur = $this->authentifierAdministrateur();

        $egliseId = $this->postJson('/api/v1/eglises', [
            'nom' => 'Église Béthel',
            'sigle' => 'EB',
            'ville_commune' => 'Bouaké',
        ])->assertCreated()->json('eglise.id');

        $this->getJson('/api/v1/eglises?recherche=Bethel')
            ->assertOk()
            ->assertJsonPath('total', 0);

        $this->getJson('/api/v1/eglises?statut=Active')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $egliseId);

        $this->getJson("/api/v1/eglises/{$egliseId}")
            ->assertOk()
            ->assertJsonPath('eglise.sigle', 'EB');

        $this->patchJson("/api/v1/eglises/{$egliseId}", [
            'pasteur_principal' => 'Pasteur Koffi',
            'statut' => 'Suspendue',
        ])->assertOk()
            ->assertJsonPath('eglise.pasteur_principal', 'Pasteur Koffi')
            ->assertJsonPath('eglise.statut', 'Suspendue')
            ->assertJsonPath('eglise.updated_by', $administrateur->id);

        $this->deleteJson("/api/v1/eglises/{$egliseId}")
            ->assertOk()
            ->assertJsonPath('message', 'Église supprimée avec succès.');

        $this->assertSoftDeleted('eglises', [
            'id' => $egliseId,
            'deleted_by' => $administrateur->id,
        ]);
    }

    public function test_le_code_envoye_par_le_frontend_est_ignore_et_les_champs_techniques_sont_interdits(): void
    {
        $this->authentifierAdministrateur();

        $this->postJson('/api/v1/eglises', [
            'code' => 'CODE-CHOISI',
            'user_id' => 999,
            'user_code' => 'CODE-CHOISI',
            'created_by' => 999,
            'nom' => 'Église Test',
            'ville_commune' => 'Abidjan',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['user_id', 'user_code', 'created_by'])
            ->assertJsonMissingValidationErrors(['code']);

        $this->postJson('/api/v1/eglises', [
            'code' => 'CODE-CHOISI',
            'nom' => 'Église Test',
            'ville_commune' => 'Abidjan',
        ])->assertCreated()
            ->assertJsonPath('eglise.code', 'EGL-000001');
    }

    public function test_le_crud_des_eglises_exige_une_authentification(): void
    {
        $this->getJson('/api/v1/eglises')->assertUnauthorized();
        $this->postJson('/api/v1/eglises', [])->assertUnauthorized();
    }

    public function test_la_creation_valide_les_champs_obligatoires_et_les_representants(): void
    {
        $this->authentifierAdministrateur();

        $this->postJson('/api/v1/eglises', [
            'representants' => [
                ['fonction' => 'Secrétaire', 'email' => 'adresse-invalide'],
            ],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors([
                'nom',
                'ville_commune',
                'representants.0.nom',
                'representants.0.email',
            ]);
    }

    public function test_le_sigle_est_unique_et_peut_rester_inchange_lors_d_une_modification(): void
    {
        $this->authentifierAdministrateur();

        $egliseId = $this->postJson('/api/v1/eglises', [
            'nom' => 'Église Une',
            'sigle' => 'EU',
            'ville_commune' => 'Abidjan',
        ])->assertCreated()->json('eglise.id');

        $this->patchJson("/api/v1/eglises/{$egliseId}", ['sigle' => 'EU'])
            ->assertOk()
            ->assertJsonPath('eglise.sigle', 'EU');

        $this->postJson('/api/v1/eglises', [
            'nom' => 'Église Deux',
            'sigle' => 'EU',
            'ville_commune' => 'Yamoussoukro',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('sigle');
    }

    public function test_les_codes_des_eglises_sont_generes_en_sequence(): void
    {
        $this->authentifierAdministrateur();

        $premierCode = $this->postJson('/api/v1/eglises', [
            'nom' => 'Église Une', 'ville_commune' => 'Abidjan',
        ])->assertCreated()->json('eglise.code');

        $deuxiemeCode = $this->postJson('/api/v1/eglises', [
            'nom' => 'Église Deux', 'ville_commune' => 'Bouaké',
        ])->assertCreated()->json('eglise.code');

        $this->assertSame('EGL-000001', $premierCode);
        $this->assertSame('EGL-000002', $deuxiemeCode);
    }

    public function test_user_id_et_user_code_restent_ceux_du_createur(): void
    {
        $administrateur = $this->authentifierAdministrateur();

        $egliseId = $this->postJson('/api/v1/eglises', [
            'nom' => 'Église Test',
            'ville_commune' => 'Abidjan',
        ])->assertCreated()
            ->assertJsonPath('eglise.user_id', $administrateur->id)
            ->assertJsonPath('eglise.user_code', $administrateur->code)
            ->json('eglise.id');

        $this->patchJson("/api/v1/eglises/{$egliseId}", [
            'user_id' => 999,
            'user_code' => 'CODE-INTERDIT',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['user_id', 'user_code']);

        $this->assertDatabaseHas('eglises', [
            'id' => $egliseId,
            'user_id' => $administrateur->id,
            'user_code' => $administrateur->code,
        ]);
    }

    public function test_la_liste_filtre_les_eglises_et_compte_leurs_etudiants(): void
    {
        $this->authentifierAdministrateur();
        $egliseId = $this->postJson('/api/v1/eglises', [
            'nom' => 'Église Alliance Cocody',
            'sigle' => 'EAC',
            'pasteur_principal' => 'Pasteur Koffi',
            'denomination' => 'Alliance chrétienne',
            'region' => 'Abidjan',
            'district' => 'Nord',
            'ville_commune' => 'Cocody',
            'capacite_max_stagiaires' => 40,
        ])->assertCreated()->json('eglise.id');

        DB::table('etudiants')->insert([
            'matricule' => 'EBAC-0001-2026', 'nom' => 'Kouassi', 'prenoms' => 'Jean',
            'date_inscription' => '2026-08-20', 'eglise_id' => $egliseId,
        ]);
        DB::table('etudiants')->insert([
            'matricule' => 'EBAC-0002-2026', 'nom' => 'Yao', 'prenoms' => 'Marie',
            'date_inscription' => '2026-08-20', 'eglise_id' => $egliseId,
        ]);

        $this->getJson('/api/v1/eglises?ville=Cocody&region=Abidjan&district=Nord&denomination=Alliance%20chrétienne&pasteur=Koffi&capacite_min=30&avec_etudiants=1&q=Alliance')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $egliseId)
            ->assertJsonPath('data.0.nombre_etudiants', 2)
            ->assertJsonPath('data.0.createur.id', auth()->id());

        $this->getJson("/api/v1/eglises/{$egliseId}")
            ->assertOk()
            ->assertJsonPath('eglise.nombre_etudiants', 2);
    }

    public function test_le_code_est_ignore_et_les_autres_champs_techniques_restent_proteges_lors_de_la_modification(): void
    {
        $this->authentifierAdministrateur();

        $egliseId = $this->postJson('/api/v1/eglises', [
            'nom' => 'Église Test', 'ville_commune' => 'Abidjan',
        ])->assertCreated()->json('eglise.id');

        $this->patchJson("/api/v1/eglises/{$egliseId}", [
            'code' => 'CODE-MODIFIE',
            'user_code' => 'CODE-MODIFIE',
            'deleted_by' => 999,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['user_code', 'deleted_by'])
            ->assertJsonMissingValidationErrors(['code']);

        $this->patchJson("/api/v1/eglises/{$egliseId}", ['code' => 'CODE-MODIFIE'])
            ->assertOk()
            ->assertJsonPath('eglise.code', 'EGL-000001');

        $this->assertDatabaseHas('eglises', [
            'id' => $egliseId,
            'code' => 'EGL-000001',
            'deleted_by' => null,
        ]);
    }

    public function test_le_detail_d_une_eglise_compte_les_etudiants_par_dernier_niveau(): void
    {
        $this->authentifierAdministrateur();
        $egliseId = $this->postJson('/api/v1/eglises', [
            'nom' => 'Église des étudiants', 'ville_commune' => 'Abidjan',
        ])->assertCreated()->json('eglise.id');

        $niveauA1 = DB::table('niveaux')->insertGetId([
            'code' => 'A1', 'libelle' => 'Première Année', 'rang' => 1, 'statut' => 'Actif',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $niveauA2 = DB::table('niveaux')->insertGetId([
            'code' => 'A2', 'libelle' => 'Deuxième Année', 'rang' => 2, 'statut' => 'Actif',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $anneeId = DB::table('annees_academiques')->insertGetId([
            'libelle' => '2026-2027', 'date_debut' => '2026-09-01', 'date_fin' => '2027-07-31',
            'active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $promotionA1 = DB::table('promotions')->insertGetId([
            'code' => 'PROMO-A1', 'num_promotion' => 1, 'id_annee_academique' => $anneeId, 'id_niveau' => $niveauA1,
            'statut' => 'Active', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $promotionA2 = DB::table('promotions')->insertGetId([
            'code' => 'PROMO-A2', 'num_promotion' => 2, 'id_annee_academique' => $anneeId, 'id_niveau' => $niveauA2,
            'statut' => 'Active', 'created_at' => now(), 'updated_at' => now(),
        ]);

        foreach (range(1, 3) as $index) {
            DB::table('etudiants')->insert([
                'matricule' => "EBAC-NIV-{$index}", 'nom' => "Nom {$index}", 'prenoms' => "Prénoms {$index}",
                'date_inscription' => '2026-08-20', 'eglise_id' => $egliseId,
            ]);
        }
        $etudiants = DB::table('etudiants')->where('eglise_id', $egliseId)->orderBy('id')->pluck('id');
        DB::table('inscriptions')->insert([
            ['id_etudiant' => $etudiants[0], 'id_promotion' => $promotionA1, 'date_inscription' => '2025-09-01', 'statut' => 'Terminee', 'created_at' => now(), 'updated_at' => now()],
            ['id_etudiant' => $etudiants[0], 'id_promotion' => $promotionA2, 'date_inscription' => '2026-09-01', 'statut' => 'En formation', 'created_at' => now(), 'updated_at' => now()],
            ['id_etudiant' => $etudiants[1], 'id_promotion' => $promotionA1, 'date_inscription' => '2026-09-01', 'statut' => 'En formation', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->getJson("/api/v1/eglises/{$egliseId}")
            ->assertOk()
            ->assertJsonPath('eglise.nombre_etudiants', 3)
            ->assertJsonPath('eglise.statistiques_etudiants.total', 3)
            ->assertJsonPath('eglise.statistiques_etudiants.avec_niveau', 2)
            ->assertJsonPath('eglise.statistiques_etudiants.sans_niveau', 1)
            ->assertJsonPath('eglise.statistiques_etudiants.par_niveau.0.niveau_code', 'A1')
            ->assertJsonPath('eglise.statistiques_etudiants.par_niveau.0.nombre_etudiants', 1)
            ->assertJsonPath('eglise.statistiques_etudiants.par_niveau.1.niveau_code', 'A2')
            ->assertJsonPath('eglise.statistiques_etudiants.par_niveau.1.nombre_etudiants', 1)
            ->assertJsonPath('eglise.compte.id', auth()->id())
            ->assertJsonPath('eglise.createur.id', auth()->id());
    }
}

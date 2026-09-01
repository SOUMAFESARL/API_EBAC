<?php

namespace Tests\Feature;

use App\Models\Niveau;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PromotionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_toutes_les_routes_des_promotions_exigent_une_authentification(): void
    {
        $this->getJson('/api/v1/parametres/promotions')->assertUnauthorized();
        $this->postJson('/api/v1/parametres/promotions', [])->assertUnauthorized();
        $this->getJson('/api/v1/parametres/promotions/1')->assertUnauthorized();
        $this->putJson('/api/v1/parametres/promotions/1', [])->assertUnauthorized();
        $this->patchJson('/api/v1/parametres/promotions/1', [])->assertUnauthorized();
        $this->deleteJson('/api/v1/parametres/promotions/1')->assertUnauthorized();
    }

    public function test_crud_filtres_et_nombre_d_etudiants(): void
    {
        $role = Role::query()->create(['code' => 'ADMIN', 'libelle' => 'Administrateur']);
        $utilisateur = User::factory()->create(['id_role' => $role->id]);
        Sanctum::actingAs($utilisateur);
        $niveau = Niveau::query()->create(['libelle' => 'Première année', 'code' => 'A1', 'rang' => 1]);

        $id = $this->postJson('/api/v1/parametres/promotions', [
            'num_promotion' => 1,
            'annee_entree' => 2026,
            'id_niveau' => $niveau->id,
            'statut' => 'Active',
            'date_ouverture' => '2026-09-01',
            'date_cloture' => '2027-07-31',
        ])->assertCreated()
            ->assertJsonPath('promotion.code', 'PROMO-000001')
            ->assertJsonPath('promotion.num_promotion', 1)
            ->assertJsonPath('promotion.annee_entree', 2026)
            ->assertJsonPath('promotion.nombre_etudiants', 0)
            ->json('promotion.id');

        $etudiant = DB::table('etudiants')->insertGetId([
            'matricule' => 'ETU-001', 'nom' => 'Kouassi', 'prenoms' => 'Jean',
            'date_inscription' => '2026-09-01', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('inscriptions')->insert([
            'id_etudiant' => $etudiant, 'id_promotion' => $id, 'date_inscription' => '2026-09-01',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->getJson("/api/v1/parametres/promotions?niveau={$niveau->id}&annee=2026&status=Active&promotion=PROMO-000001")
            ->assertOk()
            ->assertJsonCount(1, 'promotions')
            ->assertJsonPath('promotions.0.nombre_etudiants', 1);
        $this->getJson("/api/v1/parametres/promotions/{$id}")
            ->assertOk()->assertJsonPath('promotion.nombre_etudiants', 1);
        $this->patchJson("/api/v1/parametres/promotions/{$id}", ['num_promotion' => 2])
            ->assertOk()->assertJsonPath('promotion.num_promotion', 2);
        $this->deleteJson("/api/v1/parametres/promotions/{$id}")->assertOk();
        $this->assertSoftDeleted('promotions', ['id' => $id, 'deleted_by' => $utilisateur->id]);
    }

    public function test_creation_valide_les_relations_et_les_dates(): void
    {
        $role = Role::query()->create(['code' => 'ADMIN', 'libelle' => 'Administrateur']);
        Sanctum::actingAs(User::factory()->create(['id_role' => $role->id]));

        $this->postJson('/api/v1/parametres/promotions', [
            'annee_entree' => 26, 'id_niveau' => 999,
            'date_ouverture' => '2027-07-31', 'date_cloture' => '2026-09-01',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['num_promotion', 'annee_entree', 'id_niveau', 'date_cloture']);

        $this->postJson('/api/v1/parametres/promotions', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['num_promotion', 'annee_entree']);
    }

    public function test_le_code_est_genere_par_le_serveur_et_ne_peut_pas_etre_modifie(): void
    {
        $role = Role::query()->create(['code' => 'ADMIN', 'libelle' => 'Administrateur']);
        Sanctum::actingAs(User::factory()->create(['id_role' => $role->id]));
        $niveau = Niveau::query()->create(['libelle' => 'Première année', 'code' => 'A1', 'rang' => 1]);

        $payload = ['num_promotion' => 1, 'annee_entree' => 2026, 'id_niveau' => $niveau->id];

        $this->postJson('/api/v1/parametres/promotions', [...$payload, 'code' => 'CODE-CLIENT'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');

        $promotion = $this->postJson('/api/v1/parametres/promotions', $payload)
            ->assertCreated()
            ->assertJsonPath('promotion.code', 'PROMO-000001')
            ->json('promotion');

        $this->postJson('/api/v1/parametres/promotions', [...$payload, 'num_promotion' => 2])
            ->assertCreated()
            ->assertJsonPath('promotion.code', 'PROMO-000002');

        $this->patchJson("/api/v1/parametres/promotions/{$promotion['id']}", ['code' => 'CODE-MODIFIE'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');
    }
}

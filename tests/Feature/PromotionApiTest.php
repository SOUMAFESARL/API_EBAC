<?php

namespace Tests\Feature;

use App\Models\AnneeAcademique;
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

    public function test_crud_filtres_et_nombre_d_etudiants(): void
    {
        $role = Role::query()->create(['code' => 'ADMIN', 'libelle' => 'Administrateur']);
        $utilisateur = User::factory()->create(['id_role' => $role->id]);
        Sanctum::actingAs($utilisateur);
        $annee = AnneeAcademique::query()->create([
            'libelle' => '2026-2027', 'date_debut' => '2026-09-01', 'date_fin' => '2027-07-31',
        ]);
        $niveau = Niveau::query()->create(['libelle' => 'Première année', 'code' => 'A1', 'rang' => 1]);

        $id = $this->postJson('/api/v1/parametres/promotions', [
            'code' => 'PROMO-2026-A1',
            'id_annee_academique' => $annee->id,
            'id_niveau' => $niveau->id,
            'capacite' => 30,
            'statut' => 'Active',
            'date_ouverture' => '2026-09-01',
            'date_cloture' => '2027-07-31',
        ])->assertCreated()
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

        $this->getJson("/api/v1/parametres/promotions?niveau={$niveau->id}&annee={$annee->id}&status=Active&promotion=PROMO-2026")
            ->assertOk()
            ->assertJsonCount(1, 'promotions')
            ->assertJsonPath('promotions.0.nombre_etudiants', 1);
        $this->getJson("/api/v1/parametres/promotions/{$id}")
            ->assertOk()->assertJsonPath('promotion.nombre_etudiants', 1);
        $this->patchJson("/api/v1/parametres/promotions/{$id}", ['capacite' => 35])
            ->assertOk()->assertJsonPath('promotion.capacite', 35);
        $this->deleteJson("/api/v1/parametres/promotions/{$id}")->assertOk();
        $this->assertSoftDeleted('promotions', ['id' => $id, 'deleted_by' => $utilisateur->id]);
    }

    public function test_creation_valide_les_relations_et_les_dates(): void
    {
        $role = Role::query()->create(['code' => 'ADMIN', 'libelle' => 'Administrateur']);
        Sanctum::actingAs(User::factory()->create(['id_role' => $role->id]));

        $this->postJson('/api/v1/parametres/promotions', [
            'code' => 'PROMO-X', 'id_annee_academique' => 999, 'id_niveau' => 999,
            'date_ouverture' => '2027-07-31', 'date_cloture' => '2026-09-01',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['id_annee_academique', 'id_niveau', 'date_cloture']);
    }
}

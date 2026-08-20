<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Models\AnneeAcademique;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NiveauApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_utilisateur_authentifie_peut_creer_un_niveau(): void
    {
        $role = Role::query()->create(['code' => 'ADMIN', 'libelle' => 'Administrateur']);
        $utilisateur = User::factory()->create([
            'id_role' => $role->id,
            'user_code' => 'USR-NIVEAU-001',
        ]);
        Sanctum::actingAs($utilisateur);

        $this->postJson('/api/v1/parametres/niveaux', [
            'libelle' => 'Première Année',
            'code' => 'A1',
            'rang' => 1,
            'statut' => 'Actif',
        ])->assertCreated()
            ->assertJsonPath('message', 'Niveau créé avec succès.')
            ->assertJsonPath('niveau.libelle', 'Première Année')
            ->assertJsonPath('niveau.code', 'A1')
            ->assertJsonPath('niveau.rang', 1)
            ->assertJsonPath('niveau.statut', 'Actif')
            ->assertJsonPath('niveau.user_id', $utilisateur->id)
            ->assertJsonPath('niveau.user_code', 'USR-NIVEAU-001')
            ->assertJsonPath('niveau.created_by', $utilisateur->id);

        $this->assertDatabaseHas('niveaux', [
            'libelle' => 'Première Année',
            'code' => 'A1',
            'rang' => 1,
            'statut' => 'Actif',
            'user_id' => $utilisateur->id,
            'user_code' => 'USR-NIVEAU-001',
            'created_by' => $utilisateur->id,
        ]);
    }

    public function test_le_code_et_le_rang_d_un_niveau_doivent_etre_uniques(): void
    {
        $role = Role::query()->create(['code' => 'ADMIN', 'libelle' => 'Administrateur']);
        Sanctum::actingAs(User::factory()->create(['id_role' => $role->id]));

        $payload = ['libelle' => 'Première Année', 'code' => 'A1', 'rang' => 1];
        $this->postJson('/api/v1/parametres/niveaux', $payload)->assertCreated();

        $this->postJson('/api/v1/parametres/niveaux', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['code', 'rang']);
    }

    public function test_la_creation_d_un_niveau_exige_une_authentification(): void
    {
        $this->postJson('/api/v1/parametres/niveaux', [
            'libelle' => 'Première Année',
            'code' => 'A1',
            'rang' => 1,
        ])->assertUnauthorized();
    }

    public function test_un_utilisateur_authentifie_peut_lister_modifier_et_supprimer_un_niveau(): void
    {
        $role = Role::query()->create(['code' => 'ADMIN', 'libelle' => 'Administrateur']);
        Sanctum::actingAs(User::factory()->create(['id_role' => $role->id]));

        $niveauDeux = $this->postJson('/api/v1/parametres/niveaux', [
            'libelle' => 'Deuxième Année', 'code' => 'A2', 'rang' => 2,
        ])->assertCreated()->json('niveau.id');

        $niveauUn = $this->postJson('/api/v1/parametres/niveaux', [
            'libelle' => 'Première Année', 'code' => 'A1', 'rang' => 1,
        ])->assertCreated()->json('niveau.id');

        $this->getJson('/api/v1/parametres/niveaux')
            ->assertOk()
            ->assertJsonCount(2, 'niveaux')
            ->assertJsonPath('niveaux.0.id', $niveauUn)
            ->assertJsonPath('niveaux.1.id', $niveauDeux);

        $this->getJson("/api/v1/parametres/niveaux/{$niveauUn}")
            ->assertOk()
            ->assertJsonPath('niveau.code', 'A1');

        $this->patchJson("/api/v1/parametres/niveaux/{$niveauUn}", [
            'libelle' => 'Niveau 1',
            'statut' => 'Archive',
        ])->assertOk()
            ->assertJsonPath('niveau.libelle', 'Niveau 1')
            ->assertJsonPath('niveau.statut', 'Archive');

        $this->deleteJson("/api/v1/parametres/niveaux/{$niveauDeux}")
            ->assertOk()
            ->assertJsonPath('message', 'Niveau supprimé avec succès.');

        $this->assertSoftDeleted('niveaux', [
            'id' => $niveauDeux,
            'deleted_by' => auth()->id(),
        ]);
    }

    public function test_un_niveau_utilise_par_une_promotion_ne_peut_pas_etre_supprime(): void
    {
        $role = Role::query()->create(['code' => 'ADMIN', 'libelle' => 'Administrateur']);
        Sanctum::actingAs(User::factory()->create(['id_role' => $role->id]));

        $niveauId = $this->postJson('/api/v1/parametres/niveaux', [
            'libelle' => 'Première Année', 'code' => 'A1', 'rang' => 1,
        ])->assertCreated()->json('niveau.id');

        $anneeAcademique = AnneeAcademique::query()->create([
            'libelle' => '2026-2027',
            'date_debut' => '2026-09-01',
            'date_fin' => '2027-07-31',
        ]);

        \DB::table('promotions')->insert([
            'code' => 'PROMO-2026-A1',
            'id_annee_academique' => $anneeAcademique->id,
            'id_niveau' => $niveauId,
            'statut' => 'Active',
        ]);

        $this->deleteJson("/api/v1/parametres/niveaux/{$niveauId}")
            ->assertUnprocessable();

        $this->assertDatabaseHas('niveaux', ['id' => $niveauId]);
    }
}

<?php

namespace Tests\Feature;

use App\Models\DossierEtudiant;
use App\Models\Etudiant;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PhotoEtudiantProfilTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_photo_est_partagee_depuis_le_dossier_et_le_profil(): void
    {
        Storage::fake('public');
        $role = Role::query()->create(['code' => 'ETUDIANT', 'libelle' => 'Étudiant']);
        $compte = User::factory()->create(['id_role' => $role->id, 'photo' => 'comptes/ancienne.jpg']);
        $autreCompte = User::factory()->create(['photo' => 'comptes/autre.jpg']);
        $etudiant = Etudiant::query()->create([
            'user_id' => $compte->id, 'matricule' => 'PHOTO-001', 'nom' => 'YAO', 'prenoms' => 'Anne',
            'date_inscription' => now()->toDateString(), 'photo_identite' => 'etudiants/ancienne.jpg',
        ]);
        DossierEtudiant::query()->create([
            'id_etudiant' => $etudiant->id, 'numero_dossier' => 'PHOTO-DOS-001',
            'date_ouverture' => now()->toDateString(),
        ]);
        foreach (['comptes/ancienne.jpg', 'etudiants/ancienne.jpg', 'comptes/autre.jpg'] as $chemin) {
            Storage::disk('public')->put($chemin, 'ancienne photo');
        }
        Sanctum::actingAs($compte);

        $this->post('/api/v1/etudiant/dossier', [
            'photo_identite' => UploadedFile::fake()->image('identite.jpg'),
        ])->assertOk();
        $photo = $etudiant->fresh()->photo_identite;
        $this->assertSame($photo, $compte->fresh()->photo);
        Storage::disk('public')->assertExists($photo);
        Storage::disk('public')->assertMissing(['comptes/ancienne.jpg', 'etudiants/ancienne.jpg']);
        $this->getJson('/api/v1/administration/profil')->assertOk()->assertJsonPath('profil.photo', $photo);

        $this->patchJson('/api/v1/administration/profil', ['nom' => 'KOUAME'])->assertOk();
        $this->assertSame($photo, $etudiant->fresh()->photo_identite);
        Storage::disk('public')->assertExists($photo);

        $this->post('/api/v1/administration/profil', [
            'photo' => UploadedFile::fake()->image('profil.jpg'),
        ])->assertOk();
        $nouvellePhoto = $compte->fresh()->photo;
        $this->assertNotSame($photo, $nouvellePhoto);
        $this->assertSame($nouvellePhoto, $etudiant->fresh()->photo_identite);
        Storage::disk('public')->assertExists($nouvellePhoto);
        Storage::disk('public')->assertMissing($photo);
        $this->getJson('/api/v1/etudiant/dossier')->assertOk()
            ->assertJsonPath('dossier.informations_personnelles.compte.photo', $nouvellePhoto);

        $this->post('/api/v1/administration/profil', [
            'photo' => UploadedFile::fake()->create('invalide.pdf', 10, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertUnprocessable();
        $this->assertSame($nouvellePhoto, $etudiant->fresh()->photo_identite);
        $this->assertSame($nouvellePhoto, $compte->fresh()->photo);
        Storage::disk('public')->assertExists($nouvellePhoto);

        $this->patchJson('/api/v1/administration/profil', ['photo' => null])->assertOk()
            ->assertJsonPath('profil.photo', null);
        $this->assertNull($etudiant->fresh()->photo_identite);
        Storage::disk('public')->assertMissing($nouvellePhoto);
        $this->assertSame('comptes/autre.jpg', $autreCompte->fresh()->photo);
        Storage::disk('public')->assertExists('comptes/autre.jpg');
    }
}

<?php

namespace Tests\Feature;

use App\Models\ConnexionDeuxFacteurs;
use App\Models\DossierEtudiant;
use App\Models\Etudiant;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PhotoEtudiantProfilTest extends TestCase
{
    use RefreshDatabase;

    public static function modesConnexion(): array
    {
        return [[true], [false]];
    }

    #[DataProvider('modesConnexion')]
    public function test_la_connexion_conserve_la_photo_de_profil(bool $sansOtp): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('comptes/profil.jpg', 'photo de profil');
        $role = Role::query()->create(['code' => 'ETUDIANT', 'libelle' => 'Etudiant']);
        $compte = User::factory()->create([
            'id_role' => $role->id, 'photo' => 'comptes/profil.jpg',
            'prochaine_connexion_sans_otp' => $sansOtp,
        ]);
        $etudiant = Etudiant::query()->create([
            'user_id' => $compte->id, 'nom' => 'YAO', 'prenoms' => 'Anne',
            'date_inscription' => now()->toDateString(), 'photo_identite' => 'etudiants/identite.jpg',
        ]);

        if ($sansOtp) {
            $reponse = $this->postJson('/api/v1/auth/connexion', [
                'email' => $compte->email, 'password' => 'password',
            ]);
        } else {
            $tentative = ConnexionDeuxFacteurs::query()->create([
                'id_users' => $compte->id, 'code_otp_hash' => Hash::make('123456'),
                'canal' => 'Email', 'envoye_le' => now(), 'nom_appareil' => 'web',
            ]);
            $reponse = $this->postJson('/api/v1/auth/confirmer-otp', [
                'id_tentative' => $tentative->getKey(), 'code_otp' => '123456',
            ]);
        }

        $reponse->assertOk()->assertJsonPath('utilisateur.photo', 'comptes/profil.jpg');
        $image = $this->get($reponse->json('utilisateur.photo_url'))->assertOk();
        $this->assertSame('photo de profil', file_get_contents($image->getFile()->getPathname()));
        $this->assertSame('comptes/profil.jpg', $compte->fresh()->photo);
        $this->assertSame('etudiants/identite.jpg', $etudiant->fresh()->photo_identite);
    }

    public function test_la_photo_de_profil_reste_independante_de_la_photo_identite(): void
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
        $ancienneUrl = $this->getJson('/api/v1/administration/profil')->assertOk()->json('profil.photo_url');

        $reponse = $this->post('/api/v1/etudiant/dossier', [
            'photo_identite' => UploadedFile::fake()->image('identite.jpg'),
        ])->assertOk();
        $photo = $etudiant->fresh()->photo_identite;
        $url = $reponse->json('dossier.informations_personnelles.compte.photo_url');
        $this->assertNotNull($url);
        $this->assertSame($ancienneUrl, $url);
        $image = $this->get($url)->assertOk();
        $this->assertTrue($image->headers->hasCacheControlDirective('no-cache'));
        $this->assertFalse($image->headers->hasCacheControlDirective('public'));
        $this->assertSame(Storage::disk('public')->get('comptes/ancienne.jpg'), file_get_contents($image->getFile()->getPathname()));
        $this->assertSame('comptes/ancienne.jpg', $compte->fresh()->photo);
        Storage::disk('public')->assertExists($photo);
        Storage::disk('public')->assertExists('comptes/ancienne.jpg');
        Storage::disk('public')->assertMissing('etudiants/ancienne.jpg');
        $this->getJson('/api/v1/administration/profil')->assertOk()
            ->assertJsonPath('profil.photo', 'comptes/ancienne.jpg')->assertJsonPath('profil.photo_url', $url);

        $this->patchJson('/api/v1/administration/profil', ['nom' => 'KOUAME'])->assertOk();
        $this->assertSame($photo, $etudiant->fresh()->photo_identite);
        Storage::disk('public')->assertExists($photo);

        $this->post('/api/v1/administration/profil', [
            'photo' => UploadedFile::fake()->image('profil.jpg'),
        ])->assertOk();
        $nouvellePhoto = $compte->fresh()->photo;
        $nouvelleUrl = $this->getJson('/api/v1/administration/profil')->assertOk()->json('profil.photo_url');
        $this->assertNotSame($url, $nouvelleUrl);
        $image = $this->get($nouvelleUrl)->assertOk();
        $this->assertSame(Storage::disk('public')->get($nouvellePhoto), file_get_contents($image->getFile()->getPathname()));
        $this->assertNotSame($photo, $nouvellePhoto);
        $this->assertSame($photo, $etudiant->fresh()->photo_identite);
        Storage::disk('public')->assertExists($nouvellePhoto);
        Storage::disk('public')->assertExists($photo);
        Storage::disk('public')->assertMissing('comptes/ancienne.jpg');
        $this->getJson('/api/v1/etudiant/dossier')->assertOk()
            ->assertJsonPath('dossier.informations_personnelles.compte.photo', $nouvellePhoto);

        $this->post('/api/v1/administration/profil', [
            'photo' => UploadedFile::fake()->create('invalide.pdf', 10, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertUnprocessable();
        $this->assertSame($photo, $etudiant->fresh()->photo_identite);
        $this->assertSame($nouvellePhoto, $compte->fresh()->photo);
        Storage::disk('public')->assertExists($nouvellePhoto);

        $this->patchJson('/api/v1/administration/profil', ['photo' => null])->assertOk()
            ->assertJsonPath('profil.photo', null)->assertJsonPath('profil.photo_url', null);
        $this->assertSame($photo, $etudiant->fresh()->photo_identite);
        Storage::disk('public')->assertExists($photo);
        Storage::disk('public')->assertMissing($nouvellePhoto);
        $this->assertSame('comptes/autre.jpg', $autreCompte->fresh()->photo);
        Storage::disk('public')->assertExists('comptes/autre.jpg');
    }
}

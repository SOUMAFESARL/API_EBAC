<?php

namespace Tests\Feature;

use App\Models\ConfigurationEcole;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ConfigurationEcoleApiTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/v1/parametres/ecole';

    private function connecter(string $role): User
    {
        $profil = Role::firstOrCreate(['code' => $role], ['libelle' => $role]);
        $user = User::factory()->create(['id_role' => $profil->id]);
        Sanctum::actingAs($user);

        return $user;
    }

    public function test_configuration_unique_fichiers_et_mise_a_jour_partielle(): void
    {
        Storage::fake('local');
        $this->connecter('ADMIN');
        $this->getJson(self::URL)->assertOk()->assertJsonPath('ecole', null);
        $donnees = ['nom_ecole' => 'École Biblique', 'code_ecole' => 'EBAC', 'slogan' => 'Former pour servir',
            'adresse' => 'Rue des Écoles', 'fondateur' => 'Fondateur Test', 'directeur_actuel' => 'Directeur Test',
            'situation_geographique' => 'Quartier Centre', 'ville' => 'Abidjan', 'region' => 'Lagunes',
            'email' => 'contact@example.test', 'telephone' => '+225 2700000000', 'cellulaire' => '+225 0100000000',
            'biographie' => 'Historique de l’école.'];
        foreach (['logo', 'favicon', 'signature', 'signature_directeur'] as $type) {
            $donnees[$type] = UploadedFile::fake()->image($type.'.png');
        }
        $reponse = $this->post(self::URL, $donnees)->assertCreated()
            ->assertJsonPath('ecole.nom_ecole', 'École Biblique')->assertJsonPath('ecole.biographie', 'Historique de l’école.')
            ->assertJsonMissingPath('ecole.signature_directeur')->assertJsonMissingPath('ecole.created_by');
        $ecole = ConfigurationEcole::findOrFail(1);
        foreach (['logo', 'favicon', 'signature', 'signature_directeur'] as $type) {
            Storage::disk('local')->assertExists($ecole->$type);
            $url = $reponse->json('ecole.'.$type.'_url');
            $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/png');
            $this->get('/api/v1/parametres/ecole/fichiers/'.$type)->assertForbidden();
        }
        $ancienLogo = $ecole->logo;
        $this->connecter('DIRECTION');
        $this->post(self::URL, ['logo' => UploadedFile::fake()->image('nouveau.png')])->assertOk();
        Storage::disk('local')->assertMissing($ancienLogo);
        Storage::disk('local')->assertExists($ecole->fresh()->logo);
        $signature = $ecole->signature_directeur;
        $this->patchJson(self::URL, ['signature_directeur' => null, 'slogan' => null])->assertOk()
            ->assertJsonPath('ecole.signature_directeur_url', null)->assertJsonPath('ecole.slogan', null)
            ->assertJsonPath('ecole.nom_ecole', 'École Biblique');
        Storage::disk('local')->assertMissing($signature);
        Storage::disk('local')->assertExists($ecole->signature);
        $this->putJson(self::URL, ['ville' => 'Bouaké'])->assertOk()->assertJsonPath('ecole.ville', 'Bouaké');
        $this->assertDatabaseCount('configuration_ecoles', 1);
        $urlExpiree = URL::temporarySignedRoute('api.v1.parametres.ecole.fichier', now()->subMinute(), ['type' => 'logo']);
        $this->get($urlExpiree)->assertForbidden();
        Storage::disk('local')->delete($ecole->fresh()->logo);
        $this->get(URL::temporarySignedRoute('api.v1.parametres.ecole.fichier', now()->addMinute(), ['type' => 'logo']))->assertNotFound();
    }

    public function test_validation_et_acces(): void
    {
        Storage::fake('local');
        $this->getJson(self::URL)->assertUnauthorized();
        $this->postJson(self::URL, [])->assertUnauthorized();
        foreach (['ETUDIANT', 'ENSEIGNANT', 'SECRETARIAT', 'SECRETAIRE_ACADEMIQUE'] as $role) {
            $this->connecter($role);
            $this->getJson(self::URL)->assertOk();
            foreach (['post', 'put', 'patch'] as $methode) {
                $this->json($methode, self::URL, ['nom_ecole' => 'Interdit', 'code_ecole' => 'NON'])->assertForbidden();
            }
        }
        $user = $this->connecter('ADMIN');
        $this->postJson(self::URL, [])->assertUnprocessable()->assertJsonValidationErrors(['nom_ecole', 'code_ecole']);
        $this->post(self::URL, ['nom_ecole' => 'Test', 'code_ecole' => 'TEST',
            'email' => 'invalide', 'signature' => UploadedFile::fake()->create('script.php', 1, 'text/plain')])
            ->assertUnprocessable()->assertJsonValidationErrors(['email', 'signature']);
        $this->assertDatabaseCount('configuration_ecoles', 0);
        $this->postJson(self::URL, ['nom_ecole' => 'Test', 'code_ecole' => 'TEST'])->assertCreated();
        $this->patchJson(self::URL, ['nom_ecole' => null])->assertUnprocessable();
        $this->patchJson(self::URL, [])->assertUnprocessable();
        $user->update(['is_active' => false]);
        $this->getJson(self::URL)->assertForbidden();
        $this->postJson(self::URL, ['slogan' => 'Test'])->assertForbidden();
    }
}

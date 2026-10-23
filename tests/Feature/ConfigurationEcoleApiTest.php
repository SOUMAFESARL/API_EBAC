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

    public function test_champs_complementaires_preservent_la_configuration_existante(): void
    {
        Storage::fake('local');
        $this->connecter('ADMIN');
        $this->post(self::URL, ['nom_ecole' => 'École existante', 'code_ecole' => 'CODE-INITIAL',
            'directeur_actuel' => 'Nom historique', 'slogan' => 'Slogan initial',
            'signature_directeur' => UploadedFile::fake()->image('signature.png')])->assertCreated();
        $signatureInitiale = ConfigurationEcole::findOrFail(1)->signature_directeur;
        $civilite = \App\Models\Civilite::create(['code' => 'M', 'name' => 'Monsieur']);
        $ajouts = ['sigle' => 'EBAC', 'nom_plateforme' => 'EBAC SIG',
            'arrete' => 'N°1287/SE/INT/AG du 30 octobre 1965', 'boite_postale' => 'BP 31 Toumodi',
            'site_web' => 'https://www.ebac-edu.org', 'directeur_civilite_id' => $civilite->id,
            'directeur_nom' => 'Kouamé', 'directeur_prenoms' => 'Jean',
            'directeur_academique_civilite_id' => $civilite->id,
            'directeur_academique_nom' => 'Koné', 'directeur_academique_prenoms' => 'Paul'];
        $this->patchJson(self::URL, $ajouts)->assertOk();
        $reponse = $this->getJson(self::URL)->assertOk()
            ->assertJsonPath('ecole.code_ecole', 'CODE-INITIAL')->assertJsonPath('ecole.nom_ecole', 'École existante')
            ->assertJsonPath('ecole.directeur_actuel', 'Nom historique')->assertJsonPath('ecole.slogan', 'Slogan initial');
        foreach ($ajouts as $champ => $valeur) {
            $reponse->assertJsonPath('ecole.'.$champ, $valeur);
        }
        $reponse = $this->post(self::URL, ['signature_directeur_academique' => UploadedFile::fake()->image('academique.png')])
            ->assertOk()->assertJsonMissingPath('ecole.signature_directeur_academique');
        $url = $reponse->json('ecole.signature_directeur_academique_url');
        $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/png');
        $ancien = ConfigurationEcole::findOrFail(1)->signature_directeur_academique;
        $this->post(self::URL, ['signature_directeur_academique' => UploadedFile::fake()->image('remplacement.png')])->assertOk();
        Storage::disk('local')->assertMissing($ancien);
        $nouveau = ConfigurationEcole::findOrFail(1)->signature_directeur_academique;
        $this->patchJson(self::URL, ['signature_directeur_academique' => null, 'boite_postale' => null])->assertOk()
            ->assertJsonPath('ecole.signature_directeur_academique_url', null)->assertJsonPath('ecole.boite_postale', null);
        Storage::disk('local')->assertMissing($nouveau);
        Storage::disk('local')->assertExists($signatureInitiale);
        $this->assertSame($signatureInitiale, ConfigurationEcole::findOrFail(1)->signature_directeur);
        $this->patchJson(self::URL, ['site_web' => 'javascript:alert(1)', 'directeur_civilite_id' => 999999,
            'directeur_academique_civilite_id' => 999999])->assertUnprocessable()
            ->assertJsonValidationErrors(['site_web', 'directeur_civilite_id', 'directeur_academique_civilite_id']);
        $this->assertDatabaseCount('configuration_ecoles', 1);
    }
}

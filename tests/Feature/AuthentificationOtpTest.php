<?php

namespace Tests\Feature;

use App\Models\ConnexionDeuxFacteurs;
use App\Models\Role;
use App\Models\User;
use App\Notifications\CodeOtpConnexionNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AuthentificationOtpTest extends TestCase
{
    use RefreshDatabase;

    public function test_des_identifiants_valides_envoient_un_otp_sans_creer_de_jeton(): void
    {
        Notification::fake();
        $utilisateur = $this->creerUtilisateur();

        $response = $this->postJson('/api/v1/auth/connexion', [
            'email' => $utilisateur->email,
            'password' => 'Password123',
            'nom_appareil' => 'mobile',
        ]);

        $response->assertAccepted()
            ->assertJsonPath('otp_requis', true)
            ->assertJsonMissing(['token']);

        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertDatabaseHas('connexions_2fa', [
            'id_users' => $utilisateur->id,
            'canal' => 'Email',
            'nom_appareil' => 'mobile',
        ]);
        Notification::assertSentTo($utilisateur, CodeOtpConnexionNotification::class);
    }

    public function test_un_otp_valide_termine_la_connexion_et_ne_peut_pas_etre_reutilise(): void
    {
        $utilisateur = $this->creerUtilisateur();
        $tentative = ConnexionDeuxFacteurs::query()->create([
            'id_users' => $utilisateur->id,
            'code_otp_hash' => Hash::make('123456'),
            'canal' => 'Email',
            'envoye_le' => now(),
            'nom_appareil' => 'web',
        ]);

        $payload = ['id_tentative' => $tentative->getKey(), 'code_otp' => '123456'];

        $this->postJson('/api/v1/auth/confirmer-otp', $payload)
            ->assertOk()
            ->assertJsonStructure(['token', 'token_type', 'utilisateur']);

        $this->postJson('/api/v1/auth/confirmer-otp', $payload)
            ->assertUnprocessable();
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_un_otp_expire_est_refuse(): void
    {
        $utilisateur = $this->creerUtilisateur();
        $tentative = ConnexionDeuxFacteurs::query()->create([
            'id_users' => $utilisateur->id,
            'code_otp_hash' => Hash::make('123456'),
            'canal' => 'Email',
            'envoye_le' => now()->subMinutes(11),
            'nom_appareil' => 'web',
        ]);

        $this->postJson('/api/v1/auth/confirmer-otp', [
            'id_tentative' => $tentative->getKey(),
            'code_otp' => '123456',
        ])->assertUnprocessable();
    }

    private function creerUtilisateur(): User
    {
        $role = Role::query()->create([
            'code' => 'ADMIN',
            'libelle' => 'Administrateur',
            'portee' => 'Globale',
        ]);

        return User::factory()->create([
            'id_role' => $role->id,
            'password' => 'Password123',
        ]);
    }
}

<?php

namespace Tests\Feature;

use App\Models\ConnexionDeuxFacteurs;
use App\Models\Role;
use App\Models\User;
use App\Notifications\CodeOtpConnexionNotification;
use App\Notifications\CodeReinitialisationMotDePasseNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

    public function test_le_template_email_otp_affiche_le_logo_et_le_code_mis_en_valeur(): void
    {
        $message = new class
        {
            public function embed(string $path): string
            {
                return 'cid:logo-ebac';
            }
        };

        $html = view('emails.code-otp-connexion', [
            'nomComplet' => 'Système Administrateur',
            'code' => '317098',
            'dureeValiditeMinutes' => 10,
            'message' => $message,
        ])->render();

        $this->assertStringContainsString('cid:logo-ebac', $html);
        $this->assertStringContainsString('317098', $html);
        $this->assertStringContainsString('background:#123b8f', $html);
        $this->assertStringContainsString('color:#ffffff', $html);
        $this->assertStringContainsString('border-radius:50%', $html);
        $this->assertStringContainsString('https://ebac.ci', $html);
        $this->assertStringContainsString('Adresse de connexion', $html);
        $this->assertStringNotContainsString('Regards,<br>Laravel', $html);
    }

    public function test_mot_de_passe_oublie_genere_un_code_a_six_chiffres_et_une_notification(): void
    {
        Notification::fake();
        $utilisateur = $this->creerUtilisateur();

        $this->postJson('/api/v1/auth/mot-de-passe-oublie', [
            'email' => $utilisateur->email,
        ])->assertOk();

        Notification::assertSentTo($utilisateur, CodeReinitialisationMotDePasseNotification::class);
        $this->assertDatabaseHas('password_reset_tokens', ['email' => $utilisateur->email]);

        $notification = Notification::sent($utilisateur, CodeReinitialisationMotDePasseNotification::class)->first();
        $code = (new \ReflectionClass($notification))->getProperty('code')->getValue($notification);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
        $this->assertTrue(Hash::check(
            $code,
            DB::table('password_reset_tokens')->where('email', $utilisateur->email)->value('token'),
        ));
    }

    public function test_un_code_valide_autorise_le_reset_puis_reinitialise_le_mot_de_passe(): void
    {
        Notification::fake();
        $utilisateur = $this->creerUtilisateur();
        $utilisateur->createToken('ancien-appareil');
        $this->postJson('/api/v1/auth/mot-de-passe-oublie', ['email' => $utilisateur->email])->assertOk();
        $notification = Notification::sent($utilisateur, CodeReinitialisationMotDePasseNotification::class)->first();
        $code = (new \ReflectionClass($notification))->getProperty('code')->getValue($notification);

        $verification = $this->postJson('/api/v1/auth/verifier-code-reinitialisation', [
            'code_otp' => $code,
            'email' => $utilisateur->email,
        ])->assertOk()
            ->assertJsonPath('reset_autorise', true)
            ->assertJsonStructure(['reset_token', 'expire_dans']);

        $this->postJson('/api/v1/auth/reinitialiser-mot-de-passe', [
            'reset_token' => $verification->json('reset_token'),
            'email' => $utilisateur->email,
            'password' => 'NouveauPassword123',
            'password_confirmation' => 'NouveauPassword123',
        ])->assertOk();

        $this->assertTrue(Hash::check('NouveauPassword123', $utilisateur->fresh()->password));
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $utilisateur->email]);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_le_reset_est_refuse_si_le_code_na_pas_ete_verifie(): void
    {
        $utilisateur = $this->creerUtilisateur();

        $this->postJson('/api/v1/auth/reinitialiser-mot-de-passe', [
            'reset_token' => str_repeat('a', 64),
            'email' => $utilisateur->email,
            'password' => 'NouveauPassword123',
            'password_confirmation' => 'NouveauPassword123',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('reset_token');
    }

    private function creerUtilisateur(): User
    {
        $role = Role::query()->create([
            'code' => 'ADMIN',
            'libelle' => 'Administrateur',
        ]);

        return User::factory()->create([
            'id_role' => $role->id,
            'password' => 'Password123',
        ]);
    }
}

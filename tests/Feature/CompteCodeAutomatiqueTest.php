<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CompteCodeAutomatiqueTest extends TestCase
{
    use RefreshDatabase;

    public function test_le_code_du_compte_est_genere_automatiquement(): void
    {
        Notification::fake();
        $role = Role::query()->create(['code' => 'ADMIN', 'libelle' => 'Administrateur']);
        Sanctum::actingAs(User::factory()->create(['id_role' => $role->id, 'code' => 'USR-ADMIN']));

        $this->postJson('/api/v1/administration/comptes', [
            'user_id' => 'user-002',
            'nom' => 'KOFFI',
            'prenoms' => 'Jean',
            'email' => 'jean.koffi@example.net',
            'id_role' => $role->id,
        ])->assertCreated()
            ->assertJsonPath('compte.code', 'USR-000001')
            ->assertJsonPath('compte.user_code', 'USR-ADMIN')
            ->assertJsonPath('compte.created_by', 1);
    }

    public function test_un_code_fourni_par_le_client_est_refuse(): void
    {
        $role = Role::query()->create(['code' => 'ADMIN', 'libelle' => 'Administrateur']);
        Sanctum::actingAs(User::factory()->create(['id_role' => $role->id]));

        $this->postJson('/api/v1/administration/comptes', [
            'code' => 'CODE-MANUEL',
            'user_id' => 'user-002',
            'nom' => 'KOFFI',
            'prenoms' => 'Jean',
            'email' => 'jean.koffi@example.net',
            'id_role' => $role->id,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('code');
    }

    public function test_le_template_de_creation_de_compte_utilise_le_design_ebac(): void
    {
        $message = new class
        {
            public function embed(string $path): string
            {
                return 'cid:logo-ebac';
            }
        };

        $html = view('emails.compte-cree', [
            'nomComplet' => 'Severin Zran',
            'email' => 'severin.zran@soumafe.ci',
            'motDePasseTemporaire' => 'Temporaire123',
            'role' => 'Administrateur',
            'urlConnexion' => 'https://ebac.ci',
            'message' => $message,
        ])->render();

        $this->assertStringContainsString('cid:logo-ebac', $html);
        $this->assertStringContainsString('border-radius:50%', $html);
        $this->assertStringContainsString('Temporaire123', $html);
        $this->assertStringContainsString('https://ebac.ci', $html);
        $this->assertStringNotContainsString('127.0.0.1', $html);
        $this->assertStringNotContainsString("If you're having trouble", $html);
    }
}

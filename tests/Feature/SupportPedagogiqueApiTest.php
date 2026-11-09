<?php

namespace Tests\Feature;

use App\Models\AffectationEnseignant;
use App\Models\Cours;
use App\Models\Matiere;
use App\Models\Module;
use App\Models\Niveau;
use App\Models\QuotaStockage;
use App\Models\Role;
use App\Models\UniteEnseignement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SupportPedagogiqueApiTest extends TestCase
{
    use RefreshDatabase;

    private User $enseignant;

    private User $admin;

    private Cours $coursAffecte;

    private Cours $autreCours;

    private UniteEnseignement $domaine;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $roleEnseignant = Role::create(['code' => 'ENSEIGNANT', 'libelle' => 'Enseignant']);
        $this->enseignant = User::factory()->create(['id_role' => $roleEnseignant->id, 'nom' => 'KONAN', 'prenoms' => 'Abraham']);
        $this->admin = User::factory()->create(['id_role' => Role::create(['code' => 'ADMIN', 'libelle' => 'Administrateur'])->id, 'nom' => 'ADMIN', 'prenoms' => 'Système']);

        $this->domaine = UniteEnseignement::create(['code' => 'BIB', 'libelle' => 'Connaissance de la Bible', 'statut' => 'Actif']);
        $niveau = Niveau::create(['code' => 'N1', 'libelle' => '1ère Année', 'rang' => 1]);
        $matiere = Matiere::create(['code' => 'MAT-1', 'libelle' => 'Herméneutique', 'id_niveau' => $niveau->id, 'unite_ensigne_id' => $this->domaine->id]);
        $module = Module::create(['id_matiere' => $matiere->id, 'libelle' => 'Méthodes', 'ordre' => 1]);
        $this->coursAffecte = Cours::create(['id_module' => $module->id, 'code' => 'C-1', 'libelle' => 'Lecture', 'ordre' => 1]);
        $this->autreCours = Cours::create(['id_module' => $module->id, 'code' => 'C-2', 'libelle' => 'Exégèse', 'ordre' => 2]);
        AffectationEnseignant::create(['enseignant_id' => $this->enseignant->id, 'id_matiere' => $matiere->id, 'id_cours' => $this->coursAffecte->id, 'portee' => 'cours', 'date_debut' => now()->subDay()->toDateString()]);
    }

    private function deposer(Cours $cours, string $fichier = 'plan.pdf', int $ko = 200)
    {
        return $this->post('/api/v1/supports', [
            'id_cours' => $cours->id, 'nom' => 'Plan de cours', 'type' => 'cours',
            'fichier' => UploadedFile::fake()->create($fichier, $ko),
        ], ['Accept' => 'application/json']);
    }

    public function test_l_enseignant_depose_sur_son_cours_et_le_document_apparait_a_la_gestion(): void
    {
        Sanctum::actingAs($this->enseignant);
        $this->deposer($this->coursAffecte)->assertCreated()
            ->assertJsonPath('support.nom', 'Plan de cours')
            ->assertJsonPath('quota.supports', 1);
        $this->deposer($this->autreCours)->assertForbidden()->assertJsonPath('code', 'COURS_NON_AFFECTE');

        $this->getJson('/api/v1/enseignant/bibliotheque')->assertOk()
            ->assertJsonPath('matieres.0.modules.0.cours.0.supports.0.nom', 'Plan de cours');

        Sanctum::actingAs($this->admin);
        $this->getJson('/api/v1/supports/bibliotheque')->assertOk()
            ->assertJsonPath('domaines.0.libelle', 'Connaissance de la Bible')
            ->assertJsonPath('domaines.0.matieres.0.code', 'MAT-1')
            ->assertJsonPath('domaines.0.matieres.0.documents.0.ajoute_par.nom', 'KONAN')
            ->assertJsonPath('domaines.0.matieres.0.documents.0.cours', 'Lecture');
    }

    public function test_l_administration_depose_sur_n_importe_quel_cours_hors_quota(): void
    {
        Sanctum::actingAs($this->admin);
        $this->deposer($this->autreCours)->assertCreated()->assertJsonPath('quota', null);

        $this->getJson('/api/v1/supports/bibliotheque')->assertOk()
            ->assertJsonPath('domaines.0.matieres.0.documents.0.ajoute_par.nom', 'ADMIN');
    }

    public function test_la_gestion_sans_droit_est_refusee(): void
    {
        $direction = User::factory()->create(['id_role' => Role::create(['code' => 'DIRECTION', 'libelle' => 'Direction'])->id]);
        Sanctum::actingAs($direction);
        $this->getJson('/api/v1/supports/bibliotheque')->assertForbidden();
        $this->deposer($this->coursAffecte)->assertForbidden()->assertJsonPath('code', 'DROITS_INSUFFISANTS');

        $this->accorderDroits('DIRECTION', 'ressources.bibliotheque.consulter', 'ressources.bibliotheque.creer');
        $direction->unsetRelation('role');
        $this->getJson('/api/v1/supports/bibliotheque')->assertOk();
        $this->deposer($this->coursAffecte)->assertCreated();
    }

    public function test_un_document_se_telecharge_puis_se_supprime_pour_etre_remplace(): void
    {
        Sanctum::actingAs($this->enseignant);
        $depot = $this->deposer($this->coursAffecte)->assertCreated()
            ->assertJsonPath('support.format', 'PDF')
            ->assertJsonMissingPath('support.versions');
        $id = $depot->json('support.id');
        $chemin = \App\Models\SupportPedagogique::find($id)->chemin;

        $this->get("/api/v1/supports/{$id}/fichier")->assertOk()->assertDownload('plan.pdf');
        $this->postJson("/api/v1/supports/{$id}/versions")->assertNotFound();

        $this->deleteJson("/api/v1/supports/{$id}")->assertOk()->assertJsonPath('quota.supports', 0);
        Storage::disk('local')->assertMissing($chemin);
        $this->get("/api/v1/supports/{$id}/fichier")->assertNotFound();
        $this->deleteJson("/api/v1/supports/{$id}")->assertNotFound()->assertJsonPath('code', 'SUPPORT_INTROUVABLE');

        $this->deposer($this->coursAffecte, 'plan-corrige.pdf')->assertCreated();
    }

    public function test_la_suppression_exige_le_cours_affecte_ou_le_droit(): void
    {
        Sanctum::actingAs($this->admin);
        $id = $this->deposer($this->autreCours)->json('support.id');

        Sanctum::actingAs($this->enseignant);
        $this->deleteJson("/api/v1/supports/{$id}")->assertForbidden()->assertJsonPath('code', 'SUPPORT_NON_AFFECTE');

        $direction = User::factory()->create(['id_role' => Role::create(['code' => 'DIRECTION', 'libelle' => 'Direction'])->id]);
        Sanctum::actingAs($direction);
        $this->deleteJson("/api/v1/supports/{$id}")->assertForbidden()->assertJsonPath('code', 'DROITS_INSUFFISANTS');

        $this->accorderDroits('DIRECTION', 'ressources.bibliotheque.supprimer');
        $direction->unsetRelation('role');
        $this->deleteJson("/api/v1/supports/{$id}")->assertOk()->assertJsonPath('quota', null);
    }

    public function test_format_et_quota_refuses(): void
    {
        Sanctum::actingAs($this->enseignant);
        $this->deposer($this->coursAffecte, 'virus.exe')->assertStatus(409)->assertJsonPath('code', 'FORMAT_REFUSE');

        QuotaStockage::create(['enseignant_id' => $this->enseignant->id, 'quota_mo' => 50]);
        $this->deposer($this->coursAffecte, 'gros.pdf', 49 * 1024)->assertCreated();
        $this->deposer($this->coursAffecte, 'encore.pdf', 2 * 1024)->assertStatus(409)->assertJsonPath('code', 'QUOTA_DEPASSE');

        Sanctum::actingAs($this->admin);
        $this->putJson("/api/v1/supports/quotas/{$this->enseignant->id}", ['quota_mo' => 50])->assertOk();
        $this->getJson('/api/v1/supports/quotas')->assertOk()->assertJsonPath('quotas.0.quota_mo', 50);
    }
}

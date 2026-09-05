<?php

namespace Tests\Feature;

use App\Models\DossierEtudiant;
use App\Models\Etudiant;
use App\Models\FichierDossierEtudiant;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RemplacerDocumentEtudiantTest extends TestCase
{
    use RefreshDatabase;

    private function creerDocument(User $compte): FichierDossierEtudiant
    {
        $etudiant = Etudiant::query()->create([
            'user_id' => $compte->id, 'matricule' => 'DOC-'.$compte->id,
            'nom' => 'YAO', 'prenoms' => 'Anne', 'date_inscription' => now()->toDateString(),
        ]);
        $dossier = DossierEtudiant::query()->create([
            'id_etudiant' => $etudiant->id, 'numero_dossier' => 'DOS-'.$compte->id,
            'date_ouverture' => now()->toDateString(),
        ]);
        $chemin = "etudiants/dossiers/{$dossier->id}/ancien.pdf";
        Storage::disk('public')->put($chemin, 'ancien contenu');

        return $dossier->fichiers()->create([
            'type_piece' => 'Diplôme', 'chemin' => $chemin, 'nom_original' => 'ancien.pdf',
            'statut_validation' => 'Validé', 'date_validation' => now(),
            'date_expiration' => now()->addYear(), 'valide_par' => $compte->id, 'motif_rejet' => 'Ancien motif',
        ]);
    }

    private function connecterEtudiant(): User
    {
        Storage::fake('public');
        $role = Role::query()->create(['code' => 'ETUDIANT', 'libelle' => 'Étudiant']);
        $compte = User::factory()->create(['id_role' => $role->id]);
        Sanctum::actingAs($compte);

        return $compte;
    }

    public function test_remplacement_conserve_identifiant_et_type_et_reinitialise_la_validation(): void
    {
        $document = $this->creerDocument($this->connecterEtudiant());
        $ancienChemin = $document->chemin;
        $this->post("/api/v1/etudiant/dossier/documents/{$document->id}", [
            'document' => UploadedFile::fake()->create('nouveau.pdf', 20, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertOk()
            ->assertJsonCount(1, 'dossier.documents')
            ->assertJsonPath('dossier.documents.0.id', $document->id)
            ->assertJsonPath('dossier.documents.0.type_piece', 'Diplôme')
            ->assertJsonPath('dossier.documents.0.nom_original', 'nouveau.pdf')
            ->assertJsonPath('dossier.documents.0.statut_validation', 'En attente');
        $document->refresh();
        $this->assertSame(20480, $document->taille);
        foreach (['date_validation', 'date_expiration', 'valide_par', 'motif_rejet'] as $champ) {
            $this->assertNull($document->$champ);
        }
        Storage::disk('public')->assertExists($document->chemin);
        Storage::disk('public')->assertMissing($ancienChemin);
        $this->assertDatabaseCount('fichiers_dossiers_etudiants', 1);
    }

    public function test_document_autrui_et_identifiant_inconnu_sont_refuses(): void
    {
        $this->creerDocument($this->connecterEtudiant());
        $autre = $this->creerDocument(User::factory()->create());
        $avant = Storage::disk('public')->allFiles();
        foreach ([$autre->id, 999999] as $id) {
            $this->post("/api/v1/etudiant/dossier/documents/{$id}", [
                'document' => UploadedFile::fake()->create('nouveau.pdf', 20, 'application/pdf'),
            ], ['Accept' => 'application/json'])->assertNotFound();
        }
        $this->assertSame($avant, Storage::disk('public')->allFiles());
        $this->assertSame('ancien.pdf', $autre->fresh()->nom_original);
    }

    public function test_fichier_absent_texte_et_format_invalide_ne_modifient_pas_le_document(): void
    {
        $document = $this->creerDocument($this->connecterEtudiant());
        foreach ([[], ['document' => $document->chemin], ['document' => UploadedFile::fake()->create('texte.txt', 1, 'text/plain')]] as $corps) {
            $this->post("/api/v1/etudiant/dossier/documents/{$document->id}", $corps, ['Accept' => 'application/json'])
                ->assertUnprocessable()->assertJsonValidationErrors('document');
        }
        $this->assertSame('ancien.pdf', $document->fresh()->nom_original);
        $this->assertSame([$document->chemin], Storage::disk('public')->allFiles());
    }

    public function test_echec_enregistrement_conserve_ancien_fichier_et_supprime_nouveau(): void
    {
        $document = $this->creerDocument($this->connecterEtudiant());
        Event::listen('eloquent.updating: '.FichierDossierEtudiant::class, function () {
            throw new \RuntimeException('Échec simulé');
        });
        $this->post("/api/v1/etudiant/dossier/documents/{$document->id}", [
            'document' => UploadedFile::fake()->create('nouveau.pdf', 20, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertStatus(500);
        $this->assertSame('ancien.pdf', $document->fresh()->nom_original);
        $this->assertSame([$document->chemin], Storage::disk('public')->allFiles());
    }

    public function test_authentification_et_role_etudiant_sont_requis(): void
    {
        $this->postJson('/api/v1/etudiant/dossier/documents/1')->assertUnauthorized();
        $role = Role::query()->create(['code' => 'ADMIN', 'libelle' => 'Administrateur']);
        Sanctum::actingAs(User::factory()->create(['id_role' => $role->id]));
        $this->postJson('/api/v1/etudiant/dossier/documents/1')->assertForbidden();
    }
}

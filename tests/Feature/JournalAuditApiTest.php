<?php

namespace Tests\Feature;

use App\Models\JournalAudit;
use App\Models\Role;
use App\Models\Salle;
use App\Models\User;
use App\Support\Audit\Journal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use LogicException;
use Tests\TestCase;

class JournalAuditApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $direction;

    private User $enseignant;

    protected function setUp(): void
    {
        parent::setUp();

        $role = fn (string $code) => Role::create(['code' => $code, 'libelle' => $code])->id;
        $this->admin = User::factory()->create(['id_role' => $role('ADMIN'), 'nom' => 'adou', 'prenoms' => 'céline']);
        $this->direction = User::factory()->create(['id_role' => $role('DIRECTION')]);
        $this->enseignant = User::factory()->create(['id_role' => $role('ENSEIGNANT')]);
    }

    private function lire(array $parametres = []): TestResponse
    {
        $requete = http_build_query(['periode' => 'jour', 'ancre' => now('UTC')->toDateString(), ...$parametres]);

        return $this->getJson('/api/v1/administration/journal-audit?'.$requete);
    }

    public function test_une_connexion_reussie_et_un_echec_sont_journalises(): void
    {
        $this->admin->forceFill(['prochaine_connexion_sans_otp' => true])->save();

        $this->postJson('/api/v1/auth/connexion', ['email' => $this->admin->email, 'password' => 'faux'])->assertUnprocessable();
        $this->postJson('/api/v1/auth/connexion', ['email' => 'inconnu@ebac.ci', 'password' => 'faux'])->assertUnprocessable();
        $this->postJson('/api/v1/auth/connexion', ['email' => $this->admin->email, 'password' => 'password'])->assertOk();

        $this->assertSame(3, JournalAudit::query()->where('categorie', 'authentification')->count());
        $this->assertDatabaseHas('journaux_audit', ['action' => 'Connexion réussie', 'id_user' => $this->admin->id, 'acteur_nom' => 'ADOU Céline', 'acteur_profil' => 'administrateur', 'criticite' => 'moyen']);
        $this->assertDatabaseHas('journaux_audit', ['acteur_nom' => 'inconnu@ebac.ci', 'id_user' => null, 'criticite' => 'eleve']);
    }

    public function test_l_administrateur_lit_tout_et_l_entree_suit_le_contrat(): void
    {
        Journal::consigner('authentification', 'Connexion réussie', acteur: $this->direction);
        Journal::consigner('finances', 'Encaissement', objet: ['type' => 'Reçu', 'libelle' => 'R-001'], avant: ['Montant' => '0'], apres: ['Montant' => '50 000'], acteur: $this->admin);

        Sanctum::actingAs($this->admin);
        $this->lire()
            ->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonPath('pages', 1)
            ->assertJsonPath('parPage', 10)
            ->assertJsonCount(9, 'categoriesLisibles')
            ->assertJsonPath('bornes.debut', now('UTC')->toDateString())
            ->assertJsonPath('entrees.0.action', 'Encaissement')
            ->assertJsonPath('entrees.0.acteur.id', (string) $this->admin->id)
            ->assertJsonPath('entrees.0.acteur.profil', 'administrateur')
            ->assertJsonPath('entrees.0.objet.libelle', 'R-001')
            ->assertJsonPath('entrees.0.valeurAvant.Montant', '0')
            ->assertJsonPath('entrees.0.valeurApres.Montant', '50 000')
            ->assertJsonPath('entrees.0.adresseIp', '127.0.0.1')
            ->assertJsonPath('entrees.1.acteur.profil', 'direction');

        $this->assertMatchesRegularExpression('/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\d\.\d{3}Z$/', $this->lire()->json('entrees.0.horodatage'));
    }

    public function test_la_direction_ne_lit_que_ses_categories(): void
    {
        Journal::consigner('authentification', 'Connexion réussie', acteur: $this->admin);
        Journal::consigner('notes', 'Validation des notes', acteur: $this->admin);

        Sanctum::actingAs($this->direction);
        $this->lire()
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('entrees.0.categorie', 'notes')
            ->assertJsonPath('categoriesLisibles', config('audit.categories_restreintes'));

        $this->lire(['categorie' => 'authentification'])->assertForbidden()->assertJsonPath('code', 'CATEGORIE_FERMEE');
    }

    public function test_sans_le_droit_le_registre_est_ferme_et_le_refus_est_journalise(): void
    {
        Sanctum::actingAs($this->enseignant);
        $this->lire()->assertForbidden();
        $this->lire()->assertForbidden();

        $this->assertSame(1, JournalAudit::query()->where('categorie', 'incidents')->where('id_user', $this->enseignant->id)->count());
    }

    public function test_fenetre_criticite_recherche_et_pagination(): void
    {
        Carbon::setTestNow('2026-11-04 10:00:00');
        Journal::consigner('parametrage', 'Modification — salle', 'critique', ['type' => 'Salle', 'libelle' => 'Amphi A'], acteur: $this->admin);
        Carbon::setTestNow('2026-11-09 10:00:00');
        Journal::consigner('comptes', 'Création d’un compte', acteur: $this->admin);
        Journal::consigner('exports', 'Export', acteur: $this->admin);
        Carbon::setTestNow('2026-10-31 23:59:59');
        Journal::consigner('comptes', 'Hors semaine', acteur: $this->admin);
        Carbon::setTestNow('2026-11-09 12:00:00');

        Sanctum::actingAs($this->admin);
        $this->lire(['periode' => 'semaine', 'ancre' => '2026-11-04'])
            ->assertJsonPath('bornes', ['debut' => '2026-11-02', 'fin' => '2026-11-08'])
            ->assertJsonPath('total', 1);
        $this->lire(['periode' => 'mois', 'ancre' => '2026-11-01'])->assertJsonPath('total', 3);
        $this->lire(['periode' => 'mois', 'ancre' => '2026-11-01', 'criticite_minimale' => 'eleve'])->assertJsonPath('total', 2);
        $this->lire(['periode' => 'mois', 'ancre' => '2026-11-01', 'recherche' => 'amphi'])->assertJsonPath('entrees.0.objet.libelle', 'Amphi A');
        $this->lire(['periode' => 'mois', 'ancre' => '2026-11-01', 'par_page' => 2, 'page' => 9])
            ->assertJsonPath('page', 2)->assertJsonPath('pages', 2)->assertJsonCount(1, 'entrees');
        $this->lire(['periode' => 'trimestre'])->assertUnprocessable();
    }

    public function test_une_ecriture_journalisee_porte_ses_valeurs_avant_apres(): void
    {
        Sanctum::actingAs($this->admin);
        $id = $this->postJson('/api/v1/parametres/salles', ['nom' => 'Amphi A', 'code' => 'AMP-A'])->assertCreated()->json('salle.id');
        $this->patchJson("/api/v1/parametres/salles/{$id}", ['statut' => 'Inactif'])->assertOk();
        $this->patchJson("/api/v1/parametres/salles/{$id}", ['code' => ''])->assertUnprocessable();

        $entrees = JournalAudit::query()->where('categorie', 'parametrage')->orderBy('id')->get();
        $this->assertCount(2, $entrees);
        $this->assertSame('Création — salle', $entrees[0]->action);
        $this->assertSame('Modification — salle', $entrees[1]->action);
        $this->assertSame('Salle', $entrees[1]->objet_type);
        $this->assertSame(['Statut' => 'Actif'], $entrees[1]->valeur_avant);
        $this->assertSame(['Statut' => 'Inactif'], $entrees[1]->valeur_apres);
        $this->assertSame(1, Salle::query()->count());
    }

    public function test_l_export_s_inscrit_au_registre(): void
    {
        Sanctum::actingAs($this->admin);
        $this->postJson('/api/v1/administration/journal-audit/exports', [
            'periode' => 'semaine', 'debut' => '2026-11-02', 'fin' => '2026-11-08', 'lignes' => 12, 'categorie' => null,
        ])->assertCreated();

        $entree = JournalAudit::query()->where('categorie', 'exports')->sole();
        $this->assertSame('Toutes catégories', $entree->objet_libelle);
        $this->assertSame('12', $entree->valeur_apres['Lignes']);

        Sanctum::actingAs($this->enseignant);
        $this->postJson('/api/v1/administration/journal-audit/exports', [
            'periode' => 'jour', 'debut' => '2026-11-02', 'fin' => '2026-11-02', 'lignes' => 1,
        ])->assertForbidden();
    }

    public function test_le_registre_est_immuable(): void
    {
        $entree = Journal::consigner('comptes', 'Création d’un compte', acteur: $this->admin);

        Sanctum::actingAs($this->admin);
        $this->putJson("/api/v1/administration/journal-audit/{$entree->id}")->assertNotFound();
        $this->deleteJson("/api/v1/administration/journal-audit/{$entree->id}")->assertNotFound();
        $this->deleteJson('/api/v1/administration/journal-audit')->assertStatus(405);

        try {
            $entree->update(['action' => 'Retouche']);
            $this->fail('Une entrée a pu être modifiée.');
        } catch (LogicException) {
        }
        try {
            $entree->delete();
            $this->fail('Une entrée a pu être supprimée.');
        } catch (LogicException) {
        }
        $this->assertSame('Création d’un compte', $entree->fresh()->action);
    }
}

<?php

namespace Tests\Feature;

use App\Models\AnneeAcademique;
use App\Models\Bulletin;
use App\Models\EpreuveFinDeCycle;
use App\Models\Etudiant;
use App\Models\Inscription;
use App\Models\Niveau;
use App\Models\Permission;
use App\Models\Promotion;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\CatalogueDroitsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Circuit du bulletin de fin de cycle (validation, renvoi en saisie,
 * publication par promotion avec omissions) et registre des bulletins par
 * niveau — 09/11/2026.
 */
class BulletinsFinDeCycleApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Promotion $promotion;

    private AnneeAcademique $annee;

    /** @var array<string, Etudiant> */
    private array $etudiants = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'id_role' => Role::create(['code' => 'ADMIN', 'libelle' => 'Administrateur'])->id,
            'nom' => 'ADJOUA', 'prenoms' => 'Marie',
        ]);
        $niveau = Niveau::create(['code' => 'N3', 'libelle' => '3e Année', 'rang' => 3]);
        $this->annee = AnneeAcademique::create(['libelle' => '2026-2027', 'date_debut' => '2026-09-01', 'date_fin' => '2027-07-31']);
        $this->promotion = Promotion::create(['code' => 'PROMO-13', 'num_promotion' => 13, 'annee_entree' => 2023, 'id_niveau' => $niveau->id]);
        foreach (['KOUADIO' => '411-2021', 'BAMBA' => '412-2021', 'YAO' => '413-2021'] as $nom => $matricule) {
            $etudiant = Etudiant::create(['nom' => $nom, 'prenoms' => 'Test', 'matricule' => $matricule, 'date_inscription' => '2026-09-01']);
            Inscription::create(['id_etudiant' => $etudiant->id, 'id_promotion' => $this->promotion->id,
                'id_annee_academique' => $this->annee->id, 'date_inscription' => '2026-09-01']);
            $this->etudiants[$nom] = $etudiant;
        }
    }

    private function url(string $suite): string
    {
        return "/api/v1/fin-de-cycle/promotions/{$this->promotion->id}/{$suite}";
    }

    /** Porte les cinq notes du bulletin (72,31 → 14,46) pour les étudiants donnés. */
    private function noterTout(string ...$noms): void
    {
        $notes = ['rapport-stage' => 15, 'predication' => 13.17, 'dissertation' => 14, 'connaissance-biblique' => 15.14, 'moyenne-annuelle' => 15];
        foreach ($notes as $code => $note) {
            $epreuve = EpreuveFinDeCycle::query()->where('code', $code)->firstOrFail();
            $this->putJson($this->url("epreuves/{$epreuve->id}/notes"), [
                'notes' => array_map(fn ($nom) => ['id_etudiant' => $this->etudiants[$nom]->id, 'note' => $note], $noms),
            ])->assertOk();
        }
    }

    private function ids(string ...$noms): array
    {
        return array_map(fn ($nom) => $this->etudiants[$nom]->id, $noms);
    }

    private function ligne(array $feuille, string $nom): array
    {
        return collect($feuille['etudiants'])->firstWhere('id', $this->etudiants[$nom]->id);
    }

    public function test_la_validation_fige_les_notes_et_refuse_un_etudiant_incomplet(): void
    {
        Sanctum::actingAs($this->admin);
        $this->noterTout('KOUADIO', 'BAMBA');

        $this->postJson($this->url('validation'), ['etudiants' => $this->ids('KOUADIO', 'YAO')])
            ->assertStatus(409)->assertJsonPath('code', 'NOTES_INCOMPLETES')
            ->assertJsonPath('etudiants', $this->ids('YAO'));
        $this->assertDatabaseCount('bulletins', 0);

        $feuille = $this->postJson($this->url('validation'), ['etudiants' => $this->ids('KOUADIO')])->assertOk()->json();
        $this->assertSame('valide', $this->ligne($feuille, 'KOUADIO')['bulletin']['statut']);
        $this->assertSame(14.46, $this->ligne($feuille, 'KOUADIO')['bulletin']['moyenne']);
        $this->assertSame('Bien', $this->ligne($feuille, 'KOUADIO')['bulletin']['mention']);
        $this->assertSame('ADJOUA', $this->ligne($feuille, 'KOUADIO')['bulletin']['valide_par']['nom']);
        $this->assertNull($this->ligne($feuille, 'BAMBA')['bulletin']);

        $bulletin = Bulletin::query()->sole();
        $this->assertSame(Bulletin::PERIODE_FIN_DE_CYCLE, $bulletin->periode);
        $this->assertSame(Bulletin::STATUT_VALIDE, $bulletin->statut);
        $this->assertSame($this->promotion->id_niveau, $bulletin->id_niveau);
        $this->assertDatabaseCount('lignes_bulletins_fin_de_cycle', 5);
        $this->assertDatabaseHas('historique_bulletins_fin_de_cycle', ['id_etudiant' => $this->etudiants['KOUADIO']->id, 'action' => 'validation']);

        // Les notes validées ne se ressaisissent plus ; une ligne vide, si.
        $predication = EpreuveFinDeCycle::query()->where('code', 'predication')->firstOrFail();
        $this->putJson($this->url("epreuves/{$predication->id}/notes"), [
            'notes' => [['id_etudiant' => $this->etudiants['KOUADIO']->id, 'note' => 10]],
        ])->assertStatus(409)->assertJsonPath('code', 'NOTES_VALIDEES');
        $this->putJson($this->url("epreuves/{$predication->id}/notes"), [
            'notes' => [['id_etudiant' => $this->etudiants['KOUADIO']->id, 'note' => null], ['id_etudiant' => $this->etudiants['BAMBA']->id, 'note' => 11]],
        ])->assertOk();

        // Revalider un étudiant déjà validé ne double rien.
        $this->postJson($this->url('validation'), ['etudiants' => $this->ids('KOUADIO')])->assertOk();
        $this->assertDatabaseCount('bulletins', 1);
    }

    public function test_le_renvoi_rouvre_la_saisie_tant_que_rien_nest_publie(): void
    {
        Sanctum::actingAs($this->admin);
        $this->noterTout('KOUADIO', 'BAMBA');
        $this->postJson($this->url('validation'), ['etudiants' => $this->ids('KOUADIO', 'BAMBA')])->assertOk();
        $this->postJson($this->url('publication'), ['etudiants' => $this->ids('BAMBA')])->assertOk();

        $this->postJson($this->url('renvoi'), ['etudiants' => $this->ids('KOUADIO', 'BAMBA')])
            ->assertStatus(409)->assertJsonPath('code', 'BULLETIN_PUBLIE')
            ->assertJsonPath('etudiants', $this->ids('BAMBA'));

        $feuille = $this->postJson($this->url('renvoi'), ['etudiants' => $this->ids('KOUADIO', 'YAO')])->assertOk()->json();
        $this->assertNull($this->ligne($feuille, 'KOUADIO')['bulletin']);
        $this->assertSame('publie', $this->ligne($feuille, 'BAMBA')['bulletin']['statut']);
        $this->assertDatabaseCount('lignes_bulletins_fin_de_cycle', 5);
        $this->assertDatabaseHas('historique_bulletins_fin_de_cycle', ['id_etudiant' => $this->etudiants['KOUADIO']->id, 'action' => 'renvoi']);

        $predication = EpreuveFinDeCycle::query()->where('code', 'predication')->firstOrFail();
        $this->putJson($this->url("epreuves/{$predication->id}/notes"), [
            'notes' => [['id_etudiant' => $this->etudiants['KOUADIO']->id, 'note' => 10]],
        ])->assertOk();
    }

    public function test_la_publication_groupee_omet_qui_lon_veut_et_paraît_dans_le_portail(): void
    {
        Sanctum::actingAs($this->admin);
        $this->noterTout('KOUADIO', 'BAMBA', 'YAO');
        $this->postJson($this->url('validation'), ['etudiants' => $this->ids('KOUADIO', 'BAMBA')])->assertOk();

        $this->postJson($this->url('publication'), ['etudiants' => $this->ids('KOUADIO', 'YAO')])
            ->assertStatus(409)->assertJsonPath('code', 'BULLETIN_NON_VALIDE')
            ->assertJsonPath('etudiants', $this->ids('YAO'));

        // BAMBA, validé, est omis de la publication.
        $feuille = $this->postJson($this->url('publication'), ['etudiants' => $this->ids('KOUADIO')])->assertOk()->json();
        $this->assertSame('publie', $this->ligne($feuille, 'KOUADIO')['bulletin']['statut']);
        $this->assertNotNull($this->ligne($feuille, 'KOUADIO')['bulletin']['publie_le']);
        $this->assertSame('valide', $this->ligne($feuille, 'BAMBA')['bulletin']['statut']);

        $role = Role::create(['code' => 'ETUDIANT', 'libelle' => 'Étudiant']);
        foreach (['KOUADIO' => 1, 'BAMBA' => 0] as $nom => $attendus) {
            $compte = User::factory()->create(['id_role' => $role->id]);
            $this->etudiants[$nom]->update(['user_id' => $compte->id]);
            Sanctum::actingAs($compte);
            $liste = $this->getJson('/api/v1/etudiant/bulletins')->assertOk()->assertJsonPath('meta.total', $attendus);
            if ($attendus === 1) {
                $id = $liste->json('bulletins.0.id');
                $this->getJson("/api/v1/etudiant/bulletins/{$id}")->assertOk()
                    ->assertJsonPath('bulletin.periode', 'Fin de cycle')
                    ->assertJsonPath('bulletin.niveau.libelle', '3e Année')
                    ->assertJsonCount(5, 'bulletin.fin_de_cycle.elements')
                    ->assertJsonPath('bulletin.fin_de_cycle.elements.0.libelle', 'Rapport de stage')
                    ->assertJsonPath('bulletin.fin_de_cycle.total', 72.31)
                    ->assertJsonPath('bulletin.fin_de_cycle.moyenne_generale', 14.46)
                    ->assertJsonPath('bulletin.fin_de_cycle.mention', 'Bien')
                    ->assertJsonCount(0, 'bulletin.matieres');
            }
        }
    }

    public function test_le_registre_range_les_bulletins_par_niveau_du_bulletin(): void
    {
        Sanctum::actingAs($this->admin);
        $premiere = Niveau::create(['code' => 'N1', 'libelle' => '1re Année', 'rang' => 1]);
        $inscription = Inscription::query()->where('id_etudiant', $this->etudiants['YAO']->id)->firstOrFail();
        // Un bulletin de 1re Année de la promotion, avancée depuis en 3e Année.
        $annuel = Bulletin::create(['id_inscription' => $inscription->id, 'id_niveau' => $premiere->id, 'periode' => 'Annuel', 'statut' => 'Généré', 'moyenne' => 12.5]);
        $this->noterTout('KOUADIO');
        $this->postJson($this->url('validation'), ['etudiants' => $this->ids('KOUADIO')])->assertOk();

        $this->getJson('/api/v1/bulletins/filtres')->assertOk()
            ->assertJsonPath('niveaux.0.libelle', '1re Année')->assertJsonPath('niveaux.0.bulletins', 1)
            ->assertJsonPath('niveaux.1.libelle', '3e Année')->assertJsonPath('niveaux.1.bulletins', 1)
            ->assertJsonPath('annees_academiques.0.libelle', '2026-2027');

        $this->getJson("/api/v1/bulletins?id_niveau={$premiere->id}")->assertOk()
            ->assertJsonCount(1, 'bulletins')->assertJsonPath('bulletins.0.id', $annuel->id)
            ->assertJsonPath('bulletins.0.fin_de_cycle', false)
            ->assertJsonPath('bulletins.0.etudiant.matricule', '413-2021');
        $this->getJson("/api/v1/bulletins?id_niveau={$this->promotion->id_niveau}&id_annee_academique={$this->annee->id}")->assertOk()
            ->assertJsonCount(1, 'bulletins')->assertJsonPath('bulletins.0.fin_de_cycle', true)
            ->assertJsonPath('bulletins.0.statut', 'Validé')
            ->assertJsonPath('bulletins.0.valide_par.nom', 'ADJOUA');

        $id = Bulletin::query()->where('periode', Bulletin::PERIODE_FIN_DE_CYCLE)->value('id');
        $this->getJson("/api/v1/bulletins/{$id}")->assertOk()
            ->assertJsonPath('etudiant.matricule', '411-2021')
            ->assertJsonPath('bulletin.fin_de_cycle.moyenne_generale', 14.46);
        $this->getJson('/api/v1/bulletins/999999')->assertNotFound()->assertJsonPath('code', 'BULLETIN_INTROUVABLE');
    }

    public function test_chaque_geste_demande_son_droit(): void
    {
        $role = Role::create(['code' => 'SCOLARITE', 'libelle' => 'Scolarité']);
        $accorder = fn (string ...$codes) => $role->permissions()->syncWithoutDetaching(
            Permission::query()->whereIn('code', $codes)->pluck('id')->mapWithKeys(fn ($id) => [$id => ['actif' => true]])->all()
        );
        $accorder('notes.bulletins.consulter', 'notes.bulletins.valider');
        Sanctum::actingAs(User::factory()->create(['id_role' => $role->id]));

        $this->getJson($this->url('notes'))->assertOk();
        $this->getJson('/api/v1/bulletins/filtres')->assertOk();
        $this->postJson($this->url('validation'), ['etudiants' => $this->ids('KOUADIO')])->assertStatus(409);
        $this->postJson($this->url('renvoi'), ['etudiants' => $this->ids('KOUADIO')])->assertForbidden();
        $this->postJson($this->url('publication'), ['etudiants' => $this->ids('KOUADIO')])->assertForbidden();

        $this->assertNotNull(Permission::query()->where('code', 'notes.bulletins.publier')->first());
        // ADMIN seul détient d'office les droits des bulletins : ils s'accordent dans « Rôles & droits ».
        foreach (['DIRECTION', 'SECRETARIAT_ACADEMIQUE', 'GESTIONNAIRE'] as $code) {
            $this->assertEmpty(array_filter(CatalogueDroitsSeeder::droitsParDefaut($code), fn ($c) => str_starts_with($c, 'notes.bulletins.')));
        }
    }

    public function test_un_etudiant_hors_promotion_est_refuse(): void
    {
        Sanctum::actingAs($this->admin);
        $etranger = Etudiant::create(['nom' => 'AUTRE', 'prenoms' => 'Test', 'matricule' => '999', 'date_inscription' => '2026-09-01']);

        $this->postJson($this->url('validation'), ['etudiants' => [$etranger->id]])
            ->assertStatus(422)->assertJsonPath('code', 'ETUDIANT_HORS_PROMOTION');
        $this->postJson($this->url('publication'), ['etudiants' => []])->assertStatus(422);
    }
}

<?php

namespace Tests\Feature;

use App\Models\NouvelleAdmission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class NouvelleAdmissionApiTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/v1/administration/nouvelles-admissions';

    private function connecter(string $code): User
    {
        $role = Role::query()->firstOrCreate(['code' => $code], ['libelle' => $code]);
        $user = User::factory()->create(['id_role' => $role->id]);
        Sanctum::actingAs($user);

        return $user;
    }

    private function liste(string $lignes = "YAO Anne;Abidjan;Saint Paul;Mariée\nKOFFI Jean;;;\n"): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('admis.csv', "\xEF\xBB\xBFNom et Prénoms;Région;Paroisse;Situation matrimoniale\n".$lignes);
    }

    public static function rolesAutorises(): array
    {
        return [['ADMIN'], ['SECRETARIAT'], ['SECRETAIRE_ACADEMIQUE']];
    }

    #[DataProvider('rolesAutorises')]
    public function test_import_liste_coordonnees_statistiques_et_pdf(string $role): void
    {
        Storage::fake('local');
        $this->connecter($role);
        $reponse = $this->postJson(self::URL.'/importer', [
            'annee_entree' => 2026, 'liste' => $this->liste(),
            'arrete' => UploadedFile::fake()->createWithContent('arrete.pdf', "%PDF-1.4\n%%EOF"),
        ])->assertCreated()->assertJsonPath('importes', 2)->assertJsonPath('doublons_ignores', 0);
        $idImport = $reponse->json('import_id');
        $this->assertDatabaseCount('etudiants', 0);
        $liste = $this->getJson(self::URL.'?annee_entree=2026')->assertOk()
            ->assertJsonPath('rentree', '2026-2027')->assertJsonPath('statistiques.total', 2)
            ->assertJsonPath('statistiques.sans_coordonnees', 2)->assertJsonPath('admis.data.0.nom_prenoms', 'YAO Anne');
        $id = $liste->json('admis.data.0.id');
        $this->patchJson(self::URL.'/'.$id, ['telephone' => '0102030405', 'dossier_depose' => true])
            ->assertOk()->assertJsonPath('admis.telephone', '0102030405');
        $this->getJson(self::URL.'?annee_entree=2026&recherche=KOFFI&par_page=1')->assertOk()
            ->assertJsonPath('admis.total', 1)->assertJsonPath('statistiques.total', 2)
            ->assertJsonPath('statistiques.dossier_depose', 1)->assertJsonPath('statistiques.sans_coordonnees', 1);
        $this->getJson(self::URL.'?annee_entree=2027')->assertOk()->assertJsonPath('statistiques.total', 0);
        $this->get(self::URL.'/imports/'.$idImport.'/arrete')->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->get(self::URL.'/pdf?annee_entree=2026')->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->postJson(self::URL.'/importer', ['annee_entree' => 2026, 'liste' => $this->liste()])
            ->assertCreated()->assertJsonPath('importes', 0)->assertJsonPath('doublons_ignores', 2);
        $this->assertSame('0102030405', NouvelleAdmission::findOrFail($id)->telephone);
        $this->postJson(self::URL.'/importer', ['annee_entree' => 2027, 'liste' => $this->liste()])
            ->assertCreated()->assertJsonPath('importes', 2);
    }

    public static function formatsExcel(): array
    {
        return [['xlsx'], ['xls']];
    }

    #[DataProvider('formatsExcel')]
    public function test_import_excel(string $extension): void
    {
        $this->connecter('ADMIN');
        $classeur = new Spreadsheet;
        $classeur->getActiveSheet()->fromArray([
            ['Nom et Prénoms', 'Région', 'Paroisse', 'Situation matrimoniale'],
            ['YAO Anne', 'Abidjan', 'Saint Paul', 'Mariée'],
        ]);
        $chemin = tempnam(sys_get_temp_dir(), 'admis');
        try {
            $writer = $extension === 'xlsx' ? new Xlsx($classeur) : new Xls($classeur);
            $writer->save($chemin);
            $this->postJson(self::URL.'/importer', [
                'annee_entree' => 2026, 'liste' => new UploadedFile($chemin, 'admis.'.$extension, null, null, true),
            ])->assertCreated()->assertJsonPath('importes', 1);
        } finally {
            $classeur->disconnectWorksheets();
            @unlink($chemin);
        }
    }

    public function test_import_invalide_ne_conserve_aucune_ligne_ni_piece(): void
    {
        Storage::fake('local');
        $this->connecter('ADMIN');
        foreach ([
            $this->liste("YAO Anne;;;\n;Abidjan;;\n"),
            UploadedFile::fake()->createWithContent('admis.csv', "Nom\nYAO\n"),
            $this->liste(''),
            $this->liste("=1+1;;;\n"),
            UploadedFile::fake()->createWithContent('admis.xlsx', 'faux excel'),
        ] as $fichier) {
            $reponse = $this->postJson(self::URL.'/importer', ['annee_entree' => 2026, 'liste' => $fichier]);
            $this->assertSame(422, $reponse->status(), $fichier->getContent());
            $reponse->assertJsonValidationErrors('liste');
        }
        $this->postJson(self::URL.'/importer', [
            'annee_entree' => 2026, 'liste' => $this->liste(),
            'arrete' => UploadedFile::fake()->createWithContent('arrete.pdf', 'pas un PDF')->mimeType('text/plain'),
        ])->assertUnprocessable()->assertJsonValidationErrors('arrete');
        $this->assertDatabaseCount('nouvelles_admissions', 0);
        $this->assertDatabaseCount('imports_admissions', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_doublons_dans_un_fichier_et_csv_avec_virgules(): void
    {
        $this->connecter('ADMIN');
        $csv = "Nom et Prénoms,Région,Paroisse,Situation matrimoniale\nYAO Anne,Abidjan,Saint Paul,\nyao  anne,abidjan,saint paul,\n";
        $this->postJson(self::URL.'/importer', [
            'annee_entree' => 2026, 'liste' => UploadedFile::fake()->createWithContent('admis.csv', $csv),
        ])->assertCreated()->assertJsonPath('importes', 1)->assertJsonPath('doublons_ignores', 1);
    }

    public function test_acces_protege_sur_toutes_les_routes(): void
    {
        $routes = [['GET', ''], ['POST', '/importer'], ['PATCH', '/1'], ['GET', '/pdf'], ['GET', '/imports/1/arrete']];
        foreach ($routes as [$methode, $suffixe]) {
            $this->json($methode, self::URL.$suffixe)->assertUnauthorized();
        }
        foreach (['ETUDIANT', 'ENSEIGNANT', 'AUTRE'] as $role) {
            $this->connecter($role);
            foreach ($routes as [$methode, $suffixe]) {
                $this->json($methode, self::URL.$suffixe)->assertForbidden();
            }
        }
        $user = $this->connecter('ADMIN');
        $user->update(['is_active' => false]);
        $this->getJson(self::URL)->assertForbidden();
    }

    public function test_validation_coordonnees_et_arrete_absent(): void
    {
        $this->connecter('ADMIN');
        $this->postJson(self::URL.'/importer', ['annee_entree' => 2026, 'liste' => $this->liste()])->assertCreated();
        $id = NouvelleAdmission::query()->firstOrFail()->id;
        $this->patchJson(self::URL.'/'.$id, ['email' => 'invalide'])->assertUnprocessable();
        $this->patchJson(self::URL.'/99999', ['adresse' => 'Abidjan'])->assertNotFound();
        $this->getJson(self::URL.'/imports/'.DB::table('imports_admissions')->value('id').'/arrete')->assertNotFound();
        $this->getJson(self::URL.'?annee_entree=abc')->assertUnprocessable();
    }
}

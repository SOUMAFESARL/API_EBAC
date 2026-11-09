<?php

namespace Tests\Feature;

use App\Models\Cours;
use App\Models\Matiere;
use App\Models\Module;
use App\Models\Niveau;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReparationBibliothequeTest extends TestCase
{
    use RefreshDatabase;

    private function migration(): object
    {
        return require database_path('migrations/2026_11_09_120000_reparer_bibliotheque_sans_versions.php');
    }

    public function test_l_ancien_schema_versionne_garde_la_version_la_plus_recente(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('supports/1/v1.pdf', 'a');
        Storage::disk('local')->put('supports/1/v2.pdf', 'b');

        $niveau = Niveau::create(['code' => 'N1', 'libelle' => '1ère Année', 'rang' => 1]);
        $matiere = Matiere::create(['code' => 'M', 'libelle' => 'M', 'id_niveau' => $niveau->id]);
        $module = Module::create(['id_matiere' => $matiere->id, 'libelle' => 'Mod', 'ordre' => 1]);
        $cours = Cours::create(['id_module' => $module->id, 'code' => 'C', 'libelle' => 'C', 'ordre' => 1]);

        // Ancien schéma de 2026_11_09_090000.
        Schema::drop('supports_pedagogiques');
        Schema::create('supports_pedagogiques', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_cours');
            $table->string('nom', 180);
            $table->string('type', 30);
            $table->foreignId('depose_par')->nullable();
            $table->timestamps();
        });
        Schema::create('versions_supports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_support');
            $table->unsignedSmallInteger('numero');
            $table->string('chemin');
            $table->string('nom_fichier');
            $table->string('format', 10);
            $table->unsignedBigInteger('taille_octets');
            $table->string('note', 300)->nullable();
            $table->foreignId('depose_par')->nullable();
            $table->timestamps();
        });
        DB::table('supports_pedagogiques')->insert(['id' => 1, 'id_cours' => $cours->id, 'nom' => 'Plan', 'type' => 'cours']);
        DB::table('supports_pedagogiques')->insert(['id' => 2, 'id_cours' => $cours->id, 'nom' => 'Vide', 'type' => 'cours']);
        DB::table('versions_supports')->insert([
            ['id_support' => 1, 'numero' => 1, 'chemin' => 'supports/1/v1.pdf', 'nom_fichier' => 'v1.pdf', 'format' => 'PDF', 'taille_octets' => 10],
            ['id_support' => 1, 'numero' => 2, 'chemin' => 'supports/1/v2.pdf', 'nom_fichier' => 'v2.pdf', 'format' => 'PDF', 'taille_octets' => 20],
        ]);

        $this->migration()->up();

        $this->assertFalse(Schema::hasTable('versions_supports'));
        $support = DB::table('supports_pedagogiques')->where('id', 1)->first();
        $this->assertSame('supports/1/v2.pdf', $support->chemin);
        $this->assertSame(20, (int) $support->taille_octets);
        $this->assertNull(DB::table('supports_pedagogiques')->where('id', 2)->first());
        Storage::disk('local')->assertMissing('supports/1/v1.pdf');
        Storage::disk('local')->assertExists('supports/1/v2.pdf');
    }

    public function test_les_droits_bibliotheque_sont_semes_et_donnes_a_l_admin(): void
    {
        $admin = Role::create(['code' => 'ADMIN', 'libelle' => 'Administrateur']);
        Permission::query()->where('code', 'like', 'ressources.bibliotheque.%')->forceDelete();

        $this->migration()->up();
        $this->migration()->up();

        $codes = ['ressources.bibliotheque.consulter', 'ressources.bibliotheque.creer', 'ressources.bibliotheque.supprimer'];
        $this->assertSame(3, Permission::query()->whereIn('code', $codes)->count());
        $this->assertSame(3, $admin->permissions()->whereIn('code', $codes)->count());
        $this->assertTrue(Schema::hasColumn('supports_pedagogiques', 'chemin'));
    }
}

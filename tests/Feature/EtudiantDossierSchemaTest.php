<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EtudiantDossierSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_etudiant_possede_un_statut_professionnel_et_une_photo_identite(): void
    {
        $this->assertTrue(Schema::hasColumns('etudiants', [
            'civilite_id',
            'statut_professionnel',
            'situation_matrimonial',
            'nombre_enfant',
            'photo_identite',
        ]));

        $this->assertTrue(Schema::hasColumn('etudiants', 'sexe'));
    }

    public function test_un_dossier_peut_posseder_plusieurs_fichiers(): void
    {
        $this->assertTrue(Schema::hasColumns('fichiers_dossiers_etudiants', [
            'id',
            'id_dossier_etudiant',
            'type_piece',
            'nom_original',
            'chemin',
            'mime_type',
            'taille',
            'created_at',
            'updated_at',
        ]));

        $dossierId = \DB::table('dossiers_etudiants')->insertGetId([
            'id_etudiant' => \DB::table('etudiants')->insertGetId([
                'matricule' => 'ETU-TEST-001',
                'nom' => 'Test',
                'prenoms' => 'Etudiant',
                'date_inscription' => '2026-08-18',
            ]),
            'numero_dossier' => 'DOS-TEST-001',
            'date_ouverture' => '2026-08-18',
        ]);

        \DB::table('fichiers_dossiers_etudiants')->insert([
            ['id_dossier_etudiant' => $dossierId, 'nom_original' => 'identite.pdf', 'chemin' => 'dossiers/identite.pdf'],
            ['id_dossier_etudiant' => $dossierId, 'nom_original' => 'diplome.pdf', 'chemin' => 'dossiers/diplome.pdf'],
        ]);

        $this->assertSame(2, \DB::table('fichiers_dossiers_etudiants')->where('id_dossier_etudiant', $dossierId)->count());
    }
}

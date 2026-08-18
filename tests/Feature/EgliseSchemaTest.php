<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EgliseSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_table_eglises_contient_les_champs_du_formulaire_et_le_tableau_des_representants(): void
    {
        $this->assertTrue(Schema::hasColumns('eglises', [
            'id',
            'code',
            'nom',
            'sigle',
            'pasteur_principal',
            'denomination',
            'adresse',
            'region',
            'district',
            'ville_commune',
            'telephone',
            'email',
            'statut',
            'capacite_max_stagiaires',
            'representants',
            'observations',
            'user_id',
            'user_code',
            'created_by',
            'updated_by',
            'deleted_by',
            'created_at',
            'updated_at',
            'deleted_at',
        ]));

        $this->assertFalse(Schema::hasColumn('eglises', 'id_compte'));
        $this->assertFalse(Schema::hasTable('eglise_representants'));
    }
}

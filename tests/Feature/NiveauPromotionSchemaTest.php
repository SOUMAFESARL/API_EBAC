<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class NiveauPromotionSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_table_niveaux_contient_les_champs_du_formulaire(): void
    {
        $this->assertTrue(Schema::hasColumns('niveaux', [
            'id',
            'libelle',
            'code',
            'rang',
            'statut',
            'user_id',
            'user_code',
            'created_by',
            'updated_by',
            'deleted_by',
            'created_at',
            'updated_at',
            'deleted_at',
        ]));
    }

    public function test_la_table_promotions_contient_les_champs_du_formulaire(): void
    {
        $this->assertTrue(Schema::hasColumns('promotions', [
            'id',
            'code',
            'id_annee_academique',
            'id_niveau',
            'capacite',
            'statut',
            'date_ouverture',
            'date_cloture',
            'user_id',
            'created_by',
            'updated_by',
            'deleted_by',
            'created_at',
            'updated_at',
            'deleted_at',
        ]));
    }
}

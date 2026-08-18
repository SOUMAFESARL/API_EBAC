<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UniteValeurSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_table_unites_valeur_contient_les_champs_des_ecrans(): void
    {
        $this->assertTrue(Schema::hasColumns('unites_valeur', [
            'id', 'code', 'libelle', 'id_niveau', 'coefficient', 'type',
            'obligatoire', 'volume_horaire', 'statut', 'description',
            'objectifs_pedagogiques', 'condition_validation',
            'note_minimale_validation', 'modalite_evaluation', 'id_prerequis',
            'acquis_irrevocable', 'created_at', 'updated_at',
        ]));
    }

    public function test_les_modules_et_les_cours_sont_stockes_dans_des_tables_enfants(): void
    {
        $this->assertTrue(Schema::hasColumns('modules', [
            'id', 'id_unite_valeur', 'libelle', 'ordre', 'created_at', 'updated_at',
        ]));

        $this->assertTrue(Schema::hasColumns('cours', [
            'id', 'id_module', 'libelle', 'volume_horaire', 'coefficient',
            'ordre', 'created_at', 'updated_at',
        ]));
    }
}

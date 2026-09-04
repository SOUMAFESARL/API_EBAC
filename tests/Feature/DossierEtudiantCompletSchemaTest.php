<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DossierEtudiantCompletSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_les_tables_du_dossier_etudiant_complet_existent(): void
    {
        $this->assertTrue(Schema::hasColumns('paiements_etudiants', [
            'id_etudiant', 'id_inscription', 'id_annee_academique', 'type_paiement',
            'montant', 'date_paiement', 'mode_paiement', 'reference', 'statut', 'recu_chemin',
        ]));
        $this->assertTrue(Schema::hasColumns('parcours_academiques', [
            'id_etudiant', 'id_annee_academique', 'id_niveau', 'id_promotion',
            'annee_academique_externe', 'niveau_externe', 'promotion_externe',
            'etablissement', 'type_parcours', 'statut', 'moyenne_generale', 'decision',
        ]));
        $this->assertTrue(Schema::hasColumns('bulletins', [
            'id_inscription', 'periode', 'moyenne', 'mention', 'rang', 'decision',
            'fichier_chemin', 'date_publication', 'statut',
        ]));
        $this->assertTrue(Schema::hasColumns('lignes_bulletins', [
            'id_bulletin', 'id_matiere', 'note', 'coefficient',
            'moyenne_ponderee', 'appreciation',
        ]));
    }

    public function test_les_inscriptions_et_les_pieces_possedent_leur_suivi(): void
    {
        $this->assertTrue(Schema::hasColumn('inscriptions', 'id_annee_academique'));
        $this->assertTrue(Schema::hasColumns('fichiers_dossiers_etudiants', [
            'statut_validation', 'date_validation', 'date_expiration',
            'motif_rejet', 'valide_par',
        ]));
    }
}

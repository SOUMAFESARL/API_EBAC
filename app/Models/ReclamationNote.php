<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReclamationNote extends Model
{
    protected $table = 'reclamations_notes';

    protected $fillable = ['id_etudiant', 'id_ligne_bulletin', 'note_contestee', 'motif', 'statut', 'reponse', 'traitee_par', 'date_traitement'];

    protected function casts(): array
    {
        return ['note_contestee' => 'float', 'date_traitement' => 'datetime'];
    }
}

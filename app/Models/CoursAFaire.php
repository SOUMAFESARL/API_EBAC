<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CoursAFaire extends Model
{
    protected $table = 'cours_a_faire';

    protected $fillable = ['id_etudiant', 'id_cours', 'id_matiere', 'id_seance', 'statut', 'motif'];
}

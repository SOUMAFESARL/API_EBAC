<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConfigurationEcole extends Model
{
    public $incrementing = false;

    protected $fillable = ['id', 'nom_ecole', 'code_ecole', 'slogan', 'adresse', 'fondateur',
        'directeur_actuel', 'situation_geographique', 'ville', 'region', 'email', 'telephone',
        'cellulaire', 'biographie', 'logo', 'favicon', 'signature', 'signature_directeur', 'created_by', 'updated_by'];
}

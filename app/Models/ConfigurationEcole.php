<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConfigurationEcole extends Model
{
    public $incrementing = false;

    protected $fillable = ['id', 'nom_ecole', 'code_ecole', 'slogan', 'adresse', 'fondateur',
        'directeur_actuel', 'situation_geographique', 'ville', 'region', 'email', 'telephone',
        'cellulaire', 'biographie', 'logo', 'favicon', 'signature', 'signature_directeur', 'created_by', 'updated_by',
        'sigle', 'nom_plateforme', 'arrete', 'boite_postale', 'site_web',
        'directeur_civilite_id', 'directeur_nom', 'directeur_prenoms',
        'directeur_academique_civilite_id', 'directeur_academique_nom', 'directeur_academique_prenoms',
        'signature_directeur_academique'];

    protected function casts(): array
    {
        return ['directeur_civilite_id' => 'integer', 'directeur_academique_civilite_id' => 'integer'];
    }
}

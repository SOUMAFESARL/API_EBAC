<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NouvelleAdmission extends Model
{
    protected $table = 'nouvelles_admissions';

    protected $fillable = ['import_admission_id', 'annee_entree', 'nom_prenoms', 'region', 'paroisse', 'situation_matrimoniale', 'telephone', 'adresse', 'email', 'dossier_depose', 'empreinte', 'updated_by'];

    protected $hidden = ['empreinte'];

    protected function casts(): array
    {
        return ['annee_entree' => 'integer', 'dossier_depose' => 'boolean'];
    }
}

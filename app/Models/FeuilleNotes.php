<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FeuilleNotes extends Model
{
    protected $table = 'feuilles_notes';

    protected $fillable = ['id_annee_academique', 'id_promotion', 'id_cours', 'statut', 'date_transmission', 'updated_by'];

    public function notes(): HasMany
    {
        return $this->hasMany(NoteCours::class, 'id_feuille_notes');
    }
}

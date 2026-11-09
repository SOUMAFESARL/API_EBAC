<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Épreuve du bulletin de fin de cycle. Ne se supprime pas : elle se retire. */
#[Fillable(['code', 'libelle', 'coefficient', 'rang', 'actif', 'created_by', 'updated_by'])]
class EpreuveFinDeCycle extends Model
{
    public const COEFFICIENT_MIN = 1;

    public const COEFFICIENT_MAX = 20;

    protected $table = 'epreuves_fin_de_cycle';

    protected function casts(): array
    {
        return ['coefficient' => 'integer', 'rang' => 'integer', 'actif' => 'boolean'];
    }

    public function notes(): HasMany
    {
        return $this->hasMany(NoteFinDeCycle::class, 'id_epreuve');
    }
}

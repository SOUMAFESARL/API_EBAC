<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Note d'épreuve figée sur un bulletin de fin de cycle à sa validation. */
#[Fillable(['id_bulletin', 'id_epreuve', 'libelle', 'coefficient', 'note', 'rang', 'saisi_par'])]
class LigneBulletinFinDeCycle extends Model
{
    protected $table = 'lignes_bulletins_fin_de_cycle';

    protected function casts(): array
    {
        return ['note' => 'float', 'coefficient' => 'integer', 'rang' => 'integer'];
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'saisi_par');
    }
}

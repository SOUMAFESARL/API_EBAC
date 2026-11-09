<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Note d'un étudiant à une épreuve de fin de cycle, avec qui l'a saisie. */
#[Fillable(['id_epreuve', 'id_etudiant', 'id_promotion', 'note', 'saisi_par'])]
class NoteFinDeCycle extends Model
{
    protected $table = 'notes_fin_de_cycle';

    protected function casts(): array
    {
        return ['note' => 'float'];
    }

    public function epreuve(): BelongsTo
    {
        return $this->belongsTo(EpreuveFinDeCycle::class, 'id_epreuve');
    }

    public function etudiant(): BelongsTo
    {
        return $this->belongsTo(Etudiant::class, 'id_etudiant');
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'saisi_par');
    }
}

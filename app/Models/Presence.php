<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Presence extends Model
{
    protected $fillable = ['id_feuille_presence', 'id_etudiant', 'statut'];

    protected function casts(): array
    {
        return ['evaluation_autorisee_le' => 'datetime'];
    }

    public function feuille(): BelongsTo
    {
        return $this->belongsTo(FeuillePresence::class, 'id_feuille_presence');
    }

    public function etudiant(): BelongsTo
    {
        return $this->belongsTo(Etudiant::class, 'id_etudiant');
    }
}

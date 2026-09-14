<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FeuillePresence extends Model
{
    protected $table = 'feuilles_presence';

    protected $fillable = ['id_seance', 'statut', 'date_validation', 'validee_par', 'created_by', 'updated_by'];

    protected function casts(): array
    {
        return ['date_validation' => 'datetime'];
    }

    public function seance(): BelongsTo
    {
        return $this->belongsTo(SeanceCahierTexte::class, 'id_seance');
    }

    public function presences(): HasMany
    {
        return $this->hasMany(Presence::class, 'id_feuille_presence');
    }
}

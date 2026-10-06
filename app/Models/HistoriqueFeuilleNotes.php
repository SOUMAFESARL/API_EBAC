<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HistoriqueFeuilleNotes extends Model
{
    protected $table = 'historique_feuilles_notes';

    public $timestamps = false;

    protected $fillable = ['id_acteur', 'action', 'statut_avant', 'statut_apres', 'motif', 'created_at'];

    public function acteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_acteur');
    }
}

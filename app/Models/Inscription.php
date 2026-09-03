<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Inscription extends Model
{
    protected $fillable = ['id_etudiant', 'id_promotion', 'date_inscription', 'statut', 'decision_passage', 'date_decision', 'observations', 'created_by'];

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class, 'id_promotion');
    }

    public function etudiant(): BelongsTo
    {
        return $this->belongsTo(Etudiant::class, 'id_etudiant');
    }
}

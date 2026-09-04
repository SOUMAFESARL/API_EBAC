<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParcoursAcademique extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['moyenne_generale' => 'decimal:2', 'date_debut' => 'date:Y-m-d', 'date_fin' => 'date:Y-m-d'];
    }

    public function etudiant(): BelongsTo { return $this->belongsTo(Etudiant::class, 'id_etudiant'); }
    public function anneeAcademique(): BelongsTo { return $this->belongsTo(AnneeAcademique::class, 'id_annee_academique'); }
    public function niveau(): BelongsTo { return $this->belongsTo(Niveau::class, 'id_niveau'); }
    public function promotion(): BelongsTo { return $this->belongsTo(Promotion::class, 'id_promotion'); }
}

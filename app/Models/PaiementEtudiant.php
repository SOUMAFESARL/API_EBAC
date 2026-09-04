<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaiementEtudiant extends Model
{
    protected $table = 'paiements_etudiants';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['montant' => 'decimal:2', 'date_paiement' => 'datetime'];
    }

    public function etudiant(): BelongsTo { return $this->belongsTo(Etudiant::class, 'id_etudiant'); }
    public function inscription(): BelongsTo { return $this->belongsTo(Inscription::class, 'id_inscription'); }
    public function anneeAcademique(): BelongsTo { return $this->belongsTo(AnneeAcademique::class, 'id_annee_academique'); }
}

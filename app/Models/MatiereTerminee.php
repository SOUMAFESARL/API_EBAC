<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatiereTerminee extends Model
{
    protected $table = 'matieres_terminees';

    protected $fillable = ['id_matiere', 'id_promotion', 'id_annee_academique', 'terminee_par'];

    public function matiere(): BelongsTo { return $this->belongsTo(Matiere::class, 'id_matiere'); }
    public function promotion(): BelongsTo { return $this->belongsTo(Promotion::class, 'id_promotion'); }
    public function anneeAcademique(): BelongsTo { return $this->belongsTo(AnneeAcademique::class, 'id_annee_academique'); }
    public function auteur(): BelongsTo { return $this->belongsTo(User::class, 'terminee_par'); }
}

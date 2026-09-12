<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AffectationEnseignant extends Model
{
    protected $table = 'affectations_enseignants';

    protected $fillable = ['id_annee_academique', 'enseignant_id', 'id_matiere', 'id_cours', 'portee', 'date_debut', 'date_fin', 'motif_fin', 'created_by', 'updated_by'];

    protected function casts(): array
    {
        return ['date_debut' => 'date:Y-m-d', 'date_fin' => 'date:Y-m-d'];
    }

    public function anneeAcademique(): BelongsTo { return $this->belongsTo(AnneeAcademique::class, 'id_annee_academique'); }
    public function enseignant(): BelongsTo { return $this->belongsTo(User::class, 'enseignant_id'); }
    public function matiere(): BelongsTo { return $this->belongsTo(Matiere::class, 'id_matiere'); }
    public function cours(): BelongsTo { return $this->belongsTo(Cours::class, 'id_cours'); }
}

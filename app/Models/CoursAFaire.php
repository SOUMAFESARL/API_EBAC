<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoursAFaire extends Model
{
    protected $table = 'cours_a_faire';

    protected $fillable = ['id_etudiant', 'id_cours', 'id_matiere', 'id_seance', 'statut', 'motif'];

    public function cours(): BelongsTo
    {
        return $this->belongsTo(Cours::class, 'id_cours');
    }

    public function matiere(): BelongsTo
    {
        return $this->belongsTo(Matiere::class, 'id_matiere');
    }

    public function seance(): BelongsTo
    {
        return $this->belongsTo(SeanceCahierTexte::class, 'id_seance');
    }
}

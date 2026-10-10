<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgrammationRattrapage extends Model
{
    protected $table = 'programmations_rattrapage';

    protected $fillable = ['id_cours_a_faire', 'date_prevue', 'heure_debut', 'heure_fin',
        'enseignant_id', 'id_salle', 'statut', 'observations', 'created_by', 'updated_by'];

    protected function casts(): array
    {
        return ['date_prevue' => 'date:Y-m-d'];
    }

    public function coursAFaire(): BelongsTo
    {
        return $this->belongsTo(CoursAFaire::class, 'id_cours_a_faire');
    }

    public function enseignant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enseignant_id');
    }

    public function salle(): BelongsTo
    {
        return $this->belongsTo(Salle::class, 'id_salle');
    }
}

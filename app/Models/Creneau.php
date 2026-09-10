<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Creneau extends Model
{
    use SoftDeletes;

    protected $table = 'creneaux';

    protected $fillable = ['id_module_calendrier', 'id_niveau', 'id_matiere', 'id_cours', 'id_promotion', 'enseignant_id', 'id_salle', 'jour', 'heure_debut', 'heure_fin', 'created_by', 'updated_by', 'deleted_by'];

    protected function casts(): array
    {
        return ['jour' => 'integer'];
    }

    public function moduleCalendrier(): BelongsTo
    {
        return $this->belongsTo(ModuleCalendrier::class, 'id_module_calendrier');
    }

    public function niveau(): BelongsTo
    {
        return $this->belongsTo(Niveau::class, 'id_niveau');
    }

    public function matiere(): BelongsTo
    {
        return $this->belongsTo(Matiere::class, 'id_matiere');
    }

    public function cours(): BelongsTo
    {
        return $this->belongsTo(Cours::class, 'id_cours');
    }

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class, 'id_promotion');
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

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class SeanceCahierTexte extends Model
{
    use SoftDeletes;

    protected $table = 'seances_cahier_texte';

    protected $fillable = ['id_creneau', 'id_module_calendrier', 'enseignant_id', 'id_niveau', 'id_matiere', 'id_cours', 'id_promotion', 'id_salle',
        'date_prevue', 'heure_debut_prevue', 'heure_fin_prevue', 'statut', 'date_effective', 'heure_effective', 'duree_reelle_minutes',
        'theme_traite', 'observations', 'supports_pedagogiques', 'motif_annulation', 'source', 'created_by', 'updated_by', 'deleted_by'];

    protected function casts(): array
    {
        return ['date_prevue' => 'date:Y-m-d', 'date_effective' => 'date:Y-m-d', 'duree_reelle_minutes' => 'integer'];
    }

    public function creneau(): BelongsTo
    {
        return $this->belongsTo(Creneau::class, 'id_creneau');
    }

    public function moduleCalendrier(): BelongsTo
    {
        return $this->belongsTo(ModuleCalendrier::class, 'id_module_calendrier');
    }

    public function enseignant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enseignant_id');
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

    public function salle(): BelongsTo
    {
        return $this->belongsTo(Salle::class, 'id_salle');
    }

    public function feuillePresence(): HasOne
    {
        return $this->hasOne(FeuillePresence::class, 'id_seance');
    }
}

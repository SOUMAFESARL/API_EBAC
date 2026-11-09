<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Affectation d'un étudiant de la campagne à une église et à un tuteur.
 * « affectee » reste une saisie reprenable ; « validee » ouvre le stage et
 * ne se reprend pas.
 */
class AffectationStage extends Model
{
    protected $table = 'affectations_stage';

    protected $fillable = [
        'id_campagne', 'id_etudiant', 'id_eglise', 'tuteur_nom', 'tuteur_telephone',
        'tuteur_email', 'statut', 'validee_le', 'validee_par', 'updated_by',
    ];

    protected function casts(): array
    {
        return ['validee_le' => 'datetime'];
    }

    public function campagne(): BelongsTo
    {
        return $this->belongsTo(CampagneStage::class, 'id_campagne');
    }

    public function etudiant(): BelongsTo
    {
        return $this->belongsTo(Etudiant::class, 'id_etudiant');
    }

    public function eglise(): BelongsTo
    {
        return $this->belongsTo(Eglise::class, 'id_eglise')->withTrashed();
    }

    public function stage(): HasOne
    {
        return $this->hasOne(Stage::class, 'id_affectation');
    }

    public function complete(): bool
    {
        return $this->id_eglise !== null && trim((string) $this->tuteur_nom) !== '';
    }
}

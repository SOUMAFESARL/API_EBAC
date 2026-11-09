<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Stage ouvert par la validation d'une affectation. Ses bornes sont celles
 * de sa campagne ; son statut se lit des dates et de l'évaluation remise.
 */
class Stage extends Model
{
    protected $table = 'stages';

    protected $fillable = [
        'id_affectation', 'id_campagne', 'id_etudiant', 'id_eglise', 'encadreur',
        'evaluation_criteres', 'evaluation_appreciation', 'evaluation_note',
        'evaluation_remise_le', 'evaluation_remise_par',
        'part_admin_criteres', 'part_admin_appreciation', 'part_admin_portee_le', 'part_admin_portee_par',
        'validation_decision', 'validation_motif', 'validation_prononcee_le', 'validation_prononcee_par',
    ];

    protected function casts(): array
    {
        return [
            'evaluation_criteres' => 'array',
            'part_admin_criteres' => 'array',
            'evaluation_note' => 'float',
            'evaluation_remise_le' => 'datetime',
            'part_admin_portee_le' => 'datetime',
            'validation_prononcee_le' => 'datetime',
        ];
    }

    public function campagne(): BelongsTo
    {
        return $this->belongsTo(CampagneStage::class, 'id_campagne');
    }

    public function affectation(): BelongsTo
    {
        return $this->belongsTo(AffectationStage::class, 'id_affectation');
    }

    public function etudiant(): BelongsTo
    {
        return $this->belongsTo(Etudiant::class, 'id_etudiant');
    }

    public function eglise(): BelongsTo
    {
        return $this->belongsTo(Eglise::class, 'id_eglise')->withTrashed();
    }

    public function semaines(): HasMany
    {
        return $this->hasMany(SemaineStage::class, 'id_stage');
    }

    public function rapports(): HasMany
    {
        return $this->hasMany(RapportStage::class, 'id_stage');
    }

    public function evaluateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluation_remise_par');
    }

    public function agentPartAdministration(): BelongsTo
    {
        return $this->belongsTo(User::class, 'part_admin_portee_par');
    }

    public function valideur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validation_prononcee_par');
    }

    /** L'évaluation finale remise, le journal est clos. */
    public function clos(): bool
    {
        return $this->evaluation_remise_le !== null;
    }

    public function statut(): string
    {
        $aujourdhui = now()->toDateString();
        $campagne = $this->campagne;

        if ($this->clos() || $campagne->date_fin->toDateString() < $aujourdhui) {
            return 'termine';
        }

        return $campagne->date_debut->toDateString() <= $aujourdhui ? 'en-cours' : 'a-demarrer';
    }
}

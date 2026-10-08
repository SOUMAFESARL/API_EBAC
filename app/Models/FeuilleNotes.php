<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FeuilleNotes extends Model
{
    public const STATUTS_TRANSMIS = ['transmise', 'validee_secretariat', 'rejetee_secretariat', 'transmise_direction', 'validee_direction', 'rejetee_direction'];

    public static function statutsPourRole(string $role): array
    {
        return collect(self::STATUTS_TRANSMIS)->mapWithKeys(fn ($statut) => [$statut => match ($role) {
            'ENSEIGNANT' => 'transmise',
            'SECRETARIAT', 'SECRETAIRE_ACADEMIQUE' => match ($statut) {
                'transmise', 'rejetee_direction' => 'a_verifier',
                default => $statut,
            },
            'DIRECTION' => match ($statut) {
                'transmise_direction' => 'en_attente',
                'validee_direction' => 'validee',
                'rejetee_direction' => 'rejetee',
                default => $statut,
            },
            default => $statut,
        }])->all();
    }

    public function statutPourRole(string $role): string
    {
        return self::statutsPourRole($role)[$this->statut] ?? $this->statut;
    }

    protected $table = 'feuilles_notes';

    protected $attributes = ['statut' => 'brouillon'];

    protected $fillable = ['id_annee_academique', 'id_promotion', 'id_cours', 'id_matiere', 'id_seance', 'statut', 'date_transmission', 'transmise_par', 'updated_by'];

    public function seance(): BelongsTo
    {
        return $this->belongsTo(SeanceCahierTexte::class, 'id_seance');
    }

    public function historique(): HasMany
    {
        return $this->hasMany(HistoriqueFeuilleNotes::class, 'id_feuille_notes')->orderBy('id');
    }

    public function changerStatut(string $statut, int $acteur, string $action, ?string $motif = null): void
    {
        $avant = $this->statut;
        $this->update(['statut' => $statut, 'updated_by' => $acteur]);
        $this->historique()->create(['id_acteur' => $acteur, 'action' => $action,
            'statut_avant' => $avant, 'statut_apres' => $statut, 'motif' => $motif, 'created_at' => now()]);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(NoteCours::class, 'id_feuille_notes');
    }

    public function anneeAcademique(): BelongsTo
    {
        return $this->belongsTo(AnneeAcademique::class, 'id_annee_academique');
    }

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class, 'id_promotion');
    }

    public function matiere(): BelongsTo
    {
        return $this->belongsTo(Matiere::class, 'id_matiere');
    }

    public function cours(): BelongsTo
    {
        return $this->belongsTo(Cours::class, 'id_cours');
    }

    public function dernierModificateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function enseignant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'transmise_par');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class FeuilleNotes extends Model
{
    public const STATUTS_TRANSMIS = ['transmise', 'validee_secretariat', 'rejetee_secretariat', 'transmise_direction', 'validee_direction', 'rejetee_direction'];

    /**
     * Vue du circuit qu'adopte le compte : elle ne sert qu'au libellé
     * historique `statut` (le front lit `statut_workflow`). Elle se déduit
     * des droits cochés, plus du code du rôle — un rôle personnalisé doté
     * de la supervision lit comme le secrétariat. Qui tient les deux
     * étapes (l'Administrateur) lit le statut brut, comme avant.
     */
    public static function vueDe(?User $utilisateur): ?string
    {
        if (! $utilisateur) {
            return null;
        }
        if ($utilisateur->role?->code === 'ENSEIGNANT') {
            return 'enseignant';
        }
        $supervision = $utilisateur->detientUnDroit('notes.supervision-notes.consulter');
        $validation = $utilisateur->detientUnDroit('notes.validation-notes.consulter');

        return match (true) {
            $supervision && ! $validation => 'secretariat',
            $validation && ! $supervision => 'direction',
            default => null,
        };
    }

    public static function statutsPourVue(?string $vue): array
    {
        return collect(self::STATUTS_TRANSMIS)->mapWithKeys(fn ($statut) => [$statut => match ($vue) {
            'enseignant' => 'transmise',
            'secretariat' => match ($statut) {
                'transmise' => 'a_verifier',
                default => $statut,
            },
            'direction' => match ($statut) {
                'transmise_direction' => 'en_attente',
                'validee_direction' => 'validee',
                'rejetee_direction' => 'rejetee',
                default => $statut,
            },
            default => $statut,
        }])->all();
    }

    public function statutPourVue(?string $vue): string
    {
        return self::statutsPourVue($vue)[$this->statut] ?? $this->statut;
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

    public function derniereDecision(): HasOne
    {
        return $this->hasOne(HistoriqueFeuilleNotes::class, 'id_feuille_notes')->latestOfMany();
    }

    public function motifRejet(): ?string
    {
        if (! in_array($this->statut, ['rejetee_secretariat', 'rejetee_direction'], true)) {
            return null;
        }

        return $this->relationLoaded('historique')
            ? $this->historique->last()?->motif : $this->derniereDecision?->motif;
    }

    public function changerStatut(string $statut, int $acteur, string $action, ?string $motif = null): void
    {
        $avant = $this->statut;
        $this->update(['statut' => $statut, 'updated_by' => $acteur]);
        $this->historique()->create(['id_acteur' => $acteur, 'action' => $action,
            'statut_avant' => $avant, 'statut_apres' => $statut, 'motif' => $motif, 'created_at' => now()]);
        app(\App\Services\GenerationBulletinsService::class)->synchroniser($this, $acteur);
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

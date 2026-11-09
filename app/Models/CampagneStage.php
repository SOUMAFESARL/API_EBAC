<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Campagne de stage d'une promotion : les bornes du stage de 3e Année et
 * une affectation par étudiant inscrit. Son statut ne se stocke pas, il se
 * lit des dates (`statut()`).
 */
class CampagneStage extends Model
{
    protected $table = 'campagnes_stage';

    /** En deçà, le journal hebdomadaire et le rapport mi-parcours n'ont plus de sens. */
    public const SEMAINES_MIN = 4;

    protected $fillable = ['id_promotion', 'date_debut', 'date_fin', 'ouverte_par', 'updated_by'];

    protected function casts(): array
    {
        return [
            'date_debut' => 'date:Y-m-d',
            'date_fin' => 'date:Y-m-d',
        ];
    }

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class, 'id_promotion');
    }

    public function affectations(): HasMany
    {
        return $this->hasMany(AffectationStage::class, 'id_campagne');
    }

    public function stages(): HasMany
    {
        return $this->hasMany(Stage::class, 'id_campagne');
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ouverte_par');
    }

    /** « ouverte » avant le début, « en-cours » jusqu'à la fin, « cloturee » ensuite. */
    public function statut(): string
    {
        $aujourdhui = now()->toDateString();

        if ($this->date_fin->toDateString() < $aujourdhui) {
            return 'cloturee';
        }

        return $this->date_debut->toDateString() <= $aujourdhui ? 'en-cours' : 'ouverte';
    }

    /** Semaines entamées entre deux dates : dix jours en font deux. */
    public static function semainesEntre(string $debut, string $fin): int
    {
        $jours = (int) round((strtotime($fin.' 00:00:00 UTC') - strtotime($debut.' 00:00:00 UTC')) / 86400);

        return $jours < 0 ? 0 : (int) ceil(($jours + 1) / 7);
    }

    public function semainesPrevues(): int
    {
        return self::semainesEntre($this->date_debut->toDateString(), $this->date_fin->toDateString());
    }
}

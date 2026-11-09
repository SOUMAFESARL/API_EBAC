<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bulletin extends Model
{
    /** Période du bulletin de fin de cycle de 3e Année (09/11/2026). */
    public const PERIODE_FIN_DE_CYCLE = 'Fin de cycle';

    /** Notes de fin de cycle validées et figées, pas encore visibles de l'étudiant. */
    public const STATUT_VALIDE = 'Validé';

    public const STATUT_PUBLIE = 'Publié';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['moyenne' => 'decimal:2', 'rang' => 'integer', 'date_publication' => 'datetime', 'valide_le' => 'datetime'];
    }

    public function estFinDeCycle(): bool
    {
        return $this->periode === self::PERIODE_FIN_DE_CYCLE;
    }

    public function inscription(): BelongsTo { return $this->belongsTo(Inscription::class, 'id_inscription'); }
    public function niveau(): BelongsTo { return $this->belongsTo(Niveau::class, 'id_niveau')->withTrashed(); }
    public function lignes(): HasMany { return $this->hasMany(LigneBulletin::class, 'id_bulletin'); }
    public function lignesFinDeCycle(): HasMany { return $this->hasMany(LigneBulletinFinDeCycle::class, 'id_bulletin')->orderBy('rang')->orderBy('id'); }
    public function valideur(): BelongsTo { return $this->belongsTo(User::class, 'valide_par'); }
    public function publieur(): BelongsTo { return $this->belongsTo(User::class, 'publie_par'); }
}

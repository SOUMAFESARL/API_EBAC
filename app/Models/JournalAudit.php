<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class JournalAudit extends Model
{
    protected $table = 'journaux_audit';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'horodatage' => 'immutable_datetime',
            'valeur_avant' => 'array',
            'valeur_apres' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Le journal d’audit est immuable : une entrée ne se modifie pas.'));
        static::deleting(fn () => throw new LogicException('Le journal d’audit est immuable : une entrée ne se supprime pas.'));
    }

    public function utilisateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user');
    }
}

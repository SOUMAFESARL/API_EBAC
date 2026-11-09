<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['id_cours', 'nom', 'type', 'depose_par'])]
class SupportPedagogique extends Model
{
    public const TYPES = ['cours', 'travaux-diriges', 'exercices', 'corrige', 'reference', 'media'];

    protected $table = 'supports_pedagogiques';

    public function cours(): BelongsTo
    {
        return $this->belongsTo(Cours::class, 'id_cours');
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'depose_par');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(VersionSupport::class, 'id_support')->orderByDesc('numero');
    }
}

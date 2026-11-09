<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['id_support', 'numero', 'chemin', 'nom_fichier', 'format', 'taille_octets', 'note', 'depose_par'])]
class VersionSupport extends Model
{
    protected $table = 'versions_supports';

    protected function casts(): array
    {
        return ['numero' => 'integer', 'taille_octets' => 'integer'];
    }

    public function support(): BelongsTo
    {
        return $this->belongsTo(SupportPedagogique::class, 'id_support');
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'depose_par');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un support = un fichier, sans versions : on le supprime pour le remplacer.
 * Il se range sous une matière ; module et cours sont facultatifs.
 */
#[Fillable(['id_matiere', 'id_module', 'id_cours', 'nom', 'type', 'chemin', 'nom_fichier', 'format', 'taille_octets', 'depose_par'])]
class SupportPedagogique extends Model
{
    public const TYPES = ['cours', 'travaux-diriges', 'exercices', 'corrige', 'reference', 'media'];

    protected $table = 'supports_pedagogiques';

    protected function casts(): array
    {
        return ['taille_octets' => 'integer', 'id_matiere' => 'integer', 'id_module' => 'integer', 'id_cours' => 'integer'];
    }

    public function matiere(): BelongsTo
    {
        return $this->belongsTo(Matiere::class, 'id_matiere');
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class, 'id_module');
    }

    public function cours(): BelongsTo
    {
        return $this->belongsTo(Cours::class, 'id_cours');
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'depose_par');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\URL;

class FichierDossierEtudiant extends Model
{
    protected $table = 'fichiers_dossiers_etudiants';

    protected $appends = ['url'];

    protected $fillable = [
        'id_dossier_etudiant',
        'type_piece',
        'nom_original',
        'chemin',
        'mime_type',
        'taille',
    ];

    protected function casts(): array
    {
        return ['taille' => 'integer'];
    }

    protected function url(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->chemin
            ? URL::temporarySignedRoute(
                'api.v1.fichiers-preinscriptions.show',
                now()->addMinutes(15),
                ['chemin' => $this->chemin],
            )
            : null);
    }

    public function dossier(): BelongsTo
    {
        return $this->belongsTo(DossierEtudiant::class, 'id_dossier_etudiant');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Rapport déposé par le stagiaire : un fichier par type, remplacé au redépôt. */
class RapportStage extends Model
{
    protected $table = 'rapports_stage';

    /** Les deux documents attendus d'un stage, dans l'ordre d'affichage. */
    public const TYPES = [
        'mi-parcours' => 'Rapport mi-parcours',
        'final' => 'Rapport de stage 3e Année — version finale',
    ];

    public const EXTENSIONS = ['pdf', 'docx', 'odt'];

    /** 15 Mo, comme le plafond affiché au stagiaire. */
    public const TAILLE_MAX_KO = 15 * 1024;

    protected $fillable = ['id_stage', 'type', 'chemin', 'nom_fichier', 'taille_octets'];

    protected function casts(): array
    {
        return ['taille_octets' => 'integer'];
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(Stage::class, 'id_stage');
    }
}

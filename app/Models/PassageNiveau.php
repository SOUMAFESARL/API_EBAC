<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PassageNiveau extends Model
{
    protected $table = 'passages_niveau';

    protected $fillable = ['id_promotion', 'id_niveau_source', 'id_niveau_cible', 'id_annee_source', 'id_annee_cible',
        'etudiants_avances', 'etudiants_non_avances', 'cours_a_faire_reportes', 'malgre_notes_en_attente', 'effectue_par'];

    protected function casts(): array
    {
        return ['malgre_notes_en_attente' => 'boolean'];
    }

    public function promotion(): BelongsTo { return $this->belongsTo(Promotion::class, 'id_promotion'); }
    public function niveauCible(): BelongsTo { return $this->belongsTo(Niveau::class, 'id_niveau_cible'); }
}

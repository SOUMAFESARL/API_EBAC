<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class DossierEtudiant extends Model
{
    use SoftDeletes;

    protected $table = 'dossiers_etudiants';
    protected $fillable = ['id_etudiant', 'numero_dossier', 'statut', 'date_ouverture', 'pieces_requises', 'observations', 'user_id', 'created_by', 'updated_by', 'deleted_by'];

    protected function casts(): array { return ['date_ouverture' => 'date:Y-m-d', 'pieces_requises' => 'array']; }

    public function etudiant(): BelongsTo { return $this->belongsTo(Etudiant::class, 'id_etudiant'); }
}

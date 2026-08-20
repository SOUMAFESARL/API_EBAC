<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'code', 'nom', 'sigle', 'pasteur_principal', 'denomination', 'adresse',
    'region', 'district', 'ville_commune', 'telephone', 'email', 'statut',
    'capacite_max_stagiaires', 'representants', 'observations', 'user_id',
    'user_code', 'created_by', 'updated_by', 'deleted_by',
])]
class Eglise extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'capacite_max_stagiaires' => 'integer',
            'representants' => 'array',
        ];
    }

    public function compte(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function etudiants(): HasMany
    {
        return $this->hasMany(Etudiant::class, 'eglise_id');
    }

    public function etudiantsHistoriques(): HasMany
    {
        return $this->hasMany(Etudiant::class, 'id_eglise');
    }

    public function createur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function modificateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function suppresseur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }
}

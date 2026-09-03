<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Etudiant extends Model
{
    use SoftDeletes;

    protected $fillable = ['user_id', 'matricule', 'nom', 'prenoms', 'civilite_id', 'date_naissance', 'lieu_naissance', 'nationalite', 'email', 'telephone', 'adresse', 'eglise_id', 'statut_professionnel', 'situation_matrimonial', 'nombre_enfant', 'photo_identite', 'date_inscription', 'statut', 'created_by', 'updated_by', 'deleted_by'];

    protected function casts(): array
    {
        return ['date_naissance' => 'date:Y-m-d', 'date_inscription' => 'date:Y-m-d', 'nombre_enfant' => 'integer'];
    }

    public function eglise(): BelongsTo
    {
        return $this->belongsTo(Eglise::class, 'eglise_id');
    }

    public function civilite(): BelongsTo
    {
        return $this->belongsTo(Civilite::class, 'civilite_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function dossier(): HasOne
    {
        return $this->hasOne(DossierEtudiant::class, 'id_etudiant');
    }

    public function inscriptions(): HasMany
    {
        return $this->hasMany(Inscription::class, 'id_etudiant');
    }
}

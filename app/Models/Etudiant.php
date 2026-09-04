<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\URL;

class Etudiant extends Model
{
    use SoftDeletes;

    protected $appends = ['photo_identite_url'];

    protected $fillable = ['user_id', 'matricule', 'nom', 'prenoms', 'civilite_id', 'date_naissance', 'lieu_naissance', 'nationalite', 'email', 'telephone', 'adresse', 'eglise_id', 'statut_professionnel', 'situation_matrimonial', 'nombre_enfant', 'photo_identite', 'date_inscription', 'statut', 'created_by', 'updated_by', 'deleted_by'];

    protected function casts(): array
    {
        return ['date_naissance' => 'date:Y-m-d', 'date_inscription' => 'date:Y-m-d', 'nombre_enfant' => 'integer'];
    }

    protected function photoIdentiteUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->photo_identite
            ? URL::temporarySignedRoute(
                'api.v1.fichiers-preinscriptions.show',
                now()->addMinutes(15),
                ['chemin' => $this->photo_identite],
            )
            : null);
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

    public function inscriptionActuelle(): HasOne
    {
        return $this->hasOne(Inscription::class, 'id_etudiant')->latestOfMany('date_inscription');
    }

    public function paiements(): HasMany
    {
        return $this->hasMany(PaiementEtudiant::class, 'id_etudiant');
    }

    public function parcoursAcademiques(): HasMany
    {
        return $this->hasMany(ParcoursAcademique::class, 'id_etudiant');
    }
}

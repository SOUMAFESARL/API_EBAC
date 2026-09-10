<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CalendrierAcademique extends Model
{
    protected $table = 'calendriers_academiques';

    protected $fillable = ['id_annee_academique', 'created_by', 'updated_by'];

    public function anneeAcademique(): BelongsTo
    {
        return $this->belongsTo(AnneeAcademique::class, 'id_annee_academique');
    }

    public function modules(): HasMany
    {
        return $this->hasMany(ModuleCalendrier::class, 'id_calendrier')->orderBy('ordre');
    }

    public function evenements(): HasMany
    {
        return $this->hasMany(EvenementCalendrier::class, 'id_calendrier')->orderBy('date_debut')->orderBy('id');
    }
}

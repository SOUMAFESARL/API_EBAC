<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['code', 'rang', 'id_annee_academique', 'id_niveau', 'capacite', 'statut', 'date_ouverture', 'date_cloture', 'user_id', 'created_by', 'updated_by', 'deleted_by'])]
class Promotion extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'rang' => 'integer',
            'capacite' => 'integer',
            'date_ouverture' => 'date:Y-m-d',
            'date_cloture' => 'date:Y-m-d',
        ];
    }

    public function anneeAcademique(): BelongsTo { return $this->belongsTo(AnneeAcademique::class, 'id_annee_academique'); }
    public function niveau(): BelongsTo { return $this->belongsTo(Niveau::class, 'id_niveau'); }
    public function inscriptions(): HasMany { return $this->hasMany(Inscription::class, 'id_promotion'); }
    public function utilisateur(): BelongsTo { return $this->belongsTo(User::class, 'user_id'); }
}

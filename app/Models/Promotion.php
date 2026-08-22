<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['code', 'num_promotion', 'annee_entree', 'id_niveau', 'statut', 'date_ouverture', 'date_cloture', 'user_id', 'created_by', 'updated_by', 'deleted_by'])]
class Promotion extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'num_promotion' => 'integer',
            'annee_entree' => 'integer',
            'date_ouverture' => 'date:Y-m-d',
            'date_cloture' => 'date:Y-m-d',
        ];
    }

    public function niveau(): BelongsTo { return $this->belongsTo(Niveau::class, 'id_niveau'); }
    public function inscriptions(): HasMany { return $this->hasMany(Inscription::class, 'id_promotion'); }
    public function utilisateur(): BelongsTo { return $this->belongsTo(User::class, 'user_id'); }
}

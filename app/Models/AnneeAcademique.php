<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['libelle', 'date_debut', 'date_fin', 'active', 'user_id', 'created_by', 'updated_by', 'deleted_by'])]
class AnneeAcademique extends Model
{
    use SoftDeletes;

    protected $table = 'annees_academiques';

    protected function casts(): array
    {
        return ['date_debut' => 'date:Y-m-d', 'date_fin' => 'date:Y-m-d', 'active' => 'boolean'];
    }

    public function utilisateur(): BelongsTo { return $this->belongsTo(User::class, 'user_id'); }
    public function createur(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function modificateur(): BelongsTo { return $this->belongsTo(User::class, 'updated_by'); }
    public function suppresseur(): BelongsTo { return $this->belongsTo(User::class, 'deleted_by'); }
}

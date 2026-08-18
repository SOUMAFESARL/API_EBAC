<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'libelle',
    'code',
    'rang',
    'statut',
    'user_id',
    'user_code',
    'created_by',
    'updated_by',
    'deleted_by',
])]
class Niveau extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'rang' => 'integer',
        ];
    }

    public function utilisateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
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

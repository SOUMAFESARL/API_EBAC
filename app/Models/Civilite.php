<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'code',
    'name',
    'abreviation',
    'description',
    'actif',
    'created_by',
    'updated_by',
    'deleted_by',
    'cree_le',
    'modifie_le',
])]
class Civilite extends Model
{
    use SoftDeletes;

    protected $table = 'civilite';

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'actif' => 'boolean',
            'deleted_at' => 'datetime',
            'cree_le' => 'datetime',
            'modifie_le' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'civilite_id');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function deleter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }
}

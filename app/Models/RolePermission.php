<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

#[Fillable([
    'id_role',
    'id_permission',
    'actif',
    'created_by',
    'updated_by',
    'deleted_by',
])]
class RolePermission extends Pivot
{
    public $incrementing = false;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'actif' => 'boolean',
            'deleted_at' => 'datetime',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'id_role');
    }

    public function permission(): BelongsTo
    {
        return $this->belongsTo(Permission::class, 'id_permission');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
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

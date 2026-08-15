<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'code',
    'libelle',
    'description',
    'actif',
    'created_by',
    'updated_by',
    'deleted_by',
])]
class Role extends Model
{
    use SoftDeletes;

    public $timestamps = false;

    protected function casts(): array
    {
        return ['actif' => 'boolean'];
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permissions', 'id_role', 'id_permission')
            ->using(RolePermission::class)
            ->withPivot(['actif', 'created_by', 'updated_by', 'deleted_by', 'deleted_at'])
            ->wherePivotNull('deleted_at');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'id_role');
    }

    public function actionsParMenu(): BelongsToMany
    {
        return $this->belongsToMany(Action::class, 'role_menu_actions', 'id_role', 'id_action')
            ->withPivot(['id_menu', 'created_by', 'created_at']);
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

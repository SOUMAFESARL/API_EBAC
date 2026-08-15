<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'code',
    'libelle',
    'description',
    'created_by',
    'updated_by',
    'deleted_by',
])]
class Permission extends Model
{
    use SoftDeletes;

    public $timestamps = false;

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_permissions', 'id_permission', 'id_role')
            ->using(RolePermission::class)
            ->withPivot(['actif', 'created_by', 'updated_by', 'deleted_by', 'deleted_at'])
            ->wherePivotNull('deleted_at');
    }

    public function menus(): BelongsToMany
    {
        return $this->belongsToMany(Menu::class, 'menu_permissions', 'id_permission', 'id_menu')
            ->withPivot('permission_principale');
    }

    public function actions(): BelongsToMany
    {
        return $this->belongsToMany(Action::class, 'permission_actions', 'id_permission', 'id_action')
            ->withPivot('created_by');
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

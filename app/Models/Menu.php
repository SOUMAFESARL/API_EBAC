<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'id_parent', 'code', 'libelle', 'description', 'route', 'route_active', 'icone',
    'groupe', 'ordre', 'visible', 'actif', 'created_by', 'updated_by', 'deleted_by',
])]
class Menu extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return ['visible' => 'boolean', 'actif' => 'boolean', 'ordre' => 'integer'];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'id_parent');
    }

    public function enfants(): HasMany
    {
        return $this->hasMany(self::class, 'id_parent')->orderBy('ordre')->orderBy('libelle');
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'menu_permissions', 'id_menu', 'id_permission')
            ->withPivot('permission_principale');
    }
}

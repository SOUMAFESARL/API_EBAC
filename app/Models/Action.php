<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['code', 'libelle', 'description', 'actif', 'created_by', 'updated_by', 'deleted_by'])]
class Action extends Model
{
    use SoftDeletes;

    public $timestamps = false;

    protected function casts(): array
    {
        return ['actif' => 'boolean'];
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'permission_actions', 'id_action', 'id_permission')
            ->withPivot('created_by');
    }
}

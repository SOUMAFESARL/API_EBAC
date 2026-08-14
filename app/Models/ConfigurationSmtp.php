<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'host',
    'port',
    'username',
    'password',
    'scheme',
    'from_address',
    'from_name',
    'actif',
    'created_by',
    'updated_by',
    'deleted_by',
])]
#[Hidden(['password'])]
class ConfigurationSmtp extends Model
{
    use SoftDeletes;

    public const CREATED_AT = 'cree_le';

    public const UPDATED_AT = 'modifie_le';

    protected $table = 'configurations_smtp';

    protected function casts(): array
    {
        return [
            'port' => 'integer',
            'username' => 'encrypted',
            'password' => 'encrypted',
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

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function deleter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }
}

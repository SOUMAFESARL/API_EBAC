<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Cours extends Model
{
    use SoftDeletes;

    protected $table = 'cours';

    protected $fillable = ['id_module', 'code', 'libelle', 'volume_horaire', 'coefficient', 'ordre', 'actif', 'user_id', 'created_by', 'updated_by', 'deleted_by'];

    protected function casts(): array
    {
        return [
            'volume_horaire' => 'decimal:2',
            'coefficient' => 'decimal:2',
            'ordre' => 'integer',
            'actif' => 'boolean',
        ];
    }

    public function module(): BelongsTo { return $this->belongsTo(Module::class, 'id_module'); }
    public function utilisateur(): BelongsTo { return $this->belongsTo(User::class, 'user_id'); }
}

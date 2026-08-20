<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['code', 'libelle', 'id_niveau', 'coefficient', 'type', 'description', 'objectifs', 'prerequis', 'note_validation', 'obligatoire', 'active', 'version', 'user_id', 'created_by', 'updated_by', 'deleted_by'])]
class Matiere extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'coefficient' => 'decimal:2',
            'note_validation' => 'decimal:2',
            'obligatoire' => 'boolean',
            'active' => 'boolean',
            'version' => 'integer',
        ];
    }

    public function niveau(): BelongsTo { return $this->belongsTo(Niveau::class, 'id_niveau'); }
    public function modules(): HasMany { return $this->hasMany(Module::class, 'id_matiere'); }
    public function utilisateur(): BelongsTo { return $this->belongsTo(User::class, 'user_id'); }
}

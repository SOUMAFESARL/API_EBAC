<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['id_matiere', 'code', 'libelle', 'ordre', 'description', 'user_id', 'created_by', 'updated_by', 'deleted_by'])]
class Module extends Model
{
    use SoftDeletes;

    protected function casts(): array { return ['ordre' => 'integer']; }

    public function matiere(): BelongsTo { return $this->belongsTo(Matiere::class, 'id_matiere'); }
    public function cours(): HasMany { return $this->hasMany(Cours::class, 'id_module'); }
    public function utilisateur(): BelongsTo { return $this->belongsTo(User::class, 'user_id'); }
}

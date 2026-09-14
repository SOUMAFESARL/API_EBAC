<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['code', 'libelle', 'id_niveau', 'enseignant_id', 'coefficient', 'volume_horaire', 'type', 'description', 'objectifs', 'prerequis', 'note_validation', 'obligatoire', 'active', 'version', 'user_id', 'created_by', 'updated_by', 'deleted_by'])]
class Matiere extends Model
{
    use SoftDeletes;

    protected $appends = ['module_calendrier_id'];

    protected function casts(): array
    {
        return [
            'coefficient' => 'decimal:2',
            'volume_horaire' => 'decimal:2',
            'note_validation' => 'decimal:2',
            'obligatoire' => 'boolean',
            'active' => 'boolean',
            'version' => 'integer',
        ];
    }

    public function niveau(): BelongsTo
    {
        return $this->belongsTo(Niveau::class, 'id_niveau');
    }

    public function enseignant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enseignant_id');
    }

    public function modules(): HasMany
    {
        return $this->hasMany(Module::class, 'id_matiere');
    }

    public function modulesCalendrier(): BelongsToMany
    {
        return $this->belongsToMany(ModuleCalendrier::class, 'matiere_module_calendrier', 'id_matiere', 'id_module_calendrier')->withTimestamps();
    }

    public function utilisateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    protected function moduleCalendrierId(): Attribute
    {
        return Attribute::get(fn () => $this->modulesCalendrier->pluck('id')->values()->all());
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ModuleCalendrier extends Model
{
    protected $table = 'modules_calendrier';

    protected $fillable = ['id_calendrier', 'libelle', 'ordre', 'date_debut', 'date_fin'];

    protected function casts(): array
    {
        return ['date_debut' => 'date:Y-m-d', 'date_fin' => 'date:Y-m-d', 'ordre' => 'integer'];
    }

    public function calendrier(): BelongsTo
    {
        return $this->belongsTo(CalendrierAcademique::class, 'id_calendrier');
    }

    public function evenements(): HasMany
    {
        return $this->hasMany(EvenementCalendrier::class, 'id_module_calendrier');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EvenementCalendrier extends Model
{
    protected $table = 'evenements_calendrier';

    protected $fillable = ['id_calendrier', 'id_module_calendrier', 'type', 'libelle', 'date_debut', 'date_fin'];

    protected function casts(): array
    {
        return ['date_debut' => 'date:Y-m-d', 'date_fin' => 'date:Y-m-d'];
    }

    public function calendrier(): BelongsTo
    {
        return $this->belongsTo(CalendrierAcademique::class, 'id_calendrier');
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(ModuleCalendrier::class, 'id_module_calendrier');
    }
}

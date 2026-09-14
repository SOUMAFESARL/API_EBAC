<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PublicationProgramme extends Model
{
    protected $fillable = ['id_module_calendrier', 'statut', 'version', 'date_publication', 'date_retrait', 'publie_par', 'retire_par'];

    protected function casts(): array
    {
        return ['version' => 'integer', 'date_publication' => 'datetime', 'date_retrait' => 'datetime'];
    }

    public function moduleCalendrier(): BelongsTo
    {
        return $this->belongsTo(ModuleCalendrier::class, 'id_module_calendrier');
    }

    public function auteurPublication(): BelongsTo
    {
        return $this->belongsTo(User::class, 'publie_par');
    }

    public function auteurRetrait(): BelongsTo
    {
        return $this->belongsTo(User::class, 'retire_par');
    }
}

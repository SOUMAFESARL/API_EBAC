<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LigneBulletin extends Model
{
    protected $table = 'lignes_bulletins';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['note' => 'decimal:2', 'coefficient' => 'decimal:2', 'moyenne_ponderee' => 'decimal:2'];
    }

    public function bulletin(): BelongsTo { return $this->belongsTo(Bulletin::class, 'id_bulletin'); }
    public function matiere(): BelongsTo { return $this->belongsTo(Matiere::class, 'id_matiere'); }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bulletin extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['moyenne' => 'decimal:2', 'rang' => 'integer', 'date_publication' => 'datetime'];
    }

    public function inscription(): BelongsTo { return $this->belongsTo(Inscription::class, 'id_inscription'); }
    public function lignes(): HasMany { return $this->hasMany(LigneBulletin::class, 'id_bulletin'); }
}

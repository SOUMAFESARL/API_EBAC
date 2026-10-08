<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CorrectionNote extends Model
{
    protected $table = 'corrections_notes';

    protected $guarded = ['id'];

    protected $appends = ['id_seance'];

    protected $hidden = ['note'];

    public function note(): BelongsTo
    {
        return $this->belongsTo(NoteCours::class, 'id_note');
    }

    public function getIdSeanceAttribute(): ?int
    {
        return $this->note?->feuilleNotes?->id_seance;
    }

    protected function casts(): array
    {
        return ['note_initiale' => 'float', 'note_proposee' => 'float', 'note_finale' => 'float'];
    }
}

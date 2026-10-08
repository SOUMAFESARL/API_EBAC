<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NoteCours extends Model
{
    protected $table = 'notes_cours';

    protected $fillable = ['id_etudiant', 'note', 'evaluation'];

    public function feuilleNotes(): BelongsTo
    {
        return $this->belongsTo(FeuilleNotes::class, 'id_feuille_notes');
    }

    public function corrections(): HasMany
    {
        return $this->hasMany(CorrectionNote::class, 'id_note');
    }

    public function etudiant(): BelongsTo
    {
        return $this->belongsTo(Etudiant::class, 'id_etudiant');
    }

    protected function casts(): array
    {
        return ['note' => 'float'];
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NoteCours extends Model
{
    protected $table = 'notes_cours';

    protected $fillable = ['id_etudiant', 'note'];

    public function etudiant(): BelongsTo
    {
        return $this->belongsTo(Etudiant::class, 'id_etudiant');
    }

    protected function casts(): array
    {
        return ['note' => 'float'];
    }
}

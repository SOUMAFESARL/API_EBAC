<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CorrectionNote extends Model
{
    protected $table = 'corrections_notes';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['note_initiale' => 'float', 'note_proposee' => 'float', 'note_finale' => 'float'];
    }
}

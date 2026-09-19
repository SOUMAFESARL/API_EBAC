<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NoteCours extends Model
{
    protected $table = 'notes_cours';

    protected $fillable = ['id_etudiant', 'note'];

    protected function casts(): array
    {
        return ['note' => 'float'];
    }
}

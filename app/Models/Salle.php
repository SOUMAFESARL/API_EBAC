<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Salle extends Model
{
    use SoftDeletes;

    protected $fillable = ['nom', 'code', 'statut', 'created_by', 'updated_by', 'deleted_by'];

    protected $hidden = ['unicite_active'];

    public function createur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function modificateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function suppresseur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }
}

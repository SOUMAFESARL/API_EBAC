<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Une semaine du journal de stage. Validée, elle ne se reprend plus. */
class SemaineStage extends Model
{
    protected $table = 'semaines_stage';

    protected $fillable = ['id_stage', 'numero', 'theme', 'activites', 'heures', 'statut', 'observation', 'statuee_le', 'statuee_par'];

    protected function casts(): array
    {
        return ['numero' => 'integer', 'heures' => 'integer', 'statuee_le' => 'datetime'];
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(Stage::class, 'id_stage');
    }

    public function decideur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'statuee_par');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** Trace d'une validation, d'un renvoi en saisie ou d'une publication. */
#[Fillable(['id_etudiant', 'id_promotion', 'action', 'moyenne', 'par'])]
class HistoriqueBulletinFinDeCycle extends Model
{
    public const VALIDATION = 'validation';

    public const RENVOI = 'renvoi';

    public const PUBLICATION = 'publication';

    public const UPDATED_AT = null;

    protected $table = 'historique_bulletins_fin_de_cycle';
}

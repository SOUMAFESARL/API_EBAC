<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['enseignant_id', 'quota_mo'])]
class QuotaStockage extends Model
{
    public const DEFAUT_MO = 500;

    protected $table = 'quotas_stockage';
}

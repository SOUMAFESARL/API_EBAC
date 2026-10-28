<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['libelle', 'code', 'description', 'statut', 'user_id', 'created_by', 'updated_by', 'deleted_by'])]
class UniteEnseignement extends Model
{
    use SoftDeletes;

    protected $table = 'unites_enseignement';
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'id_compte',
    'code_otp_hash',
    'canal',
    'envoye_le',
    'valide_le',
    'reussi',
    'adresse_ip',
    'created_by',
    'updated_by',
    'deleted_by',
])]
#[Hidden(['code_otp_hash'])]
class ConnexionDeuxFacteurs extends Model
{
    use SoftDeletes;

    protected $table = 'connexions_2fa';

    protected $primaryKey = 'id_tentative';

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'envoye_le' => 'datetime',
            'valide_le' => 'datetime',
            'reussi' => 'boolean',
            'deleted_at' => 'datetime',
        ];
    }

    public function compte(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_compte');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function deleter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }
}

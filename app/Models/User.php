<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Notifications\ReinitialisationMotDePasseNotification;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable([
    'matricule',
    'code',
    'user_code',
    'user_id',
    'nom',
    'prenoms',
    'photo',
    'email',
    'password',
    'id_role',
    'is_active',
    'statut',
    'tentatives_echouees',
    'deux_fa_active',
    'cree_le',
    'derniere_connexion',
    'created_by',
    'created_by_user_id',
    'created_by_user_code',
    'updated_by',
    'deleted_by',
])]
#[Hidden(['password'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    public $timestamps = false;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'deux_fa_active' => 'boolean',
            'tentatives_echouees' => 'integer',
            'cree_le' => 'datetime',
            'derniere_connexion' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'id_role');
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ReinitialisationMotDePasseNotification($token));
    }

    public function connexionsDeuxFacteurs(): HasMany
    {
        return $this->hasMany(ConnexionDeuxFacteurs::class, 'id_users');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(self::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(self::class, 'updated_by');
    }

    public function deleter(): BelongsTo
    {
        return $this->belongsTo(self::class, 'deleted_by');
    }
}

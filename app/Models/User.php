<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Notifications\ReinitialisationMotDePasseNotification;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable([
    'civilite_id',
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
    'prochaine_connexion_sans_otp',
    'cree_le',
    'derniere_connexion',
    'created_by',
    'updated_by',
    'deleted_by',
])]
#[Hidden(['password'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    public $timestamps = false;

    protected function photoUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->photo
            ? route('api.v1.utilisateurs.photo', [
                'compte' => $this->id,
                'v' => hash('sha256', $this->photo),
            ])
            : null);
    }

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
            'prochaine_connexion_sans_otp' => 'boolean',
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

    public function civilite(): BelongsTo
    {
        return $this->belongsTo(Civilite::class, 'civilite_id');
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

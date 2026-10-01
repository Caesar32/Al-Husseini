<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'branch_id',
        'name',
        'email',
        'phone',
        'password',
        'avatar',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function branch(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Avatars are stored on the private "local" disk under avatars/ and served through an
     * authenticated route. Older avatars were written to public/uploads/avatars with names
     * like avatar_{id}_{time}.{ext}; only names matching that image pattern are still shown.
     */
    public function avatarUrl(): string
    {
        $avatar = (string) $this->avatar;

        if (str_starts_with($avatar, 'avatars/')) {
            return route('admin.profile.avatar.show', ['user' => $this->id, 'v' => md5($avatar)]);
        }

        if (preg_match('/^avatar_\d+_\d+\.(jpe?g|png|webp)$/i', $avatar)) {
            return asset('uploads/avatars/' . $avatar);
        }

        return asset('assets/images/users/avatar-1.jpg');
    }
}

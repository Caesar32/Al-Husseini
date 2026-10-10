<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

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
     * First module the user may actually open, by existing permissions (never a route that would
     * answer 403). Used for the logo link and for redirects after login / on "/".
     * Cashiers keep landing on the POS, as before.
     */
    public function homeRouteName(): string
    {
        if ($this->hasRole('cashier') && $this->can('pos.access')) {
            return 'admin.pos.index';
        }

        $landing = [
            'dashboard.view'   => 'admin.dashboard',
            'pos.access'       => 'admin.pos.index',
            'invoices.view'    => 'admin.invoices.index',
            'credit.view'      => 'admin.credit.index',
            'customers.view'   => 'admin.sales.customers',
            'products.view'    => 'admin.sales.products',
            'scrap.view'       => 'admin.scrap.index',
            'purchases.view'   => 'admin.purchases.index',
            'suppliers.view'   => 'admin.suppliers.index',
            'warranties.view'  => 'admin.warranties.index',
            'employees.view'   => 'admin.hr.employees',
            'attendance.view'  => 'admin.hr.attendance',
            'payroll.generate' => 'admin.hr.payroll',
            'reports.hr'       => 'admin.hr.reports',
        ];

        foreach ($landing as $permission => $routeName) {
            if ($this->can($permission)) {
                return $routeName;
            }
        }

        return 'admin.profile'; // every authenticated user may open their own profile
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

    public function devices(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(UserDevice::class);
    }
}

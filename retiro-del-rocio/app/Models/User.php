<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Http\Middleware\TouchLastSeen;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'avatar',
        'password',
        'status',
        'last_login_at',
        'last_login_ip',
        'last_seen_at',
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
            'last_login_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Whether the user may access the admin portal.
     */
    public function isAdmin(): bool
    {
        return $this->status === 'active'
            && $this->hasAnyRole(['super-admin', 'admin', 'manager', 'it-administrator']);
    }

    /**
     * True when this user has hit an authenticated API endpoint within the
     * device heartbeat window — the "online" dot on a staff chat channel.
     * Touched on every authenticated request by {@see TouchLastSeen}.
     */
    public function isRecentlyActive(): bool
    {
        return $this->last_seen_at !== null
            && $this->last_seen_at->gt(now()->subSeconds(config('devices.heartbeat_timeout')));
    }

    /**
     * Active staff who should receive admin notifications.
     */
    public function scopeAdmins($query)
    {
        return $query->where('status', 'active')->role(['super-admin', 'admin', 'manager']);
    }
}

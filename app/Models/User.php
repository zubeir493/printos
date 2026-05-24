<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\UserRole;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\HasDatabaseNotifications;
use Illuminate\Notifications\Notifiable;
use NotificationChannels\WebPush\HasPushSubscriptions;
use NotificationChannels\WebPush\PushSubscription;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasDatabaseNotifications, HasFactory, HasPushSubscriptions, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
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
            'role' => UserRole::class,
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        // A user with no role cannot access any panel
        if (! $this->role instanceof UserRole) {
            return false;
        }

        // Allow all roles to authenticate via the admin panel login.
        // RedirectToCorrectPanel middleware will send non-admin users
        // to their own panel immediately after login.
        if ($panel->getId() === 'admin') {
            return true;
        }

        return $this->role->canAccessPanel($panel);
    }

    public function assignedDesignTasks(): HasMany
    {
        return $this->hasMany(JobOrderTask::class, 'designer_id');
    }

    public function assignedTypistTasks(): HasMany
    {
        return $this->hasMany(JobOrderTask::class, 'typist_id');
    }

    public function warehouses(): BelongsToMany
    {
        return $this->belongsToMany(Warehouse::class)->withTimestamps();
    }

    /**
     * @return Collection<int, PushSubscription>
     */
    public function routeNotificationForWebPush(): Collection
    {
        return $this->pushSubscriptions()->get();
    }
}

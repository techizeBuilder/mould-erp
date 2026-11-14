<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * is_super_admin is deliberately not fillable so it can never be set from a request.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'designation_id',
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

    /** Mirrors the column defaults so new instances behave correctly before a refresh. */
    protected $attributes = [
        'is_super_admin' => false,
        'is_active' => true,
    ];

    /** Per-request cache of the user's permissions, keyed by module. */
    protected ?array $permissionMap = null;

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
            'password' => 'hashed',
            'is_super_admin' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function designation(): BelongsTo
    {
        return $this->belongsTo(Designation::class);
    }

    public function permissions(): HasMany
    {
        return $this->hasMany(UserPermission::class);
    }

    public function scopeSubAdmins(Builder $query): void
    {
        $query->where('is_super_admin', false);
    }

    public function isSuperAdmin(): bool
    {
        return $this->is_super_admin;
    }

    /**
     * Whether the user may perform $action (show|view|create|edit|delete) on $module.
     * A module that is not shown grants nothing, whatever else is ticked.
     */
    public function canModule(string $module, string $action = 'view'): bool
    {
        if ($this->is_super_admin) {
            return true;
        }

        $this->permissionMap ??= $this->permissions()->get()->keyBy('module')->all();
        $permission = $this->permissionMap[$module] ?? null;

        if (! $permission || ! $permission->can_show) {
            return false;
        }

        return (bool) $permission->{'can_'.$action};
    }

    /** Modules from config/modules.php that this user may see. */
    public function visibleModules(): array
    {
        return array_filter(
            config('modules'),
            fn ($module, $key) => $this->canModule($key, 'show'),
            ARRAY_FILTER_USE_BOTH
        );
    }

    public function roleLabel(): string
    {
        if ($this->is_super_admin) {
            return 'Super Admin';
        }

        return $this->designation?->name ?? 'Sub Admin';
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim($this->name));

        return strtoupper(substr($parts[0], 0, 1).(isset($parts[1]) ? substr($parts[1], 0, 1) : ''));
    }
}

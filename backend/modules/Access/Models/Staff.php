<?php

namespace Modules\Access\Models;

use App\Support\Scope;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

/** Someone who logs in to the web dashboard. Platform scope. */
class Staff extends Authenticatable implements FilamentUser
{
    use HasRoles, Notifiable;

    public const SUPER_ADMIN = 'super-admin';

    protected $table = 'staff';

    protected string $guard_name = 'platform';

    protected $fillable = ['organisation_id', 'name', 'email', 'password', 'is_active'];

    protected $attributes = ['is_active' => true];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['password' => 'hashed', 'is_active' => 'boolean'];
    }

    public function scope(): Scope
    {
        return Scope::Platform;
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(self::SUPER_ADMIN);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_active;
    }
}

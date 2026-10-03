<?php

namespace Modules\Access\Models;

use App\Support\Scope;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/** Someone on the mobile app. App scope. One account can be a rider, a driver and a mate at once. */
class Account extends Authenticatable
{
    use HasApiTokens, HasRoles;

    protected string $guard_name = 'app';

    protected $fillable = ['phone', 'name', 'preferred_channel', 'is_active', 'phone_verified_at', 'last_login_at'];

    protected $attributes = ['is_active' => true, 'preferred_channel' => 'sms'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'phone_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    public function scope(): Scope
    {
        return Scope::App;
    }
}

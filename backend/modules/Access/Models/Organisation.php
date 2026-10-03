<?php

namespace Modules\Access\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organisation extends Model
{
    protected $fillable = ['name', 'slug', 'is_platform_owner'];

    protected function casts(): array
    {
        return ['is_platform_owner' => 'boolean'];
    }

    public static function platformOwner(): self
    {
        return static::query()->where('is_platform_owner', true)->firstOrFail();
    }

    public function staff(): HasMany
    {
        return $this->hasMany(Staff::class);
    }
}

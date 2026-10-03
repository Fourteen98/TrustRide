<?php

namespace Modules\Drivers\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class VettingItem extends Model
{
    public const DRIVER = 'driver';

    public const VEHICLE = 'vehicle';

    protected $fillable = ['key', 'label', 'applies_to', 'is_required', 'is_active', 'position'];

    protected $attributes = ['is_required' => true, 'is_active' => true];

    protected function casts(): array
    {
        return ['is_required' => 'boolean', 'is_active' => 'boolean'];
    }

    public function scopeFor(Builder $query, Driver|Vehicle $subject): Builder
    {
        return $query->where('is_active', true)
            ->where('applies_to', $subject instanceof Driver ? self::DRIVER : self::VEHICLE)
            ->orderBy('position');
    }
}

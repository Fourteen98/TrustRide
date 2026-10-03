<?php

namespace Modules\Subscriptions\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Subscriptions\Enums\NoShowPolicy;

class Plan extends Model
{
    protected $fillable = ['name', 'duration_days', 'price', 'trips_per_day', 'no_show_policy', 'notice_cutoff_minutes', 'is_active'];

    protected $attributes = [
        'trips_per_day' => 1,
        'no_show_policy' => 'credit_with_notice',
        'notice_cutoff_minutes' => 720,
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'no_show_policy' => NoShowPolicy::class,
            'is_active' => 'boolean',
            'price' => 'integer',
            'duration_days' => 'integer',
            'trips_per_day' => 'integer',
            'notice_cutoff_minutes' => 'integer',
        ];
    }
}

<?php

namespace Modules\Deliveries\Models;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Access\Concerns\BelongsToOrganisation;

/** An area one driver covers in one run, with the time orders must be in by to go out that day. */
class Zone extends Model
{
    use BelongsToOrganisation;

    protected $fillable = ['organisation_id', 'name', 'covers', 'cutoff_time', 'promise', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class);
    }

    /** The day an order placed at this moment goes out: today if it is in before the cut-off, otherwise tomorrow. */
    public function deliveryDateFor(CarbonInterface $at): CarbonImmutable
    {
        $at = CarbonImmutable::instance($at);

        return $at->format('H:i') < $this->cutoff_time ? $at->startOfDay() : $at->addDay()->startOfDay();
    }

    /** What the customer is told: "today by 6pm", "tomorrow by 6pm", "on Mon 10 Nov by 6pm". */
    public function promiseFor(CarbonInterface $date): string
    {
        $day = match (true) {
            $date->isToday() => 'today',
            $date->isTomorrow() => 'tomorrow',
            default => 'on '.$date->format('D j M'),
        };

        return "{$day} {$this->promise}";
    }
}

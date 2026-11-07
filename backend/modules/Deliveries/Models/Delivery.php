<?php

namespace Modules\Deliveries\Models;

use App\Support\Casts\DateOnly;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Deliveries\Enums\DeliveryStatus;

/** One parcel, from one business to one customer. The customer has no account. */
class Delivery extends Model
{
    use HasUlids;

    protected $fillable = [
        'business_id', 'zone_id', 'delivery_date', 'reference', 'customer_name', 'customer_phone', 'address', 'landmark', 'notes',
        'status', 'delivery_run_id', 'position', 'code_hash', 'code_attempts', 'picked_up_at', 'delivered_at', 'delivered_note',
        'failed_at', 'failure_reason', 'cancelled_at', 'customer_data_removed_at', 'created_by',
    ];

    protected $attributes = ['status' => 'pending', 'code_attempts' => 0];

    protected $hidden = ['code_hash'];

    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    protected function casts(): array
    {
        return [
            'delivery_date' => DateOnly::class,
            'status' => DeliveryStatus::class,
            'code_attempts' => 'integer',
            'position' => 'integer',
            'picked_up_at' => 'datetime',
            'delivered_at' => 'datetime',
            'failed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'customer_data_removed_at' => 'datetime',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(DeliveryRun::class, 'delivery_run_id');
    }

    /**
     * How a delivery code is kept. A keyed hash, not a password hash: a four-digit code can't be protected by slowness,
     * so the limit on wrong tries is what protects it. This keeps the code out of the database and the logs.
     */
    public static function hashCode(string $code): string
    {
        return hash_hmac('sha256', $code, (string) config('app.key'));
    }

    /** The page the customer follows it on. No login; the link can't be guessed. */
    public function trackingUrl(): string
    {
        return url("/o/{$this->public_id}");
    }
}

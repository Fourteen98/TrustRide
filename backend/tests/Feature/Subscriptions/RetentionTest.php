<?php

use Illuminate\Support\Carbon;
use Modules\Access\Models\Account;
use Modules\Access\Models\LoginCode;
use Modules\Messaging\Models\EventLog;
use Modules\Messaging\Models\Message;
use Modules\Riders\Actions\RejectApplication;
use Modules\Riders\Enums\RejectionReason;
use Modules\Riders\Models\DataDeletionRequest;
use Modules\Riders\Models\Rider;
use Modules\Subscriptions\Actions\ActivateSubscription;
use Modules\Subscriptions\Actions\ApproveApplicationWithSeats;
use Modules\Subscriptions\Actions\RunDailyUpkeep;
use Modules\Subscriptions\Models\Plan;
use Modules\Support\Actions\MarkIncident;
use Modules\Support\Actions\OpenCase;
use Modules\Support\Actions\ResolveCase;
use Modules\Support\Enums\Severity;
use Modules\Support\Models\SupportCase;

beforeEach(function () {
    syncAccess();
    Carbon::setTestNow('2026-10-05 09:00:00');
    $this->network = network();
    $this->run = run($this->network);
    $this->operator = staff('operator');
});

/** A rider who paid for two weeks and then never renewed. */
function lapsedRider(string $name): Rider
{
    $application = application(test()->network, $name);
    app(ApproveApplicationWithSeats::class)->handle($application, [test()->run->id], test()->operator);
    app(ActivateSubscription::class)->handle($application->rider, Plan::query()->where('duration_days', 14)->first(), test()->operator);

    return $application->rider;
}

it('deletes nobody while no retention period is set', function () {
    lapsedRider('Ama Owusu');
    $this->travel(400)->days();
    app(RunDailyUpkeep::class)->handle();

    $this->artisan('subscriptions:retention')->expectsOutputToContain('nobody is deleted')->assertSuccessful();

    expect(Rider::count())->toBe(1);
});

it('deletes a rider once she has been gone longer than the period, and nobody with anything live', function () {
    config(['subscriptions.rider_retention_days' => 90]);

    $gone = lapsedRider('Ama Owusu');
    $rejected = application($this->network, 'Esi Boateng');
    app(RejectApplication::class)->handle($rejected, $this->operator, RejectionReason::cases()[0]);
    $gonePhone = $gone->account->phone;

    // 14 days of riding, the seat is given up after the grace period, then 90 more days.
    foreach ([15, 5, 89] as $days) {
        $this->travel($days)->days();
        app(RunDailyUpkeep::class)->handle();
    }

    // These arrive later and each have something live, however long Aveo has known them.
    $riding = lapsedRider('Abena Darko');
    $waiting = application($this->network, 'Yaa Mensah');

    $this->artisan('subscriptions:retention --dry-run')->expectsOutputToContain('Nobody was deleted')->assertSuccessful();
    expect(Rider::count())->toBe(4);

    $this->travel(2)->days();
    $this->artisan('subscriptions:retention --dry-run')->expectsOutputToContain('2 riders are past')->assertSuccessful();
    expect(Rider::count())->toBe(4);

    $this->artisan('subscriptions:retention')->expectsOutputToContain('Deleted 2 riders')->assertSuccessful();

    expect(Rider::query()->pluck('name')->sort()->values()->all())->toBe(['Abena Darko', 'Yaa Mensah'])
        ->and(Account::query()->where('phone', $gonePhone)->exists())->toBeFalse()
        ->and(Message::query()->where('recipient_phone', $gonePhone)->exists())->toBeFalse();

    // There is a record that it happened and why, with nobody's name on it.
    $record = DataDeletionRequest::query()->where('reason', 'retention')->get();
    expect($record)->toHaveCount(2)
        ->and($record[0])->status->toBe(DataDeletionRequest::COMPLETED)->completed_by->toBeNull()->rider_id->toBeNull();

    // Running it again finds nobody.
    $this->artisan('subscriptions:retention')->expectsOutputToContain('Deleted 0 riders')->assertSuccessful();
});

it('clears out old login codes, logs and resolved cases, and keeps incidents', function () {
    LoginCode::query()->create(['phone' => '+233240000001', 'code_hash' => 'x', 'expires_at' => now()->addMinutes(10)]);
    lapsedRider('Ama Owusu'); // fires events, so the logs have entries

    $ordinary = app(OpenCase::class)->handle($this->operator, ['name' => 'Kofi', 'phone' => '0209998888', 'category' => 'other', 'message' => 'A question']);
    $incident = app(OpenCase::class)->handle($this->operator, ['name' => 'Efua', 'phone' => '0209997777', 'category' => 'crew', 'message' => 'The mate shouted']);
    app(MarkIncident::class)->handle($incident, $this->operator, Severity::Medium);
    app(ResolveCase::class)->handle($ordinary, $this->operator, 'Answered.');
    app(ResolveCase::class)->handle($incident, $this->operator, 'Mate warned.');

    expect(EventLog::count())->toBeGreaterThan(0)->and(Message::count())->toBeGreaterThan(0);

    $this->travel(2)->days();
    $this->artisan('access:prune')->assertSuccessful();
    expect(LoginCode::count())->toBe(0);

    $this->travel(179)->days();
    $this->artisan('messaging:prune')->assertSuccessful();
    expect(EventLog::count())->toBe(0)->and(Message::count())->toBe(0);

    $this->travel(200)->days();
    $this->artisan('support:prune')->assertSuccessful();
    expect(SupportCase::query()->pluck('name')->all())->toBe(['Efua']);
});

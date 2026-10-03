<?php

use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Modules\Access\Models\Account;
use Modules\Access\Models\LoginCode;
use Modules\Access\Models\Staff;
use Modules\Messaging\Models\EventLog;
use Modules\Riders\Actions\EraseRider;
use Modules\Riders\Actions\RequestDataDeletion;
use Modules\Riders\Filament\Resources\DeletionRequestResource;
use Modules\Riders\Filament\Resources\DeletionRequestResource\Pages\ManageDeletionRequests;
use Modules\Riders\Models\DataDeletionRequest;
use Modules\Riders\Models\Rider;
use Modules\Riders\Models\RiderApplication;
use Modules\Subscriptions\Actions\ActivateSubscription;
use Modules\Subscriptions\Actions\ApproveApplicationWithSeats;
use Modules\Subscriptions\Actions\GiveAbsenceNotice;
use Modules\Subscriptions\Models\AbsenceNotice;
use Modules\Subscriptions\Models\Plan;
use Modules\Subscriptions\Models\Seat;
use Modules\Subscriptions\Models\Subscription;

beforeEach(function () {
    syncAccess();
    Carbon::setTestNow('2026-10-05 09:00:00');

    $this->network = network();
    $this->run = run($this->network);
    $this->admin = staff(Staff::SUPER_ADMIN);
});

/** A rider with everything: an application, an active seat, a subscription and a notice. */
function fullRider(string $name = 'Ama Owusu'): Rider
{
    $application = application(test()->network, $name);
    app(ApproveApplicationWithSeats::class)->handle($application, [test()->run->id], test()->admin);
    app(ActivateSubscription::class)->handle($application->rider, Plan::where('name', '1 month')->first(), test()->admin);
    app(GiveAbsenceNotice::class)->handle($application->rider, Carbon::parse('2026-10-08'));

    return $application->rider;
}

it('records a request without deleting anything, and asking twice makes one request', function () {
    $rider = fullRider();

    app(RequestDataDeletion::class)->handle($rider);
    app(RequestDataDeletion::class)->handle($rider);

    expect(DataDeletionRequest::count())->toBe(1)->and(Rider::count())->toBe(1)->and(Subscription::count())->toBe(1);
});

it('erases everything about her and frees her seat', function () {
    $rider = fullRider();
    $keep = fullRider('Esi Boateng');
    $phone = $rider->account->phone;
    $accountId = $rider->account_id;
    $rider->account->createToken('phone');
    LoginCode::create(['phone' => $phone, 'code_hash' => 'x', 'expires_at' => now()->addMinutes(10)]);

    expect(EventLog::whereRaw('CAST(recipients AS TEXT) LIKE ?', ["%{$phone}%"])->count())->toBeGreaterThan(0)
        ->and($this->run->seatsFree())->toBe(10);

    $request = app(RequestDataDeletion::class)->handle($rider);
    app(EraseRider::class)->handle($request, $this->admin);

    expect(Rider::find($rider->id))->toBeNull()
        ->and(Account::find($accountId))->toBeNull()
        ->and(RiderApplication::where('rider_id', $rider->id)->count())->toBe(0)
        ->and(Seat::where('rider_id', $rider->id)->count())->toBe(0)
        ->and(Subscription::where('rider_id', $rider->id)->count())->toBe(0)
        ->and(AbsenceNotice::where('rider_id', $rider->id)->count())->toBe(0)
        ->and(LoginCode::where('phone', $phone)->count())->toBe(0)
        ->and(DB::table('personal_access_tokens')->where('tokenable_id', $accountId)->count())->toBe(0)
        ->and(DB::table('model_has_roles')->where('model_id', $accountId)->where('model_type', Account::class)->count())->toBe(0)
        ->and($this->run->seatsFree())->toBe(11);

    // Nothing left in the event log with her number, including the erasure event itself.
    expect(EventLog::whereRaw('CAST(recipients AS TEXT) LIKE ?', ["%{$phone}%"])->orWhereRaw('CAST(payload AS TEXT) LIKE ?', ["%{$phone}%"])->count())->toBe(0)
        ->and(EventLog::where('name', 'rider.erased')->count())->toBe(1);

    // The other rider is untouched, and the request row holds nothing about the erased one.
    expect(Rider::find($keep->id))->not->toBeNull()
        ->and(Subscription::where('rider_id', $keep->id)->count())->toBe(1)
        ->and($request->fresh()->status)->toBe(DataDeletionRequest::COMPLETED)
        ->and($request->fresh()->rider_id)->toBeNull()
        ->and($request->fresh()->completed_by)->toBe($this->admin->id);

    expect(fn () => app(EraseRider::class)->handle($request->fresh(), $this->admin))->toThrow(DomainException::class);
});

it('keeps the account of a rider who is also a mate, and only removes the rider side', function () {
    $rider = fullRider();
    $rider->account->assignRole('mate');

    app(EraseRider::class)->handle(app(RequestDataDeletion::class)->handle($rider), $this->admin);

    $account = Account::find($rider->account_id);

    expect($account)->not->toBeNull()
        ->and($account->roles->pluck('name')->all())->toBe(['mate'])
        ->and(Rider::count())->toBe(0);
});

it('lets her ask and take it back through the app', function () {
    $rider = fullRider();
    $this->withToken($rider->account->createToken('test')->plainTextToken);

    $this->postJson('/api/rider/v1/data-deletion')->assertStatus(202);
    $this->getJson('/api/rider/v1/home')->assertJsonPath('deletion_requested', true);

    $this->deleteJson('/api/rider/v1/data-deletion')->assertNoContent();
    $this->getJson('/api/rider/v1/home')->assertJsonPath('deletion_requested', false);

    expect(Rider::count())->toBe(1);
});

it('only lets staff with the erase permission confirm it in the console', function () {
    $rider = fullRider();
    $request = app(RequestDataDeletion::class)->handle($rider);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->actingAs(staff('operator'), 'platform');
    expect(DeletionRequestResource::canViewAny())->toBeFalse();

    $this->actingAs($this->admin, 'platform');
    expect(DeletionRequestResource::canViewAny())->toBeTrue();
    $this->get('/admin/deletion-requests')->assertOk();

    Livewire::test(ManageDeletionRequests::class)->callTableAction('erase', $request)->assertHasNoTableActionErrors();

    expect(Rider::count())->toBe(0);
});

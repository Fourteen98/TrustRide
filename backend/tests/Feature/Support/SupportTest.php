<?php

use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Modules\Access\Models\Account;
use Modules\Access\Models\Staff;
use Modules\Messaging\Models\Message;
use Modules\Riders\Actions\EraseRider;
use Modules\Riders\Actions\RequestDataDeletion;
use Modules\Riders\Actions\SubmitApplication;
use Modules\Subscriptions\Actions\ApproveApplicationWithSeats;
use Modules\Support\Actions\AddNote;
use Modules\Support\Actions\AssignCase;
use Modules\Support\Actions\MarkIncident;
use Modules\Support\Actions\OpenCase;
use Modules\Support\Actions\ReopenCase;
use Modules\Support\Actions\ReplyToCase;
use Modules\Support\Actions\ResolveCase;
use Modules\Support\Enums\CaseStatus;
use Modules\Support\Enums\EntryType;
use Modules\Support\Enums\Severity;
use Modules\Support\Filament\Resources\CaseResource\Pages\ManageCases;
use Modules\Support\Filament\Resources\CaseResource\Pages\ViewCase;
use Modules\Support\Models\SupportCase;

beforeEach(function () {
    syncAccess();
    Carbon::setTestNow('2026-10-05 09:00:00');

    $this->network = network();
    $this->run = run($this->network);
    $this->account = Account::create(['phone' => '+233241234567'])->assignRole('rider');
    $this->withToken($this->account->createToken('test')->plainTextToken);
    $this->operator = staff('operator');
});

function seatedRider()
{
    $application = app(SubmitApplication::class)->handle(test()->account, [
        'name' => 'Ama Owusu', 'route_id' => test()->network['route']->id,
        'stop_id' => test()->network['stops'][1]->id, 'morning_time' => '06:30',
    ]);
    app(ApproveApplicationWithSeats::class)->handle($application, [test()->run->id], test()->operator);

    return $application->rider;
}

function write(string $message, ?string $category = 'van_missing')
{
    return test()->postJson('/api/support/v1/messages', array_filter(['message' => $message, 'category' => $category]));
}

it('starts with no case and offers what it can be about', function () {
    $this->getJson('/api/support/v1/case')->assertOk()
        ->assertJsonPath('case', null)
        ->assertJsonPath('categories.1', ['key' => 'van_missing', 'label' => 'The van didn\'t come'])
        ->assertJsonCount(7, 'categories');
});

it('opens a case from her first message, with what she rides, and adds later messages to it', function () {
    seatedRider();

    write('I waited 30 minutes at Old Barrier.')->assertCreated()
        ->assertJsonPath('case.status', 'open')
        ->assertJsonPath('case.about', 'The van didn\'t come')
        ->assertJsonPath('case.taken', false)
        ->assertJsonPath('case.messages.0.from', 'you');

    write('Still nothing.', 'payment')->assertCreated()->assertJsonCount(2, 'case.messages');

    $case = SupportCase::query()->sole();
    expect($case)->name->toBe('Ama Owusu')->phone->toBe('+233241234567')->role->toBe('rider')->is_incident->toBeFalse()
        ->and($case->category->value)->toBe('van_missing') // the second category is ignored
        ->and($case->context)->toContain('Bortianor to Circle')->toContain('Old Barrier')->toContain('Kwame Mensah');
});

it('works for someone with no seat yet, and refuses an empty message', function () {
    write('How do I apply?', null)->assertCreated()->assertJsonPath('case.about', 'Something else');
    write('   ')->assertStatus(422);
    write('x', 'made_up')->assertStatus(422);
});

it('treats "I don\'t feel safe" as an incident at once and texts the operator on call', function () {
    config(['support.alert_phone' => '+233200000001']);
    seatedRider();

    write('The driver is speeding and shouting.', 'safety')->assertCreated();

    $case = SupportCase::query()->sole();
    expect($case)->is_incident->toBeTrue()->severity->toBe(Severity::High)
        ->and(Message::query()->where('recipient_phone', '+233200000001')->sole()->body)
        ->toContain('Ama Owusu')->toContain('I don\'t feel safe')->toContain('speeding');
});

it('shows her the reply in the app and sends it to her phone, but never a staff note', function () {
    seatedRider();
    write('The van never came.');
    $case = SupportCase::query()->sole();

    app(AddNote::class)->handle($case, $this->operator, 'Driver says he had a flat tyre.');
    app(ReplyToCase::class)->handle($case, $this->operator, 'Sorry Ama. The van broke down. We have added a day to your subscription.');

    $this->getJson('/api/support/v1/case')->assertOk()
        ->assertJsonPath('case.taken', true)
        ->assertJsonCount(2, 'case.messages')
        ->assertJsonPath('case.messages.1.from', 'aveo')
        ->assertJsonMissing(['body' => 'Driver says he had a flat tyre.']);

    expect($case->fresh()->assigned_to)->toBe($this->operator->id) // replying took it
        ->and(Message::query()->where('recipient_phone', '+233241234567')->where('body', 'like', 'Aveo: Sorry Ama%')->count())->toBe(1);
});

it('keeps a resolved case visible for a while, then her next message opens a new one', function () {
    write('The van never came.');
    $case = SupportCase::query()->sole();

    expect(fn () => app(ResolveCase::class)->handle($case, $this->operator, ' '))->toThrow(DomainException::class);
    app(ResolveCase::class)->handle($case, $this->operator, 'Credited one day.');

    $this->getJson('/api/support/v1/case')->assertJsonPath('case.status', 'resolved');
    expect(fn () => app(ReplyToCase::class)->handle($case->fresh(), $this->operator, 'Hello'))->toThrow(DomainException::class, 'Reopen');

    write('It happened again.', 'van_late')->assertCreated()->assertJsonCount(1, 'case.messages');
    expect(SupportCase::count())->toBe(2);
    $this->getJson('/api/support/v1/case')->assertJsonPath('case.status', 'open')->assertJsonPath('case.about', 'The van is late');

    // The old one can't be reopened while a newer one is open.
    expect(fn () => app(ReopenCase::class)->handle($case->fresh(), $this->operator))->toThrow(DomainException::class, 'newer');

    $this->travel(8)->days();
    app(ResolveCase::class)->handle(SupportCase::query()->latest('id')->first(), $this->operator, 'Done.');
    $this->travel(8)->days();
    $this->getJson('/api/support/v1/case')->assertJsonPath('case', null);
});

it('records a phone call as a case, tied to her account when the number has one', function () {
    $case = app(OpenCase::class)->handle($this->operator, [
        'name' => 'Ama Owusu', 'phone' => '0241234567', 'category' => 'payment', 'message' => 'Charged twice.',
    ]);

    expect($case)->account_id->toBe($this->account->id)->source->toBe('phone')->assigned_to->toBe($this->operator->id);
    $this->getJson('/api/support/v1/case')->assertJsonPath('case.about', 'A payment problem');

    expect(fn () => app(OpenCase::class)->handle($this->operator, ['name' => 'Ama', 'phone' => '0241234567', 'category' => 'other', 'message' => 'x']))
        ->toThrow(DomainException::class, 'already has an open case')
        ->and(fn () => app(OpenCase::class)->handle($this->operator, ['name' => 'X', 'phone' => '123', 'category' => 'other', 'message' => 'x']))
        ->toThrow(DomainException::class);

    $stranger = app(OpenCase::class)->handle($this->operator, ['name' => 'Kofi', 'phone' => '0209998888', 'category' => 'other', 'message' => 'Do you go to Kasoa?']);
    expect($stranger->account_id)->toBeNull();
});

it('keeps a timeline of who did what', function () {
    write('The mate was rude.', 'crew');
    $case = SupportCase::query()->sole();
    $lead = staff('operator');
    $lead->update(['name' => 'Esi Lead']);

    app(AssignCase::class)->handle($case, $this->operator, $this->operator);
    app(AssignCase::class)->handle($case, $lead, $this->operator);
    app(MarkIncident::class)->handle($case, $lead, Severity::Medium);
    app(MarkIncident::class)->handle($case, $lead, Severity::High);
    app(ResolveCase::class)->handle($case, $lead, 'Spoke to the driver. Mate warned.');

    expect($case->entries()->pluck('type')->all())->toBe([
        EntryType::Message, EntryType::Assigned, EntryType::Assigned, EntryType::Incident, EntryType::Incident, EntryType::Resolved,
    ])->and($case->entries()->skip(2)->first()->body)->toBe('Test Staff handed it to Esi Lead.')
        ->and($case->fresh())->severity->toBe(Severity::High)->status->toBe(CaseStatus::Resolved);
});

it('deletes her cases when her data is erased, but keeps an incident without her name', function () {
    $rider = seatedRider();
    write('The van never came.');
    app(ResolveCase::class)->handle(SupportCase::query()->sole(), $this->operator, 'Credited.');
    write('The driver threatened me.', 'safety');

    app(EraseRider::class)->handle(app(RequestDataDeletion::class)->handle($rider), staff(Staff::SUPER_ADMIN));

    $left = SupportCase::query()->sole();
    expect($left)->is_incident->toBeTrue()->name->toBeNull()->phone->toBeNull()->account_id->toBeNull()
        ->and($left->entries()->count())->toBeGreaterThan(0);
});

it('needs the app permission', function () {
    $this->account->removeRole('rider');

    $this->getJson('/api/support/v1/case')->assertForbidden();
});

it('lets an operator work the queue in the console', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    seatedRider();
    write('The van never came.');
    $case = SupportCase::query()->sole();
    $this->actingAs($this->operator, 'platform');

    $this->get('/admin/support')->assertOk()->assertSee('Ama Owusu');
    $this->get("/admin/support/{$case->id}")->assertOk()->assertSee('The van never came.')->assertSee('Old Barrier');

    Livewire::test(ManageCases::class)->callTableAction('take', $case);
    expect($case->fresh()->assigned_to)->toBe($this->operator->id);

    Livewire::test(ManageCases::class)
        ->callAction('record', ['name' => 'Kofi', 'phone' => '0209998888', 'source' => 'whatsapp', 'category' => 'other', 'message' => 'Do you go to Kasoa?'])
        ->assertHasNoActionErrors();
    expect(SupportCase::query()->where('source', 'whatsapp')->count())->toBe(1);

    Livewire::test(ViewCase::class, ['record' => $case->id])
        ->callAction('reply', ['body' => 'Sorry Ama, we are checking.'])
        ->callAction('note', ['body' => 'Called the driver.'])
        ->callAction('incident', ['severity' => 'medium'])
        ->callAction('resolve', ['resolution' => 'Credited a day.'])
        ->assertHasNoActionErrors()
        ->assertSee('Credited a day.')
        ->assertActionHidden('reply')
        ->callAction('reopen');

    expect($case->fresh())->status->toBe(CaseStatus::Open)->is_incident->toBeTrue()
        ->and($case->entries()->count())->toBe(7);
});

it('hides the buttons from staff who may only look', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    write('Hello');
    $case = SupportCase::query()->sole();

    $viewer = staff();
    $viewer->givePermissionTo('support.queue.view');
    $this->actingAs($viewer, 'platform');

    $this->get('/admin/support')->assertOk();
    Livewire::test(ViewCase::class, ['record' => $case->id])->assertActionHidden('reply')->assertActionHidden('resolve')->assertActionHidden('incident');
    Livewire::test(ManageCases::class)->assertTableActionHidden('take', $case)->assertActionHidden('record');
});

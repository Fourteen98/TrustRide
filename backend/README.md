# TrustRide backend

One Laravel app, one Postgres database, one deploy. The business is split into modules. The three sides (admin dashboard, rider screens, driver and mate screens) are thin entry points on top.

The full plan is in `docs/build-plan.pdf`. This repository covers Phase 1 of it, tasks F2 to F7.

## Run it

You need PHP 8.3 or newer, Composer and Postgres.

```sh
composer install
cp .env.example .env
php artisan key:generate
createdb trustride            # or create the database in your Postgres app
php artisan migrate --seed    # tables, permissions, roles, first super admin
php artisan serve             # http://localhost:8000
php artisan queue:work        # in a second terminal. Events are handled on the queue.
```

Then open http://localhost:8000/admin and log in with `ADMIN_EMAIL` and `ADMIN_PASSWORD` from `.env`. Out of the box those are `admin@trustride.test` and `change-me-now`. Change both before seeding anywhere real.

Keep `php artisan queue:work` running in a second terminal while you develop. Events are handled on the queue, so without it nothing reaches the event log.

Day to day, after pulling changes:

```sh
composer install
php artisan migrate
php artisan access:sync       # stores any new permissions the modules declare
php artisan serve
```

Run the tests with `composer test` or `./vendor/bin/pest`. They use an in-memory SQLite database, so they need no setup.

### Demo data

To see the console with something in it:

```sh
php artisan db:seed --class=DemoSeeder
```

That adds one made-up route, a vetted driver with a mate and a vehicle, a 06:30 run, three riders riding, one approved but unpaid, and two applications waiting. It uses the same Actions the console uses. It refuses to run in production. `php artisan migrate:fresh --seed` wipes it.

### The daily job

`subscriptions:upkeep` runs every night at 00:10. It tells riders whose subscription is about to end, expires the ones that have ended, and frees seats nobody is paying for. For it to run by itself, keep this going in a third terminal while developing (on a server, cron does it):

```sh
php artisan schedule:work
```

Or run it by hand with `php artisan subscriptions:upkeep`.

## API docs

The docs are generated from the code by [Scramble](https://scramble.dedoc.co). Nobody writes them by hand, so they can't fall behind the API.

- http://localhost:8000/docs/api is the page to read. It has a "Send API request" button to try each endpoint.
- http://localhost:8000/docs/api.json is the OpenAPI file. Use it to generate the mobile app's API client or import it into Postman.

Endpoints are grouped by side: Login, Rider, Driver and mate. On your own machine the docs are open. Anywhere else, only staff logged in to the dashboard can see them.

To get good docs for a new endpoint:

1. Use a controller method, not a closure in the route file.
2. Validate the request. The rules become the documented request body.
3. Return a `JsonResource`. Its fields become the documented reply.
4. Put a short comment above the method. The first line is the title, the rest is the description. Add `@unauthenticated` if it needs no token.

`php artisan scramble:export` writes the OpenAPI file to disk.

## Layout

```
modules/            the business, split by domain
  Access/           staff, mobile accounts, roles, permissions, groups, organisations
  Network/          stops, routes
  Riders/
  Drivers/
  Subscriptions/
  Trips/
  Support/
  Payments/         knows no other module
  Messaging/        knows no other module

app/                the sides, split by who is using it
  Admin/            the Filament panel at /admin, platform guard
  Api/Auth/         login for the mobile app
  Api/ApiDocsServiceProvider.php   API docs setup
  Api/Rider/        /api/rider/v1
  Api/Driver/       /api/driver/v1
  Support/          the few shared pieces every module may use
```

Inside a module:

```
Actions/              the module's front door. The only way anyone changes its data.
Models/
Enums/                its statuses and choices
Events/               what the module tells the others
Queries/              read-only lookups that are too big for a model method
Database/Migrations/  the module's own tables
Filament/Resources/   its admin screens. The panel finds them by itself.
Filament/Pages/       admin pages that are not a plain list, like Today's riders
Resources/views/      Blade views for those pages
Providers/            declares its permissions and what it listens to
config.php            its settings, read as config('<module>.key')
```

`docs/domain-model.pdf` says what every table holds and walks the path from a rider signing up to a driver being paid.

## The rules

1. **Read anything.** A module can read another module's tables through relationships and joins.
2. **Write only your own tables.** To change another module's data, call that module's Action.
3. **Say what happened.** Fire an event that extends `App\Support\BaseDomainEvent`. Messaging hears every one.
4. **Sides hold no business rules.** A controller checks the permission, calls an Action, and shapes the reply.
5. **A module says no by throwing a `DomainException`.** The API turns it into a 422 with the message.

`tests/Arch/ModulesTest.php` holds the table of which module may use which. A forbidden import fails the build.

## Access

- **Two account tables.** `staff` log in on the web with email and password (guard `platform`). `accounts` log in on mobile with a phone number and SMS code (guard `app`, Sanctum tokens).
- **Two scopes.** Every role, permission and group belongs to `platform` or `app`. They can't be mixed.
- **Modules declare permissions** in their service provider, bundled into default groups. `php artisan access:sync` stores them. It only adds, so run it on every deploy.
- **A role keeps its groups.** A permission added to a group later reaches every role that has the group. Change a role only through `SetRoleAccess`, and a group only through `SetGroupPermissions`.
- **One account, several roles.** A mate can also be a rider. Login returns the roles and the full permission list, and the app builds its home screen from that.
- **Organisations.** `staff.organisation_id` exists from day one and TrustRide is organisation 1. Drivers, vehicles and routes get the same column when their tables are written. Partner companies are not built yet.

### Mobile login

```
POST /api/auth/code      { "phone": "0241234567" }                    -> 202
POST /api/auth/verify    { "phone": "0241234567", "code": "123456" }  -> { token, account }
GET  /api/auth/me        Authorization: Bearer <token>                -> { account }
POST /api/auth/logout
```

With `MESSAGING_SMS_DRIVER=log` (the default) the code is written to `storage/logs/laravel.log`. With `hubtel` it is sent as a real SMS.

A phone number nobody has seen becomes a new account with the rider role. Staff add drivers and mates by phone number under Access, Mobile accounts.

## The rider API

Everything under `/api/rider/v1` works on the logged-in account's own record. There is no rider id in any URL, so one rider can't reach another's data. `/docs/api` has the full detail.

```
GET    /api/rider/v1/home                 everything her home screen shows, in one call
GET    /api/rider/v1/routes               routes she can apply for, with stops and the times a van runs
POST   /api/rider/v1/application          apply
DELETE /api/rider/v1/application          withdraw the one that is waiting
GET    /api/rider/v1/plans                plans on sale
GET    /api/rider/v1/absences/preview     what happens if she won't ride that day. Saves nothing.
POST   /api/rider/v1/absences             say she won't ride on a day
POST   /api/rider/v1/data-deletion        ask for her data to be deleted
DELETE /api/rider/v1/data-deletion        take that back
```

Set `SUPPORT_PHONE` and `SUPPORT_WHATSAPP` in `.env`. The app shows them when a payment needs a person to sort it out.

### Deleting a rider's data

1. She asks in the app. Nothing is deleted yet.
2. Staff with the `riders.data.erase` permission open Riders, Deletion requests, and confirm. Operators don't have this permission by default.
3. `EraseRider` deletes the rider row. The database removes what hangs off it: her applications, seats, subscriptions and absence notices. Her mobile account goes too, unless she is also a driver or a mate.
4. The `rider.erased` event tells other modules to remove copies they hold under her phone number. Messaging scrubs the event log.

Any new table that stores a rider's details must either hang off `riders` with a cascading foreign key, or listen for `App\Support\Contracts\ErasesPersonalData`.

## Messaging

Modules never send messages. They fire events, and Messaging decides who is told.

1. A module fires an event, such as `subscription.activated`, with a payload and a list of recipients.
2. Messaging logs it, then looks up the active rules for that event name.
3. For each rule and each recipient the rule is for, it fills the rule's words from the payload and queues one message.
4. Delivery tries the person's preferred channel first, then the others in `fallback_order`. Every try is written to the message.

A rule is a row: event, audience, channel, words. Edit them under Messaging, Message rules. The form lists the placeholders the event carries, such as `{car_number}` and `{stop}`. Eight rules ship switched on. Review their wording before a real provider is connected, because from then on each one costs money.

**Nothing is really sent yet.** The SMS channel uses the `log` driver, so messages land in `storage/logs/laravel.log`. WhatsApp and push are not set up, so delivery falls back to SMS. Login codes go through the same SMS channel.

To connect a real provider:

1. Write a class in `modules/Messaging/Channels` that implements `Channel`. `send()` returns when the provider accepts the message and throws when it doesn't.
2. Add its name to the `match` in `ChannelManager::channel()`.
3. Set `MESSAGING_SMS_DRIVER` in `.env` to that name.

To message every rider on a run, use "Message riders" under Runs and capacity. That fires `run.announcement`, which has its own rule.

A message is only created once per event, rule and person, so a listener that runs twice never sends twice. The queue worker must be running for anything to go out.

## Payments

A rider pays for a plan in the app with mobile money. Fares never come through here.

```
app            POST /api/rider/v1/purchase {plan_id, network, phone?}
Subscriptions  StartPlanPurchase: checks she can be activated, creates a plan_purchases row
Payments       StartPayment: creates a payments row with our reference (TR-...), asks the gateway to prompt her phone
               ... she approves with her PIN ...
Hubtel         POST /api/webhooks/hubtel/payment          (or payments:reconcile asks Hubtel, if no callback came)
Payments       ApplyOutcome: marks the payment paid or failed, once, and fires payment.succeeded or payment.failed
Subscriptions  SettlePlanPurchase: runs ActivateSubscription, the same Action an operator uses
Messaging      sends the receipt (subscription.activated) or "payment failed" (purchase.failed)
app            GET /api/rider/v1/purchase every few seconds until the status is no longer "pending"
```

Payments knows nothing about riders or plans. It charges "a thing with a type and an id" and reports back with an event. Another module that needs money calls `StartPayment` with its own type and listens for the two events.

**Gateways.** `PAYMENTS_DRIVER` in `.env` picks one.

| Driver | What it does |
| --- | --- |
| `fake` | The default. No money moves. A payment succeeds by itself after `PAYMENTS_FAKE_SETTLE_SECONDS`. A wallet number ending `0000` fails instead. The queue worker must be running. |
| `hubtel` | Hubtel Direct Receive Money: a real prompt on her phone. Needs `HUBTEL_API_USERNAME`, `HUBTEL_API_KEY`, `HUBTEL_MERCHANT_ACCOUNT_NUMBER`. |

**Before the first real payment:**

1. Put the three Hubtel values in `.env` on the server. Never in git.
2. The callback URL must be reachable from the internet. It is `APP_URL/api/webhooks/hubtel/payment` unless `HUBTEL_CALLBACK_URL` says otherwise.
3. Ask Hubtel to whitelist the server's IP for Receive Money and for the Transaction Status Check API. Without it Hubtel answers 403. This is why `hubtel` can't be used from a laptop.
4. Run `php artisan schedule:work` (or cron) so `payments:reconcile` runs every five minutes.
5. Make one small payment and read `storage/logs/hubtel-*.log`. It records every request and reply in full, with the keys masked. The request shape in `HubtelGateway::charge` follows Hubtel's published format but has not been run against Hubtel from this codebase yet, so check the first one.

**Rules the code keeps.**

- Hubtel does not sign callbacks. The only thing trusted in one is our own reference, which is long, random and never shown to the rider.
- A payment becomes paid or failed in one place, `ApplyOutcome`, under a row lock. A second callback, or a callback and a status check arriving together, change nothing.
- "Hubtel couldn't answer" never fails a payment. Only a clear "not paid" after `PAYMENTS_FAIL_AFTER_MINUTES` does.
- Money that arrives after a payment was marked failed still counts, and still activates.
- If she paid but can't be activated (her seat was released while she was paying), the purchase is flagged **Paid, needs attention** under Subscriptions > Purchases, with a count on the menu. An operator fixes the cause and presses "Try to activate again".
- The webhook always answers 200, so Hubtel doesn't retry forever. Anything missed is found by the status check.
- When a rider's data is erased, the payment rows stay for the accounts, without the phone number.

**In the console.** Subscriptions > Payments lists every charge. "Check status" asks the gateway now. "Settle by hand" needs the `payments.resolve` permission, which operators don't get by default, because it gives a rider a subscription without the gateway saying she paid.

**Real SMS.** Set `MESSAGING_SMS_DRIVER=hubtel` and the `HUBTEL_SMS_` values. These are the SMS API keys, a different pair from the payment keys. Login codes and every message rule then go out as real texts.

## Trips and the live map

A trip is one run on one day. The row is created when the van sets off.

```
crew app   GET  /api/driver/v1/today            today's runs, riders by stop
           POST /api/driver/v1/trip/start       {run_id}          riders are told the van has set off
           POST /api/driver/v1/trip/pickups     {run_id, rider_id, status}     the mate only
           POST /api/driver/v1/trip/location    {run_id, pings[]}  both phones, up to 200 at once
           POST /api/driver/v1/trip/end         {run_id}
rider app  GET  /api/rider/v1/trips             status, stops away, position, map link
anyone     GET  /t/{public id}                  the live map page. No login.
```

- **One van, two phones.** The driver's and the mate's phone both send positions. Every one is kept as history. The trip keeps only the newest, so the van stays on the map if one phone dies. A late batch from a phone that was offline never moves the van backwards.
- **"Stops away" needs no map service.** A stop counts as reached when the van comes within 150 metres of its pin, or when the mate marks anyone there. Stops need a latitude and longitude (Network > Stops) for the first to work. The second works without.
- **One map, three places.** `/t/{public id}` is a plain web page (`resources/views/trip.blade.php`) using Leaflet and OpenStreetMap, which cost nothing and need no key. The app shows that same page inside itself, the share link opens it, and the console links to it. It names no rider, can't be guessed, and stops showing the van when the trip ends.
- **By hand.** Operators can start and end a trip under Subscriptions > Trips when the crew's app is off.
- **Housekeeping.** `trips:end-stale` ends trips nobody ended, after midnight. `trips:prune` deletes positions older than `TRIPS_LOCATION_HISTORY_DAYS` (30 until the business decides).
- **Try it without a van.** `php artisan trips:simulate` drives a pretend van along today's first run and prints the map link.

Not built: sending the position with the phone locked or the app put away. See the mobile README.

## Support

Anyone in the app can write to TrustRide from the Help screen. Operators work the queue in the console under Support > Queue.

- **One conversation at a time.** Her first message picks what it is about and opens a case. Anything she writes while it is open is added to it. After it is resolved, her next message opens a new one.
- **What the operator sees.** Who she is, what she rides (route, stop, time, car, driver) and a timeline of everything said and done. The side that takes the message gathers "what she rides" (`App\Api\Support\Reporter`), because Support itself knows nothing about seats.
- **What the operator can do.** Take it, hand it to a colleague, reply, add a note, mark it as an incident, resolve it, reopen it. A reply shows in the app and is sent to her phone. A note is for staff only.
- **Calls and WhatsApp.** "Record a case" puts a phone call or a WhatsApp message into the same queue. If the number has an app account she sees the case in the app. WhatsApp messages do not arrive by themselves yet. That needs the WhatsApp Business API.
- **Incidents.** "I don't feel safe" becomes a high-severity incident the moment it arrives. Staff can mark any case as one. The phone in `SUPPORT_ALERT_PHONE` is texted. An incident is kept when a rider's data is erased, with her name and number removed. Ordinary cases are deleted.

```
GET  /api/support/v1/case       her open case, or one resolved in the last 7 days, and the categories
POST /api/support/v1/messages   {message, category?}
```

Not built: the trip share link page (S3). It needs Trips.

## What the console does today

Log in at `/admin`. The menu follows your permissions.

1. **Network.** Add stops, then a route that lists them in order with the minutes between them.
2. **Drivers.** Add a driver, his vehicle and his mate. Each gets a mobile account. Work through Vetting. A failed check can be overridden, which records who and why. Approve refuses until every required check is passed or overridden.
3. **Runs and capacity.** Put the driver on a route at a time, on set days, with the seats he holds. The list shows seats held, taken and free.
4. **Applications.** Riders apply in the app. To approve, pick the run that will carry her. That reserves her seat, and a full run refuses. Or reject with a reason.
5. **Subscriptions.** "Activate a subscription" for a rider who has paid. Renew, or record that she won't ride on a day.
6. **Today's riders.** Each run going out on the chosen day, with its riders in stop order and who is away.
7. **Monthly statements.** "Assess a month" on the driver opens his statement. Pay him outside the system, then mark it paid with the reference.
8. **Messaging.** The delivery log shows what went to whom and what failed, with "Try again". Message rules decide who is told what. Events lists everything the modules reported.
9. **Deletion requests.** Riders who asked for their data to be deleted. Confirming can't be undone.

Money is stored in pesewas as whole numbers. `App\Support\Money` formats it, and `MoneyInput` is the form field.

Dates with no time use the `App\Support\Casts\DateOnly` cast, so they store as `2026-10-05` on every database.

## Building an admin screen from the shared pieces

Everything in `app/Support/Filament` exists so screens look and behave the same. Use these before writing anything by hand.

| Piece | Use it for |
|---|---|
| `GatedByPermission` | Who sees a screen and who can change things. Set `$viewPermission` and `$managePermission`. Override `isReadOnly()` on a list-only screen. |
| `Guarded::calls(fn ...)` | What a button does. A refusal from a module shows as a red notice and the form stays open. |
| `Guarded::can()` and `Guarded::staff()` | Hiding a button, and knowing who is logged in |
| `Columns::status()` | A status as a coloured badge, from an enum or a string |
| `Columns::money()` | A pesewas column shown as "GHS 60" |
| `MoneyInput::make()` | A cedis field for a pesewas column |
| `Options::enum()` | The choices for a select or filter from an enum |
| `<x-admin.table>`, `<x-admin.row>` | A table on a page that is not a resource list |
| `<x-admin.day-picker>` | A date field bound to a Livewire property |

```php
Columns::status('status', ['approved' => 'success', 'suspended' => 'danger', 'pending' => 'warning']),
Columns::money('monthly_commission')->label('Commission'),

Action::make('suspend')
    ->visible(fn () => Guarded::can('drivers.manage'))
    ->schema([Textarea::make('reason')->required()])
    ->action(Guarded::calls(fn (Driver $record, array $data) => app(SuspendDriver::class)->handle($record, $data['reason']))),
```

An action or checklist used on two screens goes in a class of its own, like `Modules\Drivers\Filament\VettingActions`.

## Adding to a module

- **A table:** add a migration in the module's `Database/Migrations`.
- **A permission:** add it to a group in the module's provider, then run `php artisan access:sync`.
- **An admin screen:** add a Filament resource in the module's `Filament/Resources` and use the `GatedByPermission` trait. It appears in the menu only for staff who hold its permission.
- **An event:** add a class in `Events/` that extends `BaseDomainEvent`.
- **A listener:** list it in the provider's `$listen`. Make it queued and safe to run twice.

## Not done yet

- A real SMS, WhatsApp or push provider. Messaging works end to end with the `log` driver.
- Real money. Payments run on the fake gateway until Hubtel credentials, a public callback URL and a whitelisted server IP exist. See "Payments" above.
- Push notifications in the app, and letting a rider choose her channel (task R5).
- Automatic deletion of riders who have gone quiet (task H1). Deletion on request works.
- Trips, live location and the driver and mate screens (Phase 7).
- WhatsApp messages arriving in the support queue by themselves.
- Background location (D1, D9), the driver's commission screen (D8), and using pickups for the no-show policy.
- Deploy pipeline and backups (task F8).

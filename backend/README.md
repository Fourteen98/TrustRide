# Aveo backend

One Laravel app, one Postgres database, one deploy. The business is split into modules. The three sides (admin dashboard, rider screens, driver and mate screens) are thin entry points on top.

The full plan is in `docs/build-plan.pdf`. This repository covers Phase 1 of it, tasks F2 to F7.

## Run it

You need PHP 8.3 or newer, Composer and Postgres.

```sh
composer install
cp .env.example .env
php artisan key:generate
createdb aveo                # or create the database in your Postgres app
php artisan migrate --seed    # tables, permissions, roles, first super admin
php artisan serve             # http://localhost:8000
php artisan queue:work        # in a second terminal. Events are handled on the queue.
```

Then open http://localhost:8000/admin and log in with `ADMIN_EMAIL` and `ADMIN_PASSWORD` from `.env`. Out of the box those are `admin@aveo.test` and `change-me-now`. Change both before seeding anywhere real.

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

To see the console and the app with a believable month in them:

```sh
php artisan demo:reset
```

This wipes the local database and replays the last five weeks, dated from the day you run it, so the data never goes stale. Run it again whenever you want fresh dates. It takes about half a minute.

- Two real Accra routes with pinned stops: Kasoa to Circle and Madina to Accra Central. With `GOOGLE_MAPS_API_KEY` set, each road is traced once while seeding.
- Four buses, two on each route, each with a vetted driver and mate. Two are Aveo's and two are partners' (Asafo Transport and Kumi Brothers). Buses shuttle all day, from about 04:30 to 21:30, there and back again, so each makes 13 to 17 trips a day. The second bus on a route runs 20 to 30 minutes behind the first. Older days replay only the first trip each way, which keeps seeding quick.
- People travelling with riders, both ways: on Kasoa's first bus and Madina's second they pay the fare to the mate (Esi and Korkor hold seats for today), and on Kasoa's second bus they go on the rider's plan (Efua's plan is for 3).
- Sixteen riders at every stage: waiting, turned down, approved and unpaid, paying on all three plans, one renewal, one failed payment then paid, one plan that lapsed.
- Every trip from start to end: positions every three minutes, riders marked at their stops, the odd missed rider, and days riders said they would not ride.
- Today built around the moment you run it, whatever the time: the Kasoa van left about half an hour ago and is between stops, and the Madina van leaves in about a quarter of an hour. It prints today's timetable when it finishes. `--real-times` keeps the fixed 06:00 and 06:15 timetable instead.

To keep the vans moving while you test, leave this running in a terminal:

```sh
php artisan demo:drive
```

Vans set off when due, move along the road every 10 seconds, the mate marks riders at each stop, and trips end on arrival. It stops by itself when no van is left in the next three hours. `php artisan demo:reset --drive` seeds and then drives in one go.

It goes through the same Actions the app and console use, with the clock set to each moment, so the event log, messages and the pilot report read as if it happened. Messages go to the log and payments to the fake gateway whatever `.env` says. It refuses to run in production.

The riders log in with their phone numbers, such as `0245550101` for Ama Owusu (Kasoa to Circle), `0245550102` for Esi Boateng (Mallam, a stop or two ahead of the van, with two people paying the fare today), `0245550107` for Efua Mensah (three on her plan) or `0245550111` for Adjoa Quaye (Madina). The drivers are `0245550001` and `0245550005` (Kasoa) and `0245550003` and `0245550007` (Madina); each mate's number is his driver's plus one. The login code is in `storage/logs/laravel.log`.

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
  Deliveries/       Aveo Ox: businesses, zones, parcels, runs, invoices. Uses only Access and Drivers
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
- **Organisations.** `staff.organisation_id` exists from day one and Aveo is organisation 1. Drivers, vehicles and routes get the same column when their tables are written. Partner companies are not built yet.

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

### Direct debit (automatic renewal)

The other way to pay. She approves once, and her plan's price is taken each time it ends.

```
app            POST /api/rider/v1/auto-renewal {plan_id, network, phone?}
Subscriptions  StartAutoRenewal: one auto_renewals row per rider, status pending
Payments       RequestMandate: a payment_mandates row with our reference (TR-M-...), sent to the gateway
               ... she approves on her phone, or types a texted code (POST auto-renewal/code) ...
Hubtel         POST /api/webhooks/hubtel/preapproval
Payments       ApplyMandateOutcome: active or failed, once, and fires mandate.approved or mandate.failed
Subscriptions  FollowMandate: switches it on, tells her, and takes the first payment if she has no subscription
later          subscriptions:upkeep charges the day before her plan ends, through the mandate, with no prompt
```

- A mandate charge is an ordinary plan purchase, so the same code activates it and the same receipt goes out.
- Three days before the end she is told what will be taken (`auto_renewal.upcoming`), instead of "renew to keep your seat".
- The charge is tried once a day. Three failures in a row switch it off and she is told.
- **The numbers are settings staff can change**: Subscriptions > Plans > "Renewal and seat rules". Days of notice, days before the end the money is taken, failures before it is switched off, days a seat is kept after a plan ends, and days a reserved seat is held. It needs the `subscriptions.settings.manage` permission, which operators don't get by default. She can never be charged before she is told: the form refuses that.
- She can stop it in the app at any time, and an operator can under Subscriptions > Automatic renewals. Only she can start one. Once it is stopped here it is never charged again, whatever the gateway says.
- MTN and Telecel only. `debit_channel` in `modules/Payments/config.php` says which networks have it. The app hides the choice on the others.
- **Unverified, like the prompt payment.** The Hubtel "preapproval" calls in `HubtelGateway` are written from Hubtel's published format and have never been run against Hubtel. Hubtel may also need direct debit switched on for the merchant account. Ask them.
- On the fake gateway a mandate approves itself after a few seconds. A wallet ending `0000` is declined. A wallet ending `1111` asks for a code, which is `1234`.

**In the console.** Subscriptions > Payments lists every charge. "Check status" asks the gateway now. "Settle by hand" needs the `payments.resolve` permission, which operators don't get by default, because it gives a rider a subscription without the gateway saying she paid.

**App notifications.** Set `MESSAGING_PUSH_DRIVER=expo`. They go through Expo's push service and cost nothing, where every SMS costs money. The app registers each phone with `POST /api/auth/push-device` when it opens, and the first time that happens she is switched from SMS to app notifications. If she has no phone registered, or none accepts the notification, the message falls back to SMS, so nothing is lost. She can choose SMS again on the Account screen (`PUT /api/auth/preferred-channel`). Login codes always go by SMS. The app side needs a real build: see the mobile README.

**Real SMS.** Set `MESSAGING_SMS_DRIVER=hubtel` and the `HUBTEL_SMS_` values. These are the SMS API keys, a different pair from the payment keys. Login codes and every message rule then go out as real texts.

## Trips and the live map

A trip is one run on one day. The row is created when the van sets off.

```
crew app   GET  /api/driver/v1/today            today's runs, riders by stop
           POST /api/driver/v1/trip/start       {run_id}          riders are told the van has set off
           POST /api/driver/v1/trip/pickups     {run_id, rider_id, status}     the mate only
           POST /api/driver/v1/trip/location    {run_id, pings[]}  both phones, up to 200 at once
           POST /api/driver/v1/trip/end         {run_id}
rider app  GET  /api/rider/v1/trips             status, stops away, stops to where she gets off, position, map link
anyone     GET  /t/{public id}                  the live map page. No login.
```

- **One van, two phones.** The driver's and the mate's phone both send positions. Every one is kept as history. The trip keeps only the newest, so the van stays on the map if one phone dies. A late batch from a phone that was offline never moves the van backwards.
- **"Stops away" needs no map service.** A stop counts as reached when the van comes within 150 metres of its pin, or when the mate marks anyone there. Stops need a latitude and longitude (Network > Stops) for the first to work. The second works without.
- **One map, three places.** `/t/{public id}` is a plain web page (`resources/views/trip.blade.php`) using Leaflet and OpenStreetMap, which cost nothing and need no key. The app shows that same page inside itself, the share link opens it, and the console links to it. It names no rider, can't be guessed, and stops showing the van when the trip ends.
- **On the map.** A solid line shows the road the van has actually driven today, from its own positions. A dashed line joins the stops still to come, straight, because the road between them is only known once a van has driven it. The van glides to each new position instead of jumping. `?compact=1` shows the map alone, which the rider's home screen uses.
- **Google Maps, if there is a key.** Set `GOOGLE_MAPS_API_KEY` (a Google Maps Platform key with the Routes API switched on, restricted to the server's IP). Then each route's road is traced once from its stops and kept on the route, so maps follow the streets: after stops change, by "Trace the road" under Network > Routes, or `php artisan network:trace-routes`. Riders also see "about 7 min away" in today's traffic, asked at most once a minute per van and stop however many riders are looking. `GOOGLE_MAPS_DAILY_LIMIT` caps the calls a day, because Google bills each one. Without a key everything still works: straight lines and "2 stops away".
- **Getting on and getting off.** A seat's stop is her own stop, near home. Her destination is the stop she goes to, the end of the route unless she chooses another in the app (`PUT /api/rider/v1/destination`, set on all her seats on that route). Going out she gets on at her stop and off at her destination. Coming back it is the other way round: she gets on in town and off near home. The crew sees, at each stop, who gets on and who gets off, and a list of everyone on board in the order they get off. A rider is on board from the mate's "On board" until she taps "I've got down", or the van reaches her stop.
- **Your stop is next.** When the van comes within `TRIPS_APPROACH_METRES` (1,000 until the business decides) of its next stop, the riders getting on there are told to be ready, the riders getting off there are told their stop is next, and the driver and mate are told how many get on and off. Each stop is announced once a trip, and only when someone gets on or off. These go as app notifications only (Messaging > Rules), since a late SMS is worse than none. The app also counts down on screen, so it works without notifications.
- **By hand.** Operators can start and end a trip under Subscriptions > Trips when the crew's app is off.
- **Not at the stop.** When the mate marks a rider "Not here" she is sent a message. Each plan has a switch, "Give the day back when the mate marks her not at the stop", off by default. If the mate corrects it, the day is taken back. The message itself can be switched off under Messaging > Rules.
- **Housekeeping.** `trips:end-stale` ends trips nobody ended, after midnight. `trips:prune` deletes positions older than `TRIPS_LOCATION_HISTORY_DAYS` (30 until the business decides).
- **Try it without a van.** Subscriptions > Trip simulator in the console: pick a run and a rider, start the trip, then put the van at a stop, part of the way to the next, or anywhere by clicking the map. It shows how many stops away she is and the live map, and lets you mark her picked up. It uses the real Actions, so riders on that run get the real "van has set off" message. It is not offered in production. `php artisan trips:simulate` does a whole drive by itself from the terminal.

Not built: sending the position with the phone locked or the app put away. See the mobile README.

## Aveo Ox (parcel delivery)

Ox is Aveo's delivery service for businesses, in the `Deliveries` module. It shares the app, the login, the drivers and the console with Ride, and nothing else: the arch test lets it use only Access and Drivers. This is the pilot version. An operator does what a business will later do for itself.

```
console    Ox > Zones, Ox Plans, Businesses     set up once
           Ox > Parcels > Send a parcel         one at a time, or Businesses > Upload parcels (CSV)
           Ox > Runs > Build a run              a zone's waiting parcels onto one driver's run
           Businesses > Invoice a month, Ox > Invoices > Mark paid
driver app GET  /api/driver/v1/parcels           his runs: who to collect from, then each door
           POST /api/driver/v1/parcels/start | collect | deliver | not-delivered | finish
customer   GET  /o/{public id}                   her page from the SMS. No login, no app
```

- **One way to deliver: batches.** A parcel in before its zone's cut-off goes on that day's run, later ones on the next day's. "Build a run" puts every parcel waiting for a zone and day on one run, grouped by business so the driver collects each shop's together. A zone can have more than one run a day. Parcels he never collects go back in the queue for the next day when he finishes.
- **The customer has no account.** She is texted when to expect it and a link to follow it. When the driver presses "Collected", she is texted a code. She gives it to him at the door, and he types it. After five wrong codes he must call her or Aveo, and staff can confirm it in the console without the code, saying why. The code goes out directly, like a login code, so it is never in the message log; only a keyed hash of it is stored.
- **Couldn't deliver.** He picks a reason and she is texted it. An operator moves it to another day from Ox > Parcels.
- **Plans.** A business's plan numbers are copied onto it, so a negotiated price or a later change to the plan moves nobody else. At the limit a plan either keeps going and charges each one over on the invoice (the default), or stops taking parcels until next month. Cancelled parcels don't count.
- **Billing.** "Invoice a month" works out the plan fee, the deliveries over the allowance and the live map add-on. It can be issued again until it is marked paid. Payment happens outside the system for now.
- **Drivers.** Only an approved driver whose work includes Ox can be given a run (Drivers > Edit > Work he does). The app shows him a Parcels tab. Each run records what he is paid for it.
- **Customer details** are removed `OX_CUSTOMER_DATA_DAYS` after the parcel is done with (90 until the business decides), by `ox:prune-customers` each night. The parcel row stays for the invoice.
- **Not built yet:** the business's own screens in the app, automatic batching at the cut-off, the live map (the add-on is billed but there is no map yet), an API for shops, online billing, cash on delivery, and per-zone delivery fees a business shows its customers.

Numbers nobody has decided are in `modules/Deliveries/config.php`: the live map fee, the default pay for a run, how long customer details are kept, and the code length and tries.

## Support

Anyone in the app can write to Aveo from the Help screen. Operators work the queue in the console under Support > Queue.

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

## Retention and clean-up

Personal data is not kept for ever. These run nightly through the scheduler.

| Command | What it deletes | Setting |
| --- | --- | --- |
| `subscriptions:retention` | Riders who stopped riding longer ago than the retention period, by the same path as "delete my data" | Subscriptions > Plans > Renewal and seat rules. Starts at 0, which deletes nobody |
| `messaging:prune` | Delivery log and event log entries | `MESSAGING_LOG_RETENTION_DAYS`, 180 |
| `support:prune` | Resolved cases. Incidents are kept | `SUPPORT_CASE_RETENTION_DAYS`, 365 |
| `trips:prune` | Van positions | `TRIPS_LOCATION_HISTORY_DAYS`, 30 |
| `access:prune` | Login codes more than a day old | fixed |

A rider is only past retention if she has nothing live: no seat, no running or coming subscription, no application waiting, no payment under way and no direct debit. `php artisan subscriptions:retention --dry-run` lists who would go and deletes nobody. Each deletion leaves a completed row under Riders > Deletion requests, marked "Stopped riding long ago", with no name on it.

## The pilot report

`/admin/pilot-report`, for staff with the `reports.pilot.view` permission, which operators don't get by default because it shows the money. Where things stand today, then the last eight weeks: riders with a plan, new applications, money in, renewals, trips run and on time, pickups, support cases, and how many SMS were sent. Nothing is stored. Every number is counted from the records when the page opens (`App\Admin\Reports\PilotNumbers`).

Two caveats. "Trips due" uses the runs as they are today, so it is a close guess for weeks before a run changed. "On time" means started within `REPORT_ON_TIME_MINUTES` (10) of the departure time.

## Settings staff can change

`App\Support\Settings` keeps a `settings` table of config names and values. At start-up each saved row replaces the config value of the same name, so code goes on reading `config('subscriptions.seat_grace_days')` and never knows the difference. A setting nobody has saved keeps its value from the config file or `.env`.

To make another number editable: add it to a module's `config.php`, save it with `Settings::save([...])` from a module Action that checks it, and put a field on a console form. `SaveSubscriptionSettings` is the example.

## What the console does today

Log in at `/admin`. The menu follows your permissions.

1. **Network.** Add stops, then a route that lists them in order with the minutes between them.
2. **Drivers.** Add a driver, his vehicle and his mate. Each gets a mobile account. Work through Vetting. A failed check can be overridden, which records who and why. Approve refuses until every required check is passed or overridden.
3. **Runs and capacity.** Put the driver on a route at a time, on set days, with the seats he holds. The list shows seats held, taken and free.
4. **Applications.** Riders apply in the app. To approve, pick the run that will carry her. That reserves her seat, and a full run refuses. Or reject with a reason.
5. **Subscriptions.** "Activate a subscription" for a rider who has paid. Renew, or record that she won't ride on a day.
6. **Today's riders.** Each run going out on the chosen day, with its riders in stop order and who is away.
7. **Monthly statements.** "Assess a month" on the driver opens his statement. Pay him outside the system, then mark it paid with the reference.
   Each driver also has a **stage** on the way from a daily contract to Aveo driver-partner (daily contract, assessed, driver-partner) and the **work he does** (Ride, Ox, or both). Every driver starts on a daily contract driving Ride. "Change stage" moves him up only once he is approved and has a monthly assessment on record, and keeps who moved him and why. Ox has no screens yet: the work field only records who can do it.
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
- Background location (D1, D9) and the driver's commission screen (D8).
- Deploy pipeline and backups (task F8).

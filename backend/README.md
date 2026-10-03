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

Until the Hubtel sender is written, the code is written to `storage/logs/laravel.log`.

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

Set `SUPPORT_PHONE` and `SUPPORT_WHATSAPP` in `.env`. The app shows them on the Plans screen, which is how a rider pays until Hubtel is in.

### Deleting a rider's data

1. She asks in the app. Nothing is deleted yet.
2. Staff with the `riders.data.erase` permission open Riders, Deletion requests, and confirm. Operators don't have this permission by default.
3. `EraseRider` deletes the rider row. The database removes what hangs off it: her applications, seats, subscriptions and absence notices. Her mobile account goes too, unless she is also a driver or a mate.
4. The `rider.erased` event tells other modules to remove copies they hold under her phone number. Messaging scrubs the event log.

Any new table that stores a rider's details must either hang off `riders` with a cascading foreign key, or listen for `App\Support\Contracts\ErasesPersonalData`.

## What the console does today

Log in at `/admin`. The menu follows your permissions.

1. **Network.** Add stops, then a route that lists them in order with the minutes between them.
2. **Drivers.** Add a driver, his vehicle and his mate. Each gets a mobile account. Work through Vetting. A failed check can be overridden, which records who and why. Approve refuses until every required check is passed or overridden.
3. **Runs and capacity.** Put the driver on a route at a time, on set days, with the seats he holds. The list shows seats held, taken and free.
4. **Applications.** Riders apply in the app. To approve, pick the run that will carry her. That reserves her seat, and a full run refuses. Or reject with a reason.
5. **Subscriptions.** "Activate a subscription" for a rider who has paid. Renew, or record that she won't ride on a day.
6. **Today's riders.** Each run going out on the chosen day, with its riders in stop order and who is away.
7. **Monthly statements.** "Assess a month" on the driver opens his statement. Pay him outside the system, then mark it paid with the reference.
8. **Deletion requests.** Riders who asked for their data to be deleted. Confirming can't be undone.

Money is stored in pesewas as whole numbers. `App\Support\Money` formats it, and `MoneyInput` is the form field.

Dates with no time use the `App\Support\Casts\DateOnly` cast, so they store as `2026-10-05` on every database.

## Adding to a module

- **A table:** add a migration in the module's `Database/Migrations`.
- **A permission:** add it to a group in the module's provider, then run `php artisan access:sync`.
- **An admin screen:** add a Filament resource in the module's `Filament/Resources` and use the `GatedByPermission` trait. It appears in the menu only for staff who hold its permission.
- **An event:** add a class in `Events/` that extends `BaseDomainEvent`.
- **A listener:** list it in the provider's `$listen`. Make it queued and safe to run twice.

## Not done yet

- Payments. An operator activates subscriptions by hand until Hubtel is wired in (Phase 3). The app tells the rider to pay TrustRide directly.
- Messaging logs every event but has no rules, templates or channels (tasks M2 to M4). No SMS is sent, including login codes.
- Automatic deletion of riders who have gone quiet (task H1). Deletion on request works.
- Trips, live location and the driver and mate screens (Phase 7).
- Support queue and incidents (Phase 6).
- Deploy pipeline and backups (task F8).

# TrustRide mobile app

One app for riders, drivers and mates, built with Expo and React Native. Everyone logs in the same way, with a phone number and a texted code. The home screen then follows the account's roles.

What works today (tasks R1 to R6 in `../backend/docs/build-plan.pdf`, except paying in the app):

- Login with a phone number and a code, the role-based home, and the role switcher.
- **Rider:** apply for a seat, see the application's status, see her seat with the car number, colour, driver, stop and time, see the plans and how to pay, say she won't ride on a day, and ask for her data to be deleted.
- **Driver and mate:** placeholder cards. Their screens come in release 2.

Paying in the app comes with Hubtel. Until then the Plans screen tells her to pay TrustRide directly, and an operator switches her subscription on in the console.

## Run it

You need Node 20 or newer, and the backend running (`php artisan serve` in `../backend`).

```sh
npm install
cp .env.example .env      # then set EXPO_PUBLIC_API_URL, see below
npx expo start
```

Press `a` for the Android emulator, `i` for the iOS simulator, or scan the QR code with Expo Go on a phone.

`EXPO_PUBLIC_API_URL` tells the app where the backend is:

| Running on | Value |
|---|---|
| Android emulator | `http://10.0.2.2:8000/api` (the default) |
| iOS simulator or web | `http://localhost:8000/api` |
| A real phone on the same Wi-Fi | `http://<your Mac's IP>:8000/api`, and start the backend with `php artisan serve --host=0.0.0.0` |

Restart `npx expo start` after changing `.env`.

**Logging in while developing.** No SMS is sent yet. The code is written to `../backend/storage/logs/laravel.log`. Look for the line "Your TrustRide code is".

## Layout

```
src/
  app/                 the routes (expo-router)
    _layout.tsx        decides between (auth) and (app) from the login state
    (auth)/            phone number, then code
    (app)/             home and account. Only reachable when logged in.
  screens/             the three fixed screen sets: rider, driver, mate
  rider/               the rider's API calls, the seat card, and the loader hook
  auth/                login state, token storage, can(permission)
  api/
    client.ts          the one API client
    schema.d.ts        generated from the backend. Don't edit by hand.
  components/          button, text field, card, choice, notice, screen frame
  lib/                 date and phone helpers
  constants/theme.ts   colours and spacing
```

## How roles work in the app

- Login returns the account's roles and its full list of permissions.
- A role opens one of three fixed screen sets: `rider`, `driver` or `mate`. That is the role's `screens` field, set in the admin dashboard.
- An account with more than one role, such as a mate who is also a rider, gets a switcher at the top of home. The choice is remembered.
- Use `can('some.permission')` from `useAuth()` to show or hide a button. The server still checks every request, so this is for tidiness, not security.
- A new role made in the dashboard can reuse these screen sets. It can't create new screens. Those are written here, in code.

## The rider's home

`GET /rider/v1/home` returns everything in one call, and what comes back decides what she sees:

| What came back | What she sees |
|---|---|
| No application, or one rejected or withdrawn | "Apply for a seat" |
| An application that is pending | "We're looking at your application", with Withdraw |
| A seat, no subscription | Her seat details, "Your seat is reserved", and "Choose a plan" |
| A seat and a subscription | Her seat details, days left, "I won't ride on a day", and Renew |

The app never works out a rule. Before she confirms she won't ride, it asks the server what will happen (`/rider/v1/absences/preview`) and shows the answer.

Pull down on home to reload. It also reloads each time she comes back to it.

## Talking to the backend

The API client is typed from the backend's OpenAPI file, so a wrong field name is a compile error.

```ts
const data = await call(() => api.POST('/auth/verify', { body: { phone, code } }));
```

Put a screen set's calls in one file, like `src/rider/api.ts`, and take the types from the generated schema so they can't drift.

After the backend's API changes, with the backend running:

```sh
npm run api:types
```

That rewrites `src/api/schema.d.ts`. Then run `npm run typecheck` to see what the change broke.

## Checks

```sh
npm run typecheck
npm run lint
```

## Releases

- **Release 1** is rider screens only. It must not include any location code or permission, so the Play Store review stays the normal one.
- **Release 2** adds the driver and mate screens and background location.

Don't add `expo-location` or any location permission until release 2.

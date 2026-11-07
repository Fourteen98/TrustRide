# Aveo mobile app

One app for riders, drivers and mates, built with Expo and React Native. Everyone logs in the same way, with a phone number and a texted code. The home screen then follows the account's roles.

What works today (tasks R1 to R6 in `../backend/docs/build-plan.pdf`, except paying in the app):

- Login with a phone number and a code, the role-based home, and the role switcher.
- **Rider:** apply for a seat, see the application's status, see her seat with the car number, colour, driver, stop and time, see the plans and how to pay, say she won't ride on a day, and ask for her data to be deleted.
- **Driver and mate:** placeholder cards. Their screens come in release 2.

She pays for a plan on the Plans screen with mobile money. The server sends a prompt to her phone and the screen asks every few seconds until the payment is settled. She can pay once, or set up direct debit so the plan renews by itself (`src/rider/auto-renewal.tsx`). The Plans screen keeps nothing in its own memory about a payment: it reads the server's home answer and asks again every few seconds while something is under way. In development the backend uses a fake gateway: a payment succeeds after a few seconds, and a wallet number ending 0000 fails.

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

**Logging in while developing.** No SMS is sent yet. The code is written to `../backend/storage/logs/laravel.log`. Look for the line "Your Aveo code is".

## Layout

```
src/
  app/                 the routes (expo-router)
    _layout.tsx        decides between (auth) and (app) from the login state
    (auth)/            phone number, then code
    (app)/             home, apply, plans, not riding, account. Only reachable when logged in.
  screens/             the three fixed screen sets: rider, driver, mate
  rider/               the rider's API calls and the seat card
  components/          the shared building blocks, listed below
  hooks/               useLoader, useAction, the theme and colour scheme
  auth/                login state, token storage, can(permission)
  api/
    client.ts          the one API client
    schema.d.ts        generated from the backend. Don't edit by hand.
  lib/                 date, phone and error helpers
  constants/theme.ts   colours and spacing
```

## Building a screen from the shared pieces

A screen should be mostly these, with very little styling of its own.

| Piece | Use it for |
|---|---|
| `Screen` | The frame of every screen: safe area, padding, scrolling, keyboard, pull to refresh |
| `Loadable` | Anything fetched from the server: spinner, error with "Try again", then the content |
| `Card` | A titled panel. Plain text inside becomes small secondary text. |
| `Button` | Every button. `variant="plain"` for the quieter one, `loading` while busy. |
| `TextField` | Every text input, with its label and error |
| `Choice` and `ChoiceGroup` | Picking one of several: stops, times, days. Rows or chips. |
| `Detail` | A small label with its value under it |
| `MutedText` | Small secondary text |
| `Notice` | An error or an info line |
| `HomeHeader` | The name, Account link and role switcher on each home |
| `ThemedText` | All other text, so colours and sizes come from the theme |
| `MapSheet` | A screen that is a map with a sheet over it: map behind, bar on top, buttons above the sheet, a sheet that drags up |
| `TripMap` | The native map: the blue dot, a route, a labelled stop, the van as a gold tag. `emphasis="crew"` and `badges` for the driver and mate. `ref` gives Route and Me. |
| `MapTopBar`, `MapButton`, `MapChoices`, `SheetStatus` | The "Where to?" bar (or a screen's own control) and Help over a map, round buttons and a switch pill on a map, and the status line at the top of a sheet |
| `SearchField` | The "Where to?" pill. With `onPress` it opens search; without, she types in it. |
| `ActionMenu` | A menu that floats up from the bottom for the few things that can be done to one thing, such as today's ride |
| `FloatingTabBar` | The tabs as a pill over the bottom of the screen. Screens inside the tabs keep clear of it through `Screen`. |
| `Icon` | An Apple symbol on iPhone, a plain character elsewhere |
| `Stepper` | A number picked with − and +, such as how many people travel with her |

Two hooks do the work behind them:

```tsx
// Fetching. Reloads when the screen comes back into view.
const plans = useLoader(riderApi.plans);
<Loadable state={plans}>{(list) => list.map((plan) => <Card key={plan.id} title={plan.name} />)}</Loadable>

// Doing. Tracks busy for the button and keeps the error to show.
const save = useAction();
<Notice text={save.error} />
<Button label="Send" loading={save.busy} onPress={() => save.run(() => riderApi.apply(body), { then: router.back })} />
```

If you find yourself writing `useState` for "loading" or "error", or a `try` and `catch` in a screen, use one of the two hooks. If you write the same few lines of layout on a second screen, make it a component.

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

## Help

`src/app/(app)/help.tsx` is the same for every role. The link sits in the home header for any account with the `support.alert.raise` permission. The screen asks the server every 10 seconds while a case is open, so a reply from staff appears by itself.

## Driver and mate

`src/screens/crew-home.tsx` is home for both. The driver starts and ends the trip, the mate marks each rider on board or not here, and both phones send the van's position (`src/crew/use-location-sender.ts`).

**The position is only sent while the crew screen is open and the phone is unlocked.** That is the part that works in Expo Go. Sending with the screen off needs `expo-task-manager`, a development build, a permanent notification, battery saving switched off on each phone, and Google Play's approval for background location. That is tasks D1 and D9, and D1 says to test it on the crew's real phones for a full morning before building further.

`expo-location` adds the location permission to the whole app. If release 1 must ship with no location permission, add the location permissions to `android.blockedPermissions` in `app.json` for that build.

## The map

`src/components/trip-map.tsx` draws the live map natively with `react-native-maps`: Apple Maps on iPhone, Google Maps on Android. It shows her own position as the blue dot (it asks for location permission, only while the app is open), the van gliding between positions, the road the van has driven, and the road still ahead. The small map on the home card and the full screen (`src/app/(app)/trip.tsx`) both use it. The web build has no native map, so `trip-map.web.tsx` shows the backend's own map page instead.

Keys:

- **Expo Go and the iPhone simulator:** nothing needed.
- **iPhone builds:** nothing needed. Apple Maps has no key.
- **Android builds:** `GOOGLE_MAPS_ANDROID_KEY` in `mobile/.env`, read by `app.config.js`. A key with "Maps SDK for Android" switched on, restricted to the package `com.aveo.app`. Rebuild after setting it.

The road along the streets and "about 7 min away" come from the backend's Google key, not the app's. Without it the stops are joined with straight dashed lines and riders see "2 stops away".

## Seeing the real app, with the logo and splash

Expo Go shows its own icon and splash, so the Aveo ones never appear there. To see the app as it will ship, make a build on your Mac. It needs Xcode and nothing else: no Expo account and no Apple developer account for the simulator.

```
npx expo run:ios
```

The first run takes several minutes. It creates an `ios` folder from `app.json` (not kept in git), builds the app, installs it on the simulator and opens it. After that, run `npx expo start` and open the Aveo app from the simulator's home screen. Expo Go is still there next to it, and pressing `i` still opens Expo Go.

Run it again whenever `app.json` or a package with native code changes (a new icon, `expo-location`, and so on). Ordinary screen changes don't need it.

For Android the same thing is `npx expo run:android`, which needs Android Studio. A real iPhone needs an Apple developer account.

## Driver's commission

`src/app/(app)/earnings.tsx`. Drivers only. It shows the monthly commission Aveo pays and the last twelve months: assessment, bonus, and whether it was paid.

## Parcels (Aveo Ox)

`src/app/(app)/(tabs)/parcels.tsx`, with the pieces in `src/ox/`. The tab shows only to a driver whose work includes Ox (`work_types` in the account, set in the console). It lists today's parcel runs. He starts the run, presses "Collected" at each business (each customer is then texted a code), and at each door types the customer's code or says why it couldn't be delivered. Then he finishes the run. Runs are built by an operator in the console. In the demo, log in as 0245550201 (Kojo) to find a Lapaz run already on the road.

## App notifications

`src/notifications/push.ts` asks for permission and gets this phone's push token. `src/auth/auth-context.tsx` sends it to the backend after login. The Account screen lets her choose between notifications and SMS once the backend has both switched on.

Notifications can't work in Expo Go, a simulator or the web build. There the code does nothing and messages go by SMS. To turn them on for real:

1. Make a free Expo account and link the project once: `npx eas init`. This writes a project id into `app.json`, which the push token needs.
2. Android: make a free Firebase project, download `google-services.json` into `mobile/`, and set `"googleServicesFile": "./google-services.json"` under `android` in `app.json`. Upload the Firebase service account key in the Expo dashboard under the project's credentials.
3. iPhone: needs a paid Apple developer account. Not needed for the pilot if the riders are on Android.
4. Make a build on a real phone (`npx expo run:android` with Android Studio, or a cloud build with `npx eas build`).
5. On the backend set `MESSAGING_PUSH_DRIVER=expo`.

None of this has been run. The backend half is covered by tests against a faked Expo service. The phone half is written and type-checked only.

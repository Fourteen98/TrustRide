# TrustRide

A subscription that guarantees a commuter a seat on a trotro that was going to run anyway. The rider pays her fare to the mate as she does today. TrustRide sells the certainty, and never touches fare money.

This repository holds both halves of the product:

| Folder | What it is | Start here |
|---|---|---|
| `backend/` | The Laravel API and the admin console. A modular monolith on Postgres. | `backend/README.md` |
| `mobile/` | The Expo app for riders, drivers and mates. One app, one login. | `mobile/README.md` |

The plan and the data model are in `backend/docs/`:

- `build-plan.pdf` is the stack, the modules, how they talk, and the task list.
- `domain-model.pdf` is what every table holds, and the path from a rider signing up to a driver being paid.

## Run both

```sh
# Terminal 1
cd backend && php artisan serve --host=0.0.0.0

# Terminal 2
cd backend && php artisan queue:work

# Terminal 3
cd mobile && npx expo start
```

First-time setup for each is in its own README. `backend/CLAUDE.md` and `mobile/CLAUDE.md` hold the rules for changing the code, for people and for AI tools.

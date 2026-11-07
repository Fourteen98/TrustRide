# Aveo mobile: rules for anyone, human or AI, changing this code

Read `README.md` first. The plan is `../backend/docs/build-plan.pdf`.

- One app for riders, drivers and mates. Don't split it.
- All API calls go through `src/api/client.ts` using `api` and `call`. Never use `fetch` directly.
- `src/api/schema.d.ts` is generated. Regenerate it with `npm run api:types`. Don't edit it.
- Build screens from the shared pieces in `src/components`. The table in `README.md` says which one is for what. Add a new shared component before copying layout to a second screen.
- Fetch with `useLoader` and show it with `<Loadable>`. Do things with `useAction`. No `try` and `catch`, and no loading or error `useState`, inside a screen.
- Colours and spacing come from `constants/theme.ts`. No hard-coded colours in screens.
- Screens hold no business rules. If something needs deciding, the backend decides and the app shows the result.
- Hide things with `can(permission)`. Never rely on that for security.
- Location is foreground only: her blue dot on the map, and the stop nearest her in search. Background location waits for release 2.
- Show errors as the plain sentence the backend sends in `message`.
- Run `npm run typecheck` and `npm run lint` before finishing.

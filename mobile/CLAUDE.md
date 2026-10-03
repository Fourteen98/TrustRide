# TrustRide mobile: rules for anyone, human or AI, changing this code

Read `README.md` first. The plan is `../backend/docs/build-plan.pdf`.

- One app for riders, drivers and mates. Don't split it.
- All API calls go through `src/api/client.ts` using `api` and `call`. Never use `fetch` directly.
- `src/api/schema.d.ts` is generated. Regenerate it with `npm run api:types`. Don't edit it.
- Screens hold no business rules. If something needs deciding, the backend decides and the app shows the result.
- Hide things with `can(permission)`. Never rely on that for security.
- No location code or permission before release 2.
- Show errors as the plain sentence the backend sends in `message`.
- Run `npm run typecheck` and `npm run lint` before finishing.

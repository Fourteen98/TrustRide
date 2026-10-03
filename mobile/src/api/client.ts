import createClient, { type Middleware } from 'openapi-fetch';

import { API_URL } from '@/config';

import type { components, paths } from './schema';

/**
 * The one API client. Its types come from src/api/schema.d.ts, which is generated
 * from the backend's OpenAPI file. Run `npm run api:types` after the backend changes.
 */
export const api = createClient<paths>({ baseUrl: API_URL, headers: { Accept: 'application/json' } });

export type Account = components['schemas']['AccountResource'];
export type Role = Account['roles'][number];

let token: string | null = null;
let onUnauthorised: (() => void) | null = null;

export function setToken(value: string | null) {
  token = value;
}

/** Called when the server says the token is no longer good, so the app can go back to login. */
export function setUnauthorisedHandler(handler: (() => void) | null) {
  onUnauthorised = handler;
}

/*
 * These two hooks change the request in place and return nothing, on purpose.
 * If a hook returns the request or response, the library checks it with `instanceof Request/Response`.
 * On a real phone Expo's fetch hands back its own response class, that check fails, and every call
 * dies after the server has already answered. Returning nothing skips the check.
 */
const auth: Middleware = {
  onRequest({ request }) {
    if (token) {
      request.headers.set('Authorization', `Bearer ${token}`);
    }
  },
  onResponse({ response }) {
    if (response.status === 401 && token) {
      onUnauthorised?.();
    }
  },
};

api.use(auth);

/** The message to show a person when a request fails. The backend sends { message } for every refusal. */
export function errorMessage(error: unknown, fallback = 'Something went wrong. Try again.'): string {
  if (error && typeof error === 'object' && 'message' in error && typeof error.message === 'string') {
    return error.message;
  }

  return fallback;
}

/** Runs a request and turns "the phone could not reach the server" into a readable error. */
export async function call<T>(request: () => Promise<{ data?: T; error?: unknown }>): Promise<T> {
  let result: { data?: T; error?: unknown };

  try {
    result = await request();
  } catch (cause) {
    // The person gets a plain sentence. While developing, the real reason goes to the Expo terminal.
    if (__DEV__) {
      console.warn(`[api] request failed against ${API_URL}:`, cause);
    }

    throw new Error("Can't reach TrustRide. Check your connection and try again.");
  }

  if (result.error !== undefined) {
    throw new Error(errorMessage(result.error));
  }

  return result.data as T;
}

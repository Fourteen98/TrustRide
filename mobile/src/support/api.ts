import { api, call } from '@/api/client';
import type { components, paths } from '@/api/schema';

export type SupportState = paths['/support/v1/case']['get']['responses'][200]['content']['application/json'];
export type SupportCase = components['schemas']['CaseResource'];
export type SupportMessage = SupportCase['messages'][number];
export type Category = SupportState['categories'][number];

/** Writing to TrustRide for help. The same for a rider, a driver and a mate. */
export const supportApi = {
  state: () => call(() => api.GET('/support/v1/case')),
  /** `category` only counts on the first message of a new conversation. */
  send: async (message: string, category?: string | null) =>
    (await call(() => api.POST('/support/v1/messages', { body: { message, category: (category ?? null) as never } }))).case,
};

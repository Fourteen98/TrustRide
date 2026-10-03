import { api, call } from '@/api/client';
import type { components, paths } from '@/api/schema';

/** The shapes the rider endpoints return, taken straight from the generated API types. */
export type RiderHome = paths['/rider/v1/home']['get']['responses'][200]['content']['application/json'];
export type RouteChoice = paths['/rider/v1/routes']['get']['responses'][200]['content']['application/json']['routes'][number];
export type NoticePreview = paths['/rider/v1/absences/preview']['get']['responses'][200]['content']['application/json'];
export type Seat = components['schemas']['SeatResource'];
export type Subscription = components['schemas']['SubscriptionResource'];
export type Application = components['schemas']['ApplicationResource'];
export type Plan = components['schemas']['PlanResource'];

export type NewApplication = {
  name: string;
  route_id: number;
  stop_id: number;
  morning_time: string | null;
  evening_time: string | null;
};

/** Every call the rider screens make. Each one throws an Error with a sentence fit to show. */
export const riderApi = {
  home: () => call(() => api.GET('/rider/v1/home')),
  routes: async () => (await call(() => api.GET('/rider/v1/routes'))).routes,
  apply: (body: NewApplication) => call(() => api.POST('/rider/v1/application', { body })),
  withdraw: () => call(() => api.DELETE('/rider/v1/application')),
  plans: async () => (await call(() => api.GET('/rider/v1/plans'))).plans,
  previewNotice: (date: string) => call(() => api.GET('/rider/v1/absences/preview', { params: { query: { date } } })),
  giveNotice: (date: string) => call(() => api.POST('/rider/v1/absences', { body: { date } })),
  requestDeletion: () => call(() => api.POST('/rider/v1/data-deletion')),
  cancelDeletion: () => call(() => api.DELETE('/rider/v1/data-deletion')),
};

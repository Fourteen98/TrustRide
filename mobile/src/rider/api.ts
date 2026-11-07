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
export type AutoRenewal = components['schemas']['AutoRenewalResource'];
export type Purchase = components['schemas']['PurchaseResource'];
export type PlanChoices = paths['/rider/v1/plans']['get']['responses'][200]['content']['application/json'];
export type Network = PlanChoices['networks'][number];
export type NewPurchase = NonNullable<paths['/rider/v1/purchase']['post']['requestBody']>['content']['application/json'];

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
  plans: () => call(() => api.GET('/rider/v1/plans')),
  /** Sends the mobile money prompt. The purchase comes back as "pending". */
  pay: async (body: NewPurchase) => (await call(() => api.POST('/rider/v1/purchase', { body }))).purchase,
  /** Direct debit: sends a request to approve once. Nothing is charged until she approves. */
  startAutoRenewal: async (body: NewPurchase) => (await call(() => api.POST('/rider/v1/auto-renewal', { body }))).auto_renewal,
  /** The code she was texted, on networks that confirm a direct debit that way. */
  sendAutoRenewalCode: (code: string) => call(() => api.POST('/rider/v1/auto-renewal/code', { body: { code } })),
  stopAutoRenewal: () => call(() => api.DELETE('/rider/v1/auto-renewal')),
  /** Her latest payment, for asking again while it is pending. */
  purchase: async () => (await call(() => api.GET('/rider/v1/purchase'))).purchase,
  /** Where her van is today. One entry for each seat that runs today. */
  trips: async () => (await call(() => api.GET('/rider/v1/trips'))).trips,
  /** Her route, stops and stop for each seat she holds, to draw under her own position. Works with no van out. */
  routeMap: async () => (await call(() => api.GET('/rider/v1/route-map'))).routes,
  /** Vans that go to a place. With her position, she gets on at the stop nearest her. */
  search: async (to: string, near: { latitude: number; longitude: number } | null) =>
    (await call(() => api.GET('/rider/v1/search', { params: { query: { to, ...(near ?? {}) } } }))).runs,
  /** Hold seats on a day for people travelling with her, who each pay the fare. 0 lets them go. */
  holdGuests: (seat_id: number, date: string, seats: number) => call(() => api.PUT('/rider/v1/guests', { body: { seat_id, date, seats } })),
  /** Where she goes on her route: she gets off there going out, and on there coming back. Set on all her seats on the route. */
  setDestination: (seat_id: number, stop_id: number) => call(() => api.PUT('/rider/v1/destination', { body: { seat_id, stop_id } })),
  /** She has got down from today's van on that run. */
  alight: (runId: number) => call(() => api.POST('/rider/v1/trips/alight', { body: { run_id: runId } })),
  /** Her past trips, newest first, 20 a page. */
  history: (page: number) => call(() => api.GET('/rider/v1/trips/history', { params: { query: { page } } })),
  previewNotice: (date: string) => call(() => api.GET('/rider/v1/absences/preview', { params: { query: { date } } })),
  giveNotice: (date: string) => call(() => api.POST('/rider/v1/absences', { body: { date } })),
  requestDeletion: () => call(() => api.POST('/rider/v1/data-deletion')),
  cancelDeletion: () => call(() => api.DELETE('/rider/v1/data-deletion')),
};

/** One of her trips today, as `trips()` returns it. */
export type RiderTrip = Awaited<ReturnType<typeof riderApi.trips>>[number];

/** Her route on the map, as `routeMap()` returns it. */
export type RouteMap = Awaited<ReturnType<typeof riderApi.routeMap>>[number];

/** One past trip, as `history()` returns it. */
export type PastTrip = Awaited<ReturnType<typeof riderApi.history>>['trips'][number];

/** One van that goes where she searched, as `search()` returns it. */
export type FoundRun = Awaited<ReturnType<typeof riderApi.search>>[number];

import { api, call } from '@/api/client';
import type { paths } from '@/api/schema';

export type ParcelRun = paths['/driver/v1/parcels']['get']['responses'][200]['content']['application/json']['runs'][number];
export type Pickup = ParcelRun['pickups'][number];
export type Drop = ParcelRun['drops'][number];

/** Aveo Ox in the driver's app: his parcel runs. Each call throws an Error with a sentence fit to show. */
export const parcelsApi = {
  today: async () => (await call(() => api.GET('/driver/v1/parcels'))).runs,
  start: (run_id: number) => call(() => api.POST('/driver/v1/parcels/start', { body: { run_id } })),
  collect: (run_id: number, business_id: number) => call(() => api.POST('/driver/v1/parcels/collect', { body: { run_id, business_id } })),
  deliver: (delivery_id: number, code: string) => call(() => api.POST('/driver/v1/parcels/deliver', { body: { delivery_id, code } })),
  notDelivered: (delivery_id: number, reason: string) =>
    call(() => api.POST('/driver/v1/parcels/not-delivered', { body: { delivery_id, reason } })),
  finish: (run_id: number) => call(() => api.POST('/driver/v1/parcels/finish', { body: { run_id } })),
};

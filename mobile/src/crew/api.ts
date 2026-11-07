import { api, call } from '@/api/client';
import type { paths } from '@/api/schema';

export type CrewToday = paths['/driver/v1/today']['get']['responses'][200]['content']['application/json'];
type RawRun = CrewToday['runs'][number];
/**
 * One run as the crew screens use it. The road and the trail are plain lists of [latitude, longitude]: the generated
 * types call them pairs, which the replies to start, end and pickups lose on the way through `call`.
 */
export type CrewRun = Omit<RawRun, 'map'> & { map: Omit<RawRun['map'], 'path' | 'trail'> & { path: number[][] | null; trail: number[][] } };
export type CrewStop = CrewRun['stops'][number];
export type CrewRider = CrewStop['riders'][number];
export type Ping = { latitude: number; longitude: number; accuracy: number | null; recorded_at: string };

/** Every call the driver and mate screens make. Each one throws an Error with a sentence fit to show. */
export const crewApi = {
  today: () => call(() => api.GET('/driver/v1/today')),
  start: async (run_id: number) => (await call(() => api.POST('/driver/v1/trip/start', { body: { run_id } }))).run,
  end: async (run_id: number) => (await call(() => api.POST('/driver/v1/trip/end', { body: { run_id } }))).run,
  mark: async (run_id: number, rider_id: number, status: 'picked_up' | 'missed') =>
    (await call(() => api.POST('/driver/v1/trip/pickups', { body: { run_id, rider_id, status } }))).run,
  sendLocation: (run_id: number, pings: Ping[]) => call(() => api.POST('/driver/v1/trip/location', { body: { run_id, pings } })),
};

export type Earnings = paths['/driver/v1/earnings']['get']['responses'][200]['content']['application/json'];

/** The driver's own commission. Mates have none. */
export const earningsApi = {
  show: () => call(() => api.GET('/driver/v1/earnings')),
};

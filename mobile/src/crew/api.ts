import { api, call } from '@/api/client';
import type { paths } from '@/api/schema';

export type CrewToday = paths['/driver/v1/today']['get']['responses'][200]['content']['application/json'];
export type CrewRun = CrewToday['runs'][number];
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

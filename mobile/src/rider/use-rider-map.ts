import type { MapProps } from '@/components/trip-map';
import { useLoader } from '@/hooks/use-loader';
import { usePoll } from '@/hooks/use-poll';

import { riderApi, type RiderTrip, type RouteMap } from './api';
import { focusOf } from './trip-card';

const noTrips = async (): Promise<RiderTrip[]> => [];

/** The route to show first: the trip into town, since that is the one she waits for. */
export function firstRoute(routes: RouteMap[]): RouteMap | null {
  return routes.find((route) => route.direction === 'outbound') ?? routes[0] ?? null;
}

/**
 * What the rider's map shows. While her van runs, the live trip: the van, the road it has driven and the road ahead.
 * Otherwise her route and her stop. With no seat, only her own blue dot.
 *
 * Today's trips are asked for again every 15 seconds while a van runs and every minute before it leaves.
 */
export function useRiderMap({ followTrips }: { followTrips: boolean }) {
  const routes = useLoader(riderApi.routeMap);
  const trips = useLoader(followTrips ? riderApi.trips : noTrips);
  const today = trips.data ?? [];

  const live = today.find((trip) => trip.status === 'running' && trip.map_url !== null) ?? null;
  const next = today.find((trip) => trip.status === 'not_started') ?? null;
  const route = firstRoute(routes.data ?? []);

  usePoll(
    trips.reload,
    today.some((trip) => trip.status !== 'ended'),
    live ? 15000 : 60000,
  );

  const map: MapProps & { mode: 'live' | 'route' | 'me' } = live
    ? {
        mode: 'live',
        stops: live.stops,
        path: live.path,
        trail: live.trail,
        position: live.position,
        ...focusOf(live),
        vanLabel: live.car_number,
        mapUrl: live.map_url,
      }
    : { mode: route ? 'route' : 'me', stops: route?.stops ?? [], path: route?.path ?? null, position: null, myStopId: route?.my_stop_id };

  return {
    map,
    today,
    live,
    next,
    route,
    reload: async () => {
      await Promise.all([routes.reload(), trips.reload()]);
    },
  };
}

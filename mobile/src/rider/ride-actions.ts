import { useRouter } from 'expo-router';
import { Share } from 'react-native';

import { useAuth } from '@/auth/auth-context';
import type { MenuItem } from '@/components/action-menu';
import { useAction } from '@/hooks/use-action';
import { today } from '@/lib/dates';

import { riderApi, type RiderTrip } from './api';

/**
 * What she can do about today's ride, as menu items: say she has got down, say she won't ride today,
 * see the stops, share the trip, or get help. Only what fits the moment is offered. The server still
 * decides: a day added back or not, and whether getting down can be recorded.
 */
export function useRideActions(trip: RiderTrip | null, index: number, onChanged: () => void, fareSeatId: number | null = null) {
  const router = useRouter();
  const { can } = useAuth();
  const alight = useAction();

  if (!trip) {
    return { items: [] as MenuItem[], error: alight.error };
  }

  const onBoardOrDue = trip.pickup !== 'missed' && trip.alighted_at === null;
  const items: MenuItem[] = [];

  if (trip.status !== 'not_started' && onBoardOrDue && trip.pickup === 'picked_up') {
    items.push({
      label: "I've got down",
      detail: "Tell us you have arrived. It closes today's ride for you.",
      icon: 'figure.walk.arrival',
      glyph: '↘',
      onPress: () => void alight.run(() => riderApi.alight(trip.run_id), { then: onChanged }),
    });
  }

  if (trip.pickup === null && trip.status !== 'ended' && can('subscriptions.absence.give')) {
    items.push({
      label: "I won't ride today",
      detail: 'We tell the driver not to wait. See first if the day is added back.',
      icon: 'xmark.circle',
      glyph: '✕',
      tone: 'danger',
      onPress: () => router.push({ pathname: '/not-riding', params: { date: today() } }),
    });
  }

  if (fareSeatId !== null && trip.pickup === null && trip.status !== 'ended' && can('subscriptions.guests.reserve')) {
    items.push({
      label: 'People with me',
      detail: 'Hold seats today for people travelling with you. Each pays the fare.',
      icon: 'person.2',
      glyph: '+',
      onPress: () => router.push({ pathname: '/guests', params: { seat_id: String(fareSeatId), date: today() } }),
    });
  }

  if (trip.status === 'running') {
    items.push({
      label: 'See the stops',
      detail: 'The full map with every stop in order.',
      icon: 'map',
      glyph: '◎',
      onPress: () => router.push({ pathname: '/trip', params: { index: String(index) } }),
    });
  }

  if (trip.map_url && trip.status === 'running' && can('trips.share')) {
    items.push({
      label: 'Share my trip',
      detail: 'Someone can follow the van on a web page until the trip ends.',
      icon: 'square.and.arrow.up',
      glyph: '↗',
      onPress: () => void Share.share({ message: `Follow my Aveo van: ${(trip.map_url ?? '').split('?')[0]}` }),
    });
  }

  if (can('support.alert.raise')) {
    items.push({
      label: 'Report a problem',
      detail: 'The van is late, something happened, or the mate marked you wrongly.',
      icon: 'exclamationmark.bubble',
      glyph: '!',
      onPress: () => router.push('/help'),
    });
  }

  return { items, error: alight.error };
}

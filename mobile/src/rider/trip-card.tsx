import { useRouter } from 'expo-router';
import { Share } from 'react-native';

import { useAuth } from '@/auth/auth-context';
import { Button } from '@/components/button';
import { Card } from '@/components/card';
import { MutedText } from '@/components/muted-text';
import { ThemedText } from '@/components/themed-text';
import { useLoader } from '@/hooks/use-loader';
import { usePoll } from '@/hooks/use-poll';
import { clock } from '@/lib/dates';

import { riderApi, type RiderTrip } from './api';

function headline(trip: RiderTrip): string {
  if (trip.pickup === 'picked_up') {
    return 'You are on board';
  }

  if (trip.status === 'ended') {
    return 'Today\'s trip has ended';
  }

  if (trip.status === 'not_started') {
    return `Due at ${clock(trip.my_time)}`;
  }

  if (trip.pickup === 'missed') {
    return 'Marked as not at the stop';
  }

  if (trip.stops_away === null) {
    return 'On its way';
  }

  return trip.stops_away === 0 ? 'At your stop' : `${trip.stops_away} ${trip.stops_away === 1 ? 'stop' : 'stops'} away`;
}

function detail(trip: RiderTrip): string {
  if (trip.status === 'not_started') {
    return `The van has not set off yet. Be at ${trip.my_stop} by ${clock(trip.my_time)}. We will tell you when it leaves.`;
  }

  if (trip.status === 'ended') {
    return `${trip.route}. Car ${trip.car_number}.`;
  }

  if (trip.pickup === 'missed') {
    return 'The mate did not find you at your stop. If that is wrong, tell us under Help.';
  }

  const seen = trip.position ? (trip.position.live ? 'Live.' : 'The van\'s phones have lost their network, so this may be behind.') : 'No position from the van yet.';

  return `Car ${trip.car_number} to ${trip.my_stop}. ${seen}`;
}

/**
 * Task D5 on the home screen: how far her van is, in stops. It asks again every 15 seconds while the
 * van is on the road and every minute before it leaves, so the card changes by itself.
 */
export function TodaysTrips() {
  const router = useRouter();
  const { can } = useAuth();
  const state = useLoader(riderApi.trips);
  const trips = state.data ?? [];
  const running = trips.some((trip) => trip.status === 'running');

  usePoll(state.reload, trips.some((trip) => trip.status !== 'ended'), running ? 15000 : 60000);

  return (
    <>
      {trips.map((trip) => (
        <Card key={`${trip.route}-${trip.my_time}`} title="Your van today">
          <ThemedText type="subtitle">{headline(trip)}</ThemedText>
          <MutedText>{detail(trip)}</MutedText>
          {trip.map_url && trip.status === 'running' ? (
            <>
              <Button label="See on the map" onPress={() => router.push({ pathname: '/trip', params: { url: trip.map_url ?? '' } })} />
              {can('trips.share') ? (
                <Button
                  label="Share my trip"
                  variant="plain"
                  onPress={() => void Share.share({ message: `Follow my TrustRide van: ${(trip.map_url ?? '').split('?')[0]}` })}
                />
              ) : null}
            </>
          ) : null}
        </Card>
      ))}
    </>
  );
}

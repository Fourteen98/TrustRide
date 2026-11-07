import { useRouter } from 'expo-router';
import { Share, StyleSheet, View } from 'react-native';

import { useAuth } from '@/auth/auth-context';
import { Button } from '@/components/button';
import { Card } from '@/components/card';
import { MutedText } from '@/components/muted-text';
import { ThemedText } from '@/components/themed-text';
import { TripMap } from '@/components/trip-map';
import { clock } from '@/lib/dates';

import type { RiderTrip } from './api';

/** The stop her map is about: where she gets on, and once she is on board, where she gets off. */
export function focusOf(trip: RiderTrip): { myStopId: number; focusLabel: string } {
  return trip.pickup === 'picked_up' && !trip.alighted_at
    ? { myStopId: trip.off_stop_id, focusLabel: 'GET OFF HERE' }
    : { myStopId: trip.my_stop_id, focusLabel: 'YOUR STOP' };
}

export function headline(trip: RiderTrip): string {
  if (trip.alighted_at) {
    return 'You got down';
  }

  if (trip.pickup === 'picked_up') {
    // Counting down to where she gets off, so she is ready before the van stops.
    if (trip.stops_to_off === 0) {
      return `You're at ${trip.off_stop}`;
    }

    return trip.stops_to_off === 1 ? 'Your stop is next' : 'You are on board';
  }

  if (trip.status === 'ended') {
    return "Today's trip has ended";
  }

  if (trip.status === 'not_started') {
    return `Due at ${clock(trip.my_time)}`;
  }

  if (trip.pickup === 'missed') {
    return 'Marked as not at the stop';
  }

  // Minutes, when there is a Google key and a live position. Otherwise stops, which always work.
  if (trip.minutes_away) {
    return `About ${trip.minutes_away} min away`;
  }

  if (trip.stops_away === null) {
    return 'On its way';
  }

  return trip.stops_away === 0 ? 'At your stop' : `${trip.stops_away} ${trip.stops_away === 1 ? 'stop' : 'stops'} away`;
}

export function detail(trip: RiderTrip): string {
  if (trip.alighted_at) {
    return `${trip.route}. Car ${trip.car_number}. Have a good day.`;
  }

  if (trip.pickup === 'picked_up' && trip.status === 'running') {
    if (trip.stops_to_off === 0) {
      return `Get down here. Tap "I've got down" in Ride options when you're off.`;
    }

    if (trip.stops_to_off === 1) {
      return `Get ready to get down at ${trip.off_stop}.`;
    }

    const togo = trip.stops_to_off ? ` ${trip.stops_to_off} stops to go.` : '';

    return `Getting off at ${trip.off_stop}, about ${clock(trip.off_time)}.${togo}`;
  }

  if (trip.status === 'not_started') {
    return `The van has not set off yet. Be at ${trip.my_stop} by ${clock(trip.my_time)}. We will tell you when it leaves.`;
  }

  if (trip.status === 'ended') {
    return `${trip.route}. Car ${trip.car_number}.`;
  }

  if (trip.pickup === 'missed') {
    return 'The mate did not find you at your stop. If that is wrong, tell us under Help.';
  }

  const seen = trip.position
    ? trip.position.live
      ? 'Live.'
      : "The van's phones have lost their network, so this may be behind."
    : 'No position from the van yet.';

  const stops = trip.minutes_away && trip.stops_away ? ` ${trip.stops_away} ${trip.stops_away === 1 ? 'stop' : 'stops'} before yours.` : '';

  return `Car ${trip.car_number} to ${trip.my_stop}.${stops} ${seen}`;
}

/**
 * Task D5 on the home screen: how far her van is, one card for each of her trips today.
 * The home screen loads and polls the trips, since its map shows the same ones. With `showMap`, each running
 * trip also gets a small live map on its card, for screens that have no map of their own.
 */
export function TodaysTrips({
  trips,
  showMap = false,
  onOptions,
}: {
  trips: RiderTrip[];
  showMap?: boolean;
  onOptions?: (index: number) => void;
}) {
  const router = useRouter();
  const { can } = useAuth();
  // One trip is shown in full: the one on the road, or else the next to come. Others today are a quiet line each.
  const running = trips.findIndex((trip) => trip.status === 'running' && trip.alighted_at === null);
  const current = running >= 0 ? running : trips.findIndex((trip) => trip.status === 'not_started');

  return (
    <>
      {trips.map((trip, index) =>
        index !== current ? (
          <MutedText key={`${trip.route}-${trip.my_time}`}>
            {trip.status === 'not_started'
              ? `Later today · your van is due at ${trip.my_stop}, ${clock(trip.my_time)}`
              : `✓ ${trip.alighted_at ? 'You got down' : trip.pickup === 'picked_up' ? 'Trip done' : 'Trip over'} · ${trip.route}`}
          </MutedText>
        ) : (
          <Card key={`${trip.route}-${trip.my_time}`} title="Your van today">
            <ThemedText type="subtitle">{headline(trip)}</ThemedText>
            <MutedText>{detail(trip)}</MutedText>
            {onOptions ? (
              // On the map home everything she can do about the ride is in one floating menu.
              trip.status !== 'ended' || trip.pickup === 'picked_up' ? (
                <Button label="Ride options" variant="plain" onPress={() => onOptions(index)} />
              ) : null
            ) : trip.map_url && trip.status === 'running' ? (
              <>
                {showMap ? (
                  <View style={styles.map}>
                    <TripMap
                      stops={trip.stops}
                      path={trip.path}
                      trail={trip.trail}
                      position={trip.position}
                      {...focusOf(trip)}
                      vanLabel={trip.car_number}
                      mapUrl={trip.map_url}
                      interactive={false}
                    />
                  </View>
                ) : null}
                <Button label="See the stops" onPress={() => router.push({ pathname: '/trip', params: { index: String(index) } })} />
                {can('trips.share') ? (
                  <Button
                    label="Share my trip"
                    variant="plain"
                    onPress={() => void Share.share({ message: `Follow my Aveo van: ${(trip.map_url ?? '').split('?')[0]}` })}
                  />
                ) : null}
              </>
            ) : null}
          </Card>
        ),
      )}
    </>
  );
}

const styles = StyleSheet.create({
  // A small live map right on the card, for screens without a map behind them.
  map: { height: 220, borderRadius: 12, overflow: 'hidden' },
});

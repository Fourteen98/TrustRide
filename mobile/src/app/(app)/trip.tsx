import { useLocalSearchParams } from 'expo-router';
import { ScrollView, StyleSheet, View } from 'react-native';

import { Card } from '@/components/card';
import { Loadable } from '@/components/loadable';
import { MutedText } from '@/components/muted-text';
import { Notice } from '@/components/notice';
import { ThemedText } from '@/components/themed-text';
import { TripMap } from '@/components/trip-map';
import { Spacing } from '@/constants/theme';
import { useLoader } from '@/hooks/use-loader';
import { usePoll } from '@/hooks/use-poll';
import { useTheme } from '@/hooks/use-theme';
import { riderApi } from '@/rider/api';
import { detail, focusOf, headline } from '@/rider/trip-card';

/**
 * Task D5. Her van on a full-screen map, with how far away it is and the stops in order underneath.
 * It asks again every 10 seconds, and the van glides between positions.
 */
export default function TripMapScreen() {
  const theme = useTheme();
  const { index } = useLocalSearchParams<{ index?: string }>();
  const state = useLoader(riderApi.trips);
  const trip = state.data?.[Number(index ?? 0)] ?? null;

  usePoll(state.reload, trip?.status === 'running', 10000);

  return (
    <Loadable state={state}>
      {() =>
        !trip ? (
          <Notice text="There is no trip to show right now. Go back and try again." />
        ) : (
          <View style={styles.fill}>
            <View style={styles.fill}>
              <TripMap
                stops={trip.stops}
                path={trip.path}
                trail={trip.trail}
                position={trip.position}
                {...focusOf(trip)}
                mapUrl={trip.map_url}
              />
            </View>
            <ScrollView style={[styles.panel, { backgroundColor: theme.background }]} contentContainerStyle={styles.panelContent}>
              <Card title={trip.route}>
                <ThemedText type="subtitle">{headline(trip)}</ThemedText>
                <MutedText>{detail(trip)}</MutedText>
              </Card>
              {trip.stops.map((stop) => (
                <View key={stop.id} style={styles.stop}>
                  <ThemedText type={stop.id === trip.my_stop_id || stop.id === trip.off_stop_id ? 'smallBold' : 'small'}>
                    {stop.reached ? '✓ ' : '○ '}
                    {stop.name}
                    {stop.id === trip.my_stop_id ? ' (you get on)' : stop.id === trip.off_stop_id ? ' (you get off)' : ''}
                  </ThemedText>
                  <MutedText>{stop.time ?? ''}</MutedText>
                </View>
              ))}
            </ScrollView>
          </View>
        )
      }
    </Loadable>
  );
}

const styles = StyleSheet.create({
  fill: { flex: 1 },
  panel: { flexGrow: 0, maxHeight: '45%' },
  panelContent: { padding: Spacing.three, gap: Spacing.two },
  stop: { flexDirection: 'row', justifyContent: 'space-between' },
});

import { useState } from 'react';
import { ScrollView, StyleSheet, View } from 'react-native';

import { Card } from '@/components/card';
import { Choice } from '@/components/choice';
import { ChoiceGroup } from '@/components/choice-group';
import { Loadable } from '@/components/loadable';
import { MutedText } from '@/components/muted-text';
import { ThemedText } from '@/components/themed-text';
import { TripMap } from '@/components/trip-map';
import { Spacing } from '@/constants/theme';
import { useLoader } from '@/hooks/use-loader';
import { useTheme } from '@/hooks/use-theme';
import { clock } from '@/lib/dates';
import { riderApi } from '@/rider/api';
import { firstRoute } from '@/rider/use-rider-map';

/**
 * Her route full size, any time of day: her own position, the road, every stop with its time, and her stop in red.
 * With seats on more than one trip, such as there and back, she can switch between them.
 */
export default function RouteMapScreen() {
  const theme = useTheme();
  const state = useLoader(riderApi.routeMap);
  const [picked, setPicked] = useState<number | null>(null);

  return (
    <Loadable state={state}>
      {(routes) => {
        const route = picked !== null ? (routes[picked] ?? null) : firstRoute(routes);

        return (
          <View style={styles.fill}>
            <View style={styles.fill}>
              <TripMap
                key={route ? `${route.route}-${route.direction}` : 'me'}
                stops={route?.stops ?? []}
                path={route?.path ?? null}
                position={null}
                myStopId={route?.my_stop_id}
              />
            </View>
            <ScrollView style={[styles.panel, { backgroundColor: theme.background }]} contentContainerStyle={styles.panelContent}>
              {routes.length > 1 ? (
                <ChoiceGroup title="Which trip" layout="chips">
                  {routes.map((item, index) => (
                    <Choice
                      key={`${item.route}-${item.direction}`}
                      shape="chip"
                      label={`${clock(item.my_time)} ${item.direction === 'outbound' ? 'there' : 'back'}`}
                      selected={item === route}
                      onPress={() => setPicked(index)}
                    />
                  ))}
                </ChoiceGroup>
              ) : null}
              {route ? (
                <>
                  <Card title={route.route}>
                    <MutedText>{`Your stop is ${route.my_stop}${route.my_time ? `, the van is due at ${clock(route.my_time)}` : ''}.`}</MutedText>
                  </Card>
                  {route.stops.map((stop) => (
                    <View key={stop.id} style={styles.stop}>
                      <ThemedText type={stop.id === route.my_stop_id ? 'smallBold' : 'small'}>
                        {stop.name}
                        {stop.id === route.my_stop_id ? ' (your stop)' : ''}
                      </ThemedText>
                      <MutedText>{clock(stop.time)}</MutedText>
                    </View>
                  ))}
                </>
              ) : (
                <Card title="Where you are">
                  <MutedText>The blue dot is you. Once you have a seat, your route and your stop show here.</MutedText>
                </Card>
              )}
            </ScrollView>
          </View>
        );
      }}
    </Loadable>
  );
}

const styles = StyleSheet.create({
  fill: { flex: 1 },
  panel: { flexGrow: 0, maxHeight: '45%' },
  panelContent: { padding: Spacing.three, gap: Spacing.two },
  stop: { flexDirection: 'row', justifyContent: 'space-between' },
});

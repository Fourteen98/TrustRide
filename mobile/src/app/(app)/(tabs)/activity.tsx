import { useState } from 'react';
import { StyleSheet, View } from 'react-native';

import { Button } from '@/components/button';
import { Card } from '@/components/card';
import { Loadable } from '@/components/loadable';
import { MutedText } from '@/components/muted-text';
import { Notice } from '@/components/notice';
import { Screen } from '@/components/screen';
import { ThemedText } from '@/components/themed-text';
import { useAction } from '@/hooks/use-action';
import { useLoader } from '@/hooks/use-loader';
import { moment, shortDate } from '@/lib/dates';
import { riderApi, type PastTrip } from '@/rider/api';

const OUTCOME: Record<PastTrip['outcome'], string> = {
  picked_up: 'On board',
  missed: 'Not at the stop',
  not_riding: 'You said you would not ride',
  not_marked: 'Not marked by the mate',
};

/** Her past trips, newest first: the day, the van, her stop and what happened there. 20 at a time. */
export default function HistoryScreen() {
  const first = useLoader(() => riderApi.history(1));
  const more = useAction();
  const [extra, setExtra] = useState<{
    trips: PastTrip[];
    page: number;
    hasMore: boolean;
  } | null>(null);

  const loadMore = (hasMore: boolean) => {
    const page = (extra?.page ?? 1) + 1;

    if (!hasMore) {
      return;
    }

    void more.run(async () => {
      const data = await riderApi.history(page);
      setExtra({
        trips: [...(extra?.trips ?? []), ...data.trips],
        page,
        hasMore: data.has_more,
      });
    });
  };

  return (
    <Screen
      edges={['left', 'right']}
      refreshing={first.refreshing}
      onRefresh={() => {
        setExtra(null);
        void first.reload();
      }}>
      <Loadable state={first}>
        {(data) => {
          const trips = [...data.trips, ...(extra?.trips ?? [])];
          const hasMore = extra ? extra.hasMore : data.has_more;

          return trips.length === 0 ? (
            <Card title="No trips yet">
              <MutedText>Once your van has run on a day you paid for, the trip shows here.</MutedText>
            </Card>
          ) : (
            <>
              {trips.map((trip) => (
                <Card
                  key={`${trip.date}-${trip.direction}-${trip.started_at}`}
                  title={`${shortDate(trip.date)}, ${moment(trip.started_at).split(', ')[1]} ${trip.direction === 'outbound' ? 'there' : 'back'}`}>
                  <View style={styles.row}>
                    <ThemedText type="smallBold">{OUTCOME[trip.outcome]}</ThemedText>
                    {trip.status === 'running' ? <MutedText>On the road now</MutedText> : null}
                  </View>
                  <MutedText>
                    {trip.route}, car {trip.car_number}
                    {trip.my_stop ? `, from ${trip.my_stop}` : ''}.
                    {trip.picked_up_at ? ` Picked up ${moment(trip.picked_up_at).split(', ')[1]}.` : ''}
                  </MutedText>
                </Card>
              ))}
              <Notice text={more.error} />
              {hasMore ? <Button label="Show older trips" variant="plain" loading={more.busy} onPress={() => loadMore(hasMore)} /> : null}
            </>
          );
        }}
      </Loadable>
    </Screen>
  );
}

const styles = StyleSheet.create({
  row: { flexDirection: 'row', justifyContent: 'space-between' },
});

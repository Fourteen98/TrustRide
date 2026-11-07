import { useLocalSearchParams, useRouter } from 'expo-router';
import { useState } from 'react';
import { ActivityIndicator, Pressable, StyleSheet, View } from 'react-native';

import { Card } from '@/components/card';
import { Loadable } from '@/components/loadable';
import { MutedText } from '@/components/muted-text';
import { Notice } from '@/components/notice';
import { Screen } from '@/components/screen';
import { SearchField } from '@/components/search-field';
import { ThemedText } from '@/components/themed-text';
import { Spacing } from '@/constants/theme';
import { useLoader } from '@/hooks/use-loader';
import { useMyPosition } from '@/hooks/use-my-position';
import { useSearch } from '@/hooks/use-search';
import { useTheme } from '@/hooks/use-theme';
import { riderApi, type FoundRun, type RouteChoice } from '@/rider/api';
import { RunResult } from '@/rider/run-result';

/**
 * Where to? She types a place and sees every Aveo van that goes there: where she would get on (the stop
 * nearest her), when, where she gets down, and whether seats are free. One tap starts an application with it
 * all filled in. Before she types, the places we go are listed to tap.
 */
export default function RoutesScreen() {
  const router = useRouter();
  const theme = useTheme();
  const { focus } = useLocalSearchParams<{ focus?: string }>();
  const [text, setText] = useState('');
  const near = useMyPosition();
  const found = useSearch(text, (to) => riderApi.search(to, near));
  const places = useLoader(riderApi.routes);

  const ask = (run: FoundRun) =>
    router.push({
      pathname: '/apply',
      params: {
        route_id: String(run.route_id),
        stop_id: String(run.apply_stop_id),
        [run.direction === 'outbound' ? 'morning_time' : 'evening_time']: run.departs_at,
      },
    });

  const searching = text.trim().length >= 2;

  return (
    <Screen edges={['top', 'left', 'right']}>
      <ThemedText type="title" style={styles.title}>
        Where to?
      </ThemedText>
      <View style={styles.row}>
        <SearchField value={text} onChangeText={setText} autoFocus={focus === '1'} placeholder="A stop, a landmark, a place" />
      </View>

      {searching ? (
        <>
          <Notice text={found.error} />
          {found.loading ? <ActivityIndicator color={theme.primary} /> : null}
          {found.data && found.data.length === 0 ? (
            <Card title={`No van goes to "${text.trim()}" yet`}>
              <MutedText>
                Try the name of a stop or a nearby landmark. We are adding routes, so ask Help to tell us where you need to go.
              </MutedText>
            </Card>
          ) : null}
          {found.data?.map((run) => (
            <RunResult key={`${run.run_id}-${run.alight.stop_id}`} run={run} onAsk={() => ask(run)} />
          ))}
        </>
      ) : (
        <Loadable state={places}>{(routes) => <Places routes={routes} onPick={setText} />}</Loadable>
      )}
    </Screen>
  );
}

/** The places we go, as chips. Tapping one searches for it. */
function Places({ routes, onPick }: { routes: RouteChoice[]; onPick: (place: string) => void }) {
  const theme = useTheme();
  const names = [...new Set(routes.flatMap((route) => [route.destination, ...route.stops.map((stop) => stop.name)]))];

  if (names.length === 0) {
    return <Notice tone="info" text="We're not running any routes yet. Check back soon." />;
  }

  return (
    <>
      <ThemedText type="smallBold">Places we go</ThemedText>
      <View style={styles.chips}>
        {names.map((name) => (
          <Pressable
            key={name}
            accessibilityRole="button"
            onPress={() => onPick(name)}
            style={({ pressed }) => [styles.chip, { borderColor: theme.border, opacity: pressed ? 0.7 : 1 }]}>
            <ThemedText type="small">{name}</ThemedText>
          </Pressable>
        ))}
      </View>
      <MutedText>{routes.map((route) => route.name).join(' · ')}</MutedText>
    </>
  );
}

const styles = StyleSheet.create({
  title: { fontSize: 32, lineHeight: 38 },
  row: { flexDirection: 'row' },
  chips: { flexDirection: 'row', flexWrap: 'wrap', gap: Spacing.two },
  chip: { borderWidth: 1, borderRadius: 999, paddingVertical: Spacing.two, paddingHorizontal: Spacing.three },
});

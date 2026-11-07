import { useState } from 'react';
import { Pressable, StyleSheet, View } from 'react-native';

import { MutedText } from '@/components/muted-text';
import { ThemedText } from '@/components/themed-text';
import { Spacing } from '@/constants/theme';
import { useTheme } from '@/hooks/use-theme';
import { clock } from '@/lib/dates';

import type { CrewRun, CrewStop } from './api';
import { StopBlock } from './run-card';

type Props = {
  run: CrewRun;
  /** The stop shown big above this list. It is left out here. */
  nextId: number | null;
  canMark: boolean;
  onMark: (riderId: number, status: 'picked_up' | 'missed') => void;
};

/** How many people get on at a stop, counting those with each rider. */
export const gettingOn = (stop: CrewStop) =>
  stop.riders
    .filter((rider) => !rider.absent && rider.pickup !== 'missed')
    .reduce((sum, rider) => sum + 1 + rider.on_plan + rider.paying_fare, 0);

/** How many people get off at a stop. */
export const gettingOff = (stop: CrewStop) => stop.getting_off.reduce((sum, rider) => sum + rider.people, 0);

/**
 * The rest of the run, quietly: stops already done fold into one line, stops still ahead are grey with their time and
 * how many get on (↑) and off (↓) there, and the route's end closes the list. Only the next stop, above, is shown in full.
 * Tapping a grey stop opens it, for the rare rider who has to be marked out of turn.
 */
export function StopTimeline({ run, nextId, canMark, onMark }: Props) {
  const theme = useTheme();
  const [open, setOpen] = useState<number | null>(null);
  const [showDone, setShowDone] = useState(false);

  const done = run.stops.filter((stop) => stop.reached && stop.stop_id !== nextId);
  const ahead = run.stops.filter((stop) => !stop.reached && stop.stop_id !== nextId);
  // The end of the route closes the list. It is a stop like the others when someone gets off there.
  const end = run.map.stops.at(-1) ?? null;
  const endListed = end !== null && run.stops.some((stop) => stop.stop_id === end.id);
  const onBoard = run.on_board.reduce((sum, rider) => sum + rider.people, 0);

  const row = (stop: CrewStop, faded: boolean) => {
    const on = gettingOn(stop);
    const off = gettingOff(stop);
    const expanded = open === stop.stop_id;

    return (
      <View key={stop.stop_id}>
        <Pressable
          accessibilityRole="button"
          accessibilityState={{ expanded }}
          accessibilityLabel={`${stop.name}, ${clock(stop.time)}, ${on} getting on, ${off} getting off`}
          onPress={() => setOpen(expanded ? null : stop.stop_id)}
          style={styles.row}>
          <View style={styles.rail}>
            <View style={[styles.line, { backgroundColor: theme.border }]} />
            <View style={[styles.dot, { borderColor: faded ? theme.border : theme.textSecondary, backgroundColor: theme.background }]} />
          </View>
          <ThemedText type="small" themeColor="textSecondary" style={[styles.name, faded && styles.faded]} numberOfLines={1}>
            {stop.reached ? '✓ ' : ''}
            {stop.name}
          </ThemedText>
          {on > 0 ? <Count text={`↑${on}`} /> : null}
          {off > 0 ? <Count text={`↓${off}`} /> : null}
          <MutedText>{clock(stop.time)}</MutedText>
        </Pressable>
        {expanded ? (
          <View style={styles.open}>
            <StopBlock stop={stop} canMark={canMark} onMark={onMark} />
          </View>
        ) : null}
      </View>
    );
  };

  return (
    <View style={styles.list}>
      {done.length > 0 ? (
        <Pressable
          accessibilityRole="button"
          accessibilityState={{ expanded: showDone }}
          onPress={() => setShowDone(!showDone)}
          style={styles.done}>
          <MutedText>
            ✓ {done.length} {done.length === 1 ? 'stop' : 'stops'} done · {onBoard} on board
          </MutedText>
          <MutedText>{showDone ? 'Hide' : 'Show'}</MutedText>
        </Pressable>
      ) : null}
      {showDone ? done.map((stop) => row(stop, true)) : null}

      {ahead.length > 0 ? (
        <ThemedText type="small" themeColor="textSecondary" style={styles.heading}>
          COMING UP
        </ThemedText>
      ) : null}
      {ahead.map((stop) => row(stop, false))}

      {end && !endListed ? (
        <View style={styles.row}>
          <View style={styles.rail}>
            <View style={[styles.square, { backgroundColor: theme.textSecondary }]} />
          </View>
          <ThemedText type="small" themeColor="textSecondary" style={styles.name}>
            {end.name} · end of the route
          </ThemedText>
          <MutedText>{clock(end.time)}</MutedText>
        </View>
      ) : null}
    </View>
  );
}

function Count({ text }: { text: string }) {
  const theme = useTheme();

  return (
    <View style={[styles.count, { backgroundColor: theme.backgroundElement }]}>
      <ThemedText type="small" themeColor="textSecondary">
        {text}
      </ThemedText>
    </View>
  );
}

const styles = StyleSheet.create({
  list: { gap: 0 },
  heading: { letterSpacing: 1.2, fontSize: 12, marginTop: Spacing.two, marginBottom: Spacing.one },
  done: { flexDirection: 'row', justifyContent: 'space-between', paddingVertical: Spacing.two },
  row: { flexDirection: 'row', alignItems: 'center', gap: Spacing.two, minHeight: 40 },
  rail: { width: 16, alignSelf: 'stretch', alignItems: 'center', justifyContent: 'center' },
  line: { position: 'absolute', top: 0, bottom: 0, width: 2 },
  dot: { width: 10, height: 10, borderRadius: 5, borderWidth: 2 },
  square: { width: 10, height: 10, borderRadius: 2 },
  name: { flex: 1 },
  faded: { opacity: 0.6 },
  count: { minWidth: 30, height: 22, borderRadius: 11, paddingHorizontal: 6, alignItems: 'center', justifyContent: 'center' },
  open: { paddingLeft: 24, paddingBottom: Spacing.two },
});

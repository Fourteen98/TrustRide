import { StyleSheet, View } from 'react-native';

import { Button } from '@/components/button';
import { MutedText } from '@/components/muted-text';
import { ThemedText } from '@/components/themed-text';
import { Spacing } from '@/constants/theme';
import { useTheme } from '@/hooks/use-theme';
import { clock, weekdays } from '@/lib/dates';

import type { FoundRun } from './api';

function distance(metres: number | null): string {
  if (metres === null) {
    return '';
  }

  return metres < 1000 ? ` · ${Math.round(metres / 10) * 10} m from you` : ` · ${(metres / 1000).toFixed(1)} km from you`;
}

/**
 * One van that goes where she searched: where she gets on and when, where she gets down and when,
 * the days it runs and whether seats are free. The two stops are drawn as a short line, like a ticket.
 */
export function RunResult({ run, onAsk }: { run: FoundRun; onAsk: () => void }) {
  const theme = useTheme();

  return (
    <View style={[styles.card, { backgroundColor: theme.backgroundElement }]}>
      <View style={styles.top}>
        <ThemedText type="smallBold">{run.route}</ThemedText>
        <MutedText>Leaves {clock(run.departs_at)}</MutedText>
      </View>

      <View style={styles.legs}>
        <View style={styles.rail}>
          <View style={[styles.dot, { borderColor: theme.primary }]} />
          <View style={[styles.line, { backgroundColor: theme.border }]} />
          <View style={[styles.dot, styles.end, { backgroundColor: theme.primary, borderColor: theme.primary }]} />
        </View>
        <View style={styles.stops}>
          <View>
            <ThemedText type="smallBold">
              {clock(run.board.time)} · Get on at {run.board.name}
            </ThemedText>
            <MutedText>
              {run.board.landmark ?? ''}
              {distance(run.board.metres_away)}
            </MutedText>
          </View>
          <View>
            <ThemedText type="smallBold">
              {clock(run.alight.time)} · Get down at {run.alight.name}
            </ThemedText>
            {run.alight.landmark ? <MutedText>{run.alight.landmark}</MutedText> : null}
          </View>
        </View>
      </View>

      <View style={styles.top}>
        <MutedText>{weekdays(run.days)}</MutedText>
        <ThemedText type="small" style={{ color: run.seats_free > 0 ? theme.primaryText : theme.textSecondary }}>
          {run.seats_free > 0 ? `${run.seats_free} ${run.seats_free === 1 ? 'seat' : 'seats'} free` : 'Full, you can still ask'}
        </ThemedText>
      </View>
      <Button label="Ask for this seat" onPress={onAsk} />
    </View>
  );
}

const styles = StyleSheet.create({
  card: { borderRadius: 18, padding: Spacing.three, gap: Spacing.three },
  top: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', gap: Spacing.two },
  legs: { flexDirection: 'row', gap: Spacing.three },
  rail: { alignItems: 'center', paddingVertical: 6, width: 14 },
  dot: { width: 12, height: 12, borderRadius: 6, borderWidth: 3 },
  end: { borderRadius: 2 },
  line: { flex: 1, width: 2, marginVertical: 2 },
  stops: { flex: 1, gap: Spacing.three },
});

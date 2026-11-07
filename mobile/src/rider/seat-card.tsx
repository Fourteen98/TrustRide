import { Pressable, StyleSheet, View } from 'react-native';

import { Detail } from '@/components/detail';
import { MutedText } from '@/components/muted-text';
import { ThemedText } from '@/components/themed-text';
import { Spacing } from '@/constants/theme';
import { useTheme } from '@/hooks/use-theme';
import { clock, weekdays } from '@/lib/dates';

import type { Seat } from './api';

/**
 * Her seat, as a ticket: when and where on the top half, the car and the driver on the stub below the tear line.
 * This is what she looks at standing at the stop, and the one thing on the home screen that is only Aveo's.
 */
export function SeatCard({ seat, daysLeft, onChangeStop }: { seat: Seat; daysLeft?: number | null; onChangeStop?: () => void }) {
  const theme = useTheme();
  const reserved = seat.status === 'reserved';
  const stamp = reserved ? 'Reserved' : daysLeft ? `Paid · ${daysLeft} ${daysLeft === 1 ? 'day' : 'days'} left` : 'Paid';

  return (
    <View style={[styles.ticket, { backgroundColor: theme.backgroundElement }]}>
      <View style={styles.part}>
        <View style={styles.row}>
          <ThemedText type="small" themeColor="textSecondary" style={styles.kicker}>
            {seat.heading_to ? `SEAT TO ${seat.heading_to.toUpperCase()}` : `${clock(seat.time)} SEAT`}
          </ThemedText>
          <View style={[styles.stamp, { borderColor: reserved ? theme.border : theme.primary }]}>
            <ThemedText type="small" style={{ color: reserved ? theme.textSecondary : theme.primaryText }}>
              {stamp}
            </ThemedText>
          </View>
        </View>

        <View style={styles.when}>
          <ThemedText style={styles.time}>{clock(seat.time)}</ThemedText>
          <View style={styles.grow}>
            <ThemedText type="smallBold">{seat.stop}</ThemedText>
            {seat.landmark ? <MutedText>{seat.landmark}</MutedText> : null}
          </View>
        </View>
        <View style={styles.row}>
          <View style={styles.grow}>
            <MutedText>
              Get off at {seat.gets_off}, about {clock(seat.off_time)}
            </MutedText>
          </View>
          {onChangeStop ? (
            <Pressable
              accessibilityRole="button"
              accessibilityLabel={`Change where you get off, now ${seat.gets_off}`}
              onPress={onChangeStop}
              hitSlop={8}>
              <ThemedText type="smallBold" style={{ color: theme.primaryText }}>
                Change
              </ThemedText>
            </Pressable>
          ) : null}
        </View>
        <MutedText>
          {seat.route}
          {seat.party > 1 ? ` · For ${seat.party} people on your plan` : ''}
        </MutedText>
      </View>

      {/* The tear line: a dashed rule with a notch bitten out of each side. */}
      <View style={styles.tear}>
        <View style={[styles.notch, styles.notchLeft, { backgroundColor: theme.background }]} />
        <View style={[styles.dashes, { borderColor: theme.border }]} />
        <View style={[styles.notch, styles.notchRight, { backgroundColor: theme.background }]} />
      </View>

      <View style={[styles.part, styles.row]}>
        <Detail label="Car" style={styles.half}>
          <ThemedText style={styles.plate}>{seat.car_number}</ThemedText>
          <ThemedText type="small">{[seat.car_colour, seat.car_make].filter(Boolean).join(' ')}</ThemedText>
        </Detail>
        <Detail label="Driver" style={styles.half}>
          <ThemedText type="smallBold">{seat.driver_name}</ThemedText>
          <ThemedText type="small">{weekdays(seat.days)}</ThemedText>
        </Detail>
      </View>
    </View>
  );
}

const NOTCH = 18;

const styles = StyleSheet.create({
  ticket: { borderRadius: 18, overflow: 'hidden' },
  part: { padding: Spacing.three, gap: Spacing.two },
  row: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: Spacing.three },
  grow: { flex: 1 },
  kicker: { letterSpacing: 1.2, fontSize: 12 },
  stamp: { borderWidth: 1.5, borderRadius: 999, paddingHorizontal: Spacing.two, paddingVertical: 2 },
  when: { flexDirection: 'row', alignItems: 'center', gap: Spacing.three },
  time: { fontSize: 34, lineHeight: 40, fontWeight: 700 },
  tear: { height: NOTCH, justifyContent: 'center' },
  dashes: { marginHorizontal: NOTCH, borderTopWidth: 1.5, borderStyle: 'dashed' },
  notch: { position: 'absolute', width: NOTCH, height: NOTCH, borderRadius: NOTCH / 2, top: 0 },
  notchLeft: { left: -NOTCH / 2 },
  notchRight: { right: -NOTCH / 2 },
  half: { flex: 1, gap: 0 },
  plate: { fontSize: 20, lineHeight: 26, fontWeight: 700, letterSpacing: 1 },
});

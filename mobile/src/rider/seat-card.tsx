import { StyleSheet, View } from 'react-native';

import { Detail } from '@/components/detail';
import { MutedText } from '@/components/muted-text';
import { ThemedText } from '@/components/themed-text';
import { Spacing } from '@/constants/theme';
import { useTheme } from '@/hooks/use-theme';
import { clock, weekdays } from '@/lib/dates';

import type { Seat } from './api';

/** Her seat: when, where, which car and who is driving. This is what she looks at standing at the stop. */
export function SeatCard({ seat }: { seat: Seat }) {
  const theme = useTheme();
  const reserved = seat.status === 'reserved';

  return (
    <View style={[styles.card, { backgroundColor: theme.backgroundElement }]}>
      <MutedText>
        {seat.direction === 'outbound' ? 'Morning' : 'Evening'} · {seat.route}
        {reserved ? ' · Reserved, not paid yet' : ''}
      </MutedText>

      <View style={styles.when}>
        <ThemedText style={styles.time}>{clock(seat.time)}</ThemedText>
        <View style={{ flex: 1 }}>
          <ThemedText type="smallBold">{seat.stop}</ThemedText>
          {seat.landmark ? <MutedText>{seat.landmark}</MutedText> : null}
        </View>
      </View>

      <View style={[styles.car, { borderColor: theme.border }]}>
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

const styles = StyleSheet.create({
  card: { borderRadius: 16, padding: Spacing.three, gap: Spacing.three },
  when: { flexDirection: 'row', alignItems: 'center', gap: Spacing.three },
  time: { fontSize: 32, lineHeight: 38, fontWeight: 700 },
  car: { flexDirection: 'row', gap: Spacing.three, borderTopWidth: 1, paddingTop: Spacing.three },
  half: { flex: 1, gap: 0 },
  plate: { fontSize: 20, lineHeight: 26, fontWeight: 700, letterSpacing: 0.5 },
});

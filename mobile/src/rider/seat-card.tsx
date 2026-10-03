import { StyleSheet, View } from 'react-native';

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
      <ThemedText type="small" themeColor="textSecondary">
        {seat.direction === 'outbound' ? 'Morning' : 'Evening'} · {seat.route}
        {reserved ? ' · Reserved, not paid yet' : ''}
      </ThemedText>

      <View style={styles.when}>
        <ThemedText style={styles.time}>{clock(seat.time)}</ThemedText>
        <View style={{ flex: 1 }}>
          <ThemedText type="smallBold">{seat.stop}</ThemedText>
          {seat.landmark ? (
            <ThemedText type="small" themeColor="textSecondary">
              {seat.landmark}
            </ThemedText>
          ) : null}
        </View>
      </View>

      <View style={[styles.car, { borderColor: theme.border }]}>
        <View style={{ flex: 1 }}>
          <ThemedText type="small" themeColor="textSecondary">
            Car
          </ThemedText>
          <ThemedText style={styles.plate}>{seat.car_number}</ThemedText>
          <ThemedText type="small">{[seat.car_colour, seat.car_make].filter(Boolean).join(' ')}</ThemedText>
        </View>
        <View style={{ flex: 1 }}>
          <ThemedText type="small" themeColor="textSecondary">
            Driver
          </ThemedText>
          <ThemedText type="smallBold">{seat.driver_name}</ThemedText>
          <ThemedText type="small">{weekdays(seat.days)}</ThemedText>
        </View>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  card: { borderRadius: 16, padding: Spacing.three, gap: Spacing.three },
  when: { flexDirection: 'row', alignItems: 'center', gap: Spacing.three },
  time: { fontSize: 32, lineHeight: 38, fontWeight: 700 },
  car: { flexDirection: 'row', gap: Spacing.three, borderTopWidth: 1, paddingTop: Spacing.three },
  plate: { fontSize: 20, lineHeight: 26, fontWeight: 700, letterSpacing: 0.5 },
});

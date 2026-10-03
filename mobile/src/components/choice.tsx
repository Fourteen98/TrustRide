import { Pressable, StyleSheet, View } from 'react-native';

import { Spacing } from '@/constants/theme';
import { useTheme } from '@/hooks/use-theme';

import { ThemedText } from './themed-text';

type Props = {
  label: string;
  detail?: string | null;
  selected: boolean;
  onPress: () => void;
  /** A chip sits in a row with others. A row takes the full width. */
  shape?: 'row' | 'chip';
};

/** One option the person can pick. Used for routes, stops, times and days. */
export function Choice({ label, detail, selected, onPress, shape = 'row' }: Props) {
  const theme = useTheme();

  return (
    <Pressable
      accessibilityRole="radio"
      accessibilityState={{ selected }}
      accessibilityLabel={detail ? `${label}, ${detail}` : label}
      onPress={onPress}
      style={[
        shape === 'row' ? styles.row : styles.chip,
        { borderColor: selected ? theme.primary : theme.border },
        selected && { backgroundColor: theme.backgroundSelected },
      ]}>
      <View style={shape === 'row' ? styles.text : undefined}>
        <ThemedText type="smallBold">{label}</ThemedText>
        {detail ? (
          <ThemedText type="small" themeColor="textSecondary">
            {detail}
          </ThemedText>
        ) : null}
      </View>
      {shape === 'row' ? (
        <View style={[styles.dot, { borderColor: selected ? theme.primary : theme.border }]}>
          {selected ? <View style={[styles.dotFill, { backgroundColor: theme.primary }]} /> : null}
        </View>
      ) : null}
    </Pressable>
  );
}

const styles = StyleSheet.create({
  row: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: Spacing.three,
    minHeight: 56,
    borderWidth: 1.5,
    borderRadius: 12,
    paddingVertical: Spacing.two,
    paddingHorizontal: Spacing.three,
  },
  chip: {
    minHeight: 44,
    justifyContent: 'center',
    borderWidth: 1.5,
    borderRadius: 12,
    paddingVertical: Spacing.two,
    paddingHorizontal: Spacing.three,
  },
  text: { flex: 1 },
  dot: { width: 22, height: 22, borderRadius: 11, borderWidth: 2, alignItems: 'center', justifyContent: 'center' },
  dotFill: { width: 10, height: 10, borderRadius: 5 },
});

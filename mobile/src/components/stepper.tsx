import { Pressable, StyleSheet, View } from 'react-native';

import { Spacing } from '@/constants/theme';
import { useTheme } from '@/hooks/use-theme';

import { ThemedText } from './themed-text';

type Props = {
  label: string;
  hint?: string;
  value: number;
  min: number;
  max: number;
  onChange: (value: number) => void;
};

/** A number picked with − and + buttons, such as how many people travel with her. */
export function Stepper({ label, hint, value, min, max, onChange }: Props) {
  const theme = useTheme();

  const button = (text: string, next: number, disabled: boolean) => (
    <Pressable
      accessibilityRole="button"
      accessibilityLabel={`${text === '−' ? 'Fewer' : 'More'}, ${label}`}
      accessibilityState={{ disabled }}
      disabled={disabled}
      onPress={() => onChange(next)}
      style={[styles.button, { borderColor: theme.border, opacity: disabled ? 0.35 : 1 }]}>
      <ThemedText type="subtitle">{text}</ThemedText>
    </Pressable>
  );

  return (
    <View style={styles.wrap} accessibilityRole="adjustable" accessibilityLabel={label} accessibilityValue={{ min, max, now: value }}>
      <View style={styles.text}>
        <ThemedText type="smallBold">{label}</ThemedText>
        {hint ? (
          <ThemedText type="small" themeColor="textSecondary">
            {hint}
          </ThemedText>
        ) : null}
      </View>
      <View style={styles.controls}>
        {button('−', value - 1, value <= min)}
        <ThemedText type="subtitle" style={styles.value}>
          {value}
        </ThemedText>
        {button('+', value + 1, value >= max)}
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: { flexDirection: 'row', alignItems: 'center', gap: Spacing.three },
  text: { flex: 1, gap: Spacing.half },
  controls: { flexDirection: 'row', alignItems: 'center', gap: Spacing.two },
  button: { width: 44, height: 44, borderRadius: 22, borderWidth: 1, alignItems: 'center', justifyContent: 'center' },
  value: { minWidth: 28, textAlign: 'center' },
});

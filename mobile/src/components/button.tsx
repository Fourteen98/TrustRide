import { ActivityIndicator, Pressable, StyleSheet, type PressableProps } from 'react-native';

import { Spacing } from '@/constants/theme';
import { useTheme } from '@/hooks/use-theme';

import { ThemedText } from './themed-text';

type Props = Omit<PressableProps, 'children'> & {
  label: string;
  variant?: 'primary' | 'plain';
  loading?: boolean;
};

export function Button({ label, variant = 'primary', loading = false, disabled, style, ...rest }: Props) {
  const theme = useTheme();
  const primary = variant === 'primary';
  const off = disabled || loading;

  return (
    <Pressable
      accessibilityRole="button"
      accessibilityState={{ disabled: !!off, busy: loading }}
      disabled={off}
      style={(state) => [
        styles.button,
        primary ? { backgroundColor: theme.primary } : { borderColor: theme.border, borderWidth: 1 },
        (state.pressed || off) && styles.dim,
        typeof style === 'function' ? style(state) : style,
      ]}
      {...rest}>
      {loading ? (
        <ActivityIndicator color={primary ? theme.onPrimary : theme.text} />
      ) : (
        <ThemedText type="smallBold" style={[styles.label, { color: primary ? theme.onPrimary : theme.text }]}>
          {label}
        </ThemedText>
      )}
    </Pressable>
  );
}

const styles = StyleSheet.create({
  button: {
    minHeight: 52,
    borderRadius: 12,
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: Spacing.four,
  },
  label: { fontSize: 16 },
  dim: { opacity: 0.6 },
});

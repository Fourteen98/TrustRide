import { StyleSheet, View } from 'react-native';

import { Spacing } from '@/constants/theme';
import { useTheme } from '@/hooks/use-theme';

import { ThemedText } from './themed-text';

/** A line of feedback: an error from the server, or a plain note. */
export function Notice({ text, tone = 'error' }: { text: string | null | undefined; tone?: 'error' | 'info' }) {
  const theme = useTheme();

  if (!text) {
    return null;
  }

  return (
    <View
      accessibilityRole={tone === 'error' ? 'alert' : undefined}
      style={[styles.box, { borderColor: tone === 'error' ? theme.danger : theme.border, backgroundColor: theme.backgroundElement }]}>
      <ThemedText type="small" style={tone === 'error' ? { color: theme.danger } : undefined}>
        {text}
      </ThemedText>
    </View>
  );
}

const styles = StyleSheet.create({
  box: { borderWidth: 1, borderRadius: 12, padding: Spacing.three },
});

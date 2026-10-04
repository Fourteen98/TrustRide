import type { ReactNode } from 'react';
import { StyleSheet, View } from 'react-native';

import { Spacing } from '@/constants/theme';
import { useTheme } from '@/hooks/use-theme';

import { MutedText } from './muted-text';
import { ThemedText } from './themed-text';

/** A titled panel. Plain text inside it is shown as small secondary text. */
export function Card({ title, children }: { title: string; children?: ReactNode }) {
  const theme = useTheme();

  return (
    <View style={[styles.card, { backgroundColor: theme.backgroundElement }]}>
      <ThemedText type="smallBold">{title}</ThemedText>
      {typeof children === 'string' ? <MutedText>{children}</MutedText> : children}
    </View>
  );
}

const styles = StyleSheet.create({
  card: { borderRadius: 16, padding: Spacing.three, gap: Spacing.two },
});

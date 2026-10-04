import type { ReactNode } from 'react';
import { StyleSheet, View } from 'react-native';

import { Spacing } from '@/constants/theme';

import { ThemedText } from './themed-text';

type Props = {
  title: string;
  hint?: string;
  /** "chips" lays the choices out side by side and wraps. "rows" stacks them. */
  layout?: 'rows' | 'chips';
  children: ReactNode;
};

/** A titled set of <Choice> options where one is picked: stops, times, days. */
export function ChoiceGroup({ title, hint, layout = 'rows', children }: Props) {
  return (
    <View style={styles.group} accessibilityRole="radiogroup" accessibilityLabel={title}>
      <ThemedText type="smallBold">{title}</ThemedText>
      {hint ? (
        <ThemedText type="small" themeColor="textSecondary">
          {hint}
        </ThemedText>
      ) : null}
      <View style={layout === 'chips' ? styles.chips : styles.rows}>{children}</View>
    </View>
  );
}

const styles = StyleSheet.create({
  group: { gap: Spacing.two },
  rows: { gap: Spacing.two },
  chips: { flexDirection: 'row', flexWrap: 'wrap', gap: Spacing.two },
});

import type { ReactNode } from 'react';
import { View, type ViewStyle } from 'react-native';

import { Spacing } from '@/constants/theme';

import { ThemedText } from './themed-text';

/** A small label with its value under it: "Phone number" over the number, "Car" over the plate. */
export function Detail({ label, children, style }: { label: string; children: ReactNode; style?: ViewStyle }) {
  return (
    <View style={[{ gap: Spacing.one }, style]}>
      <ThemedText type="small" themeColor="textSecondary">
        {label}
      </ThemedText>
      {typeof children === 'string' ? <ThemedText>{children}</ThemedText> : children}
    </View>
  );
}

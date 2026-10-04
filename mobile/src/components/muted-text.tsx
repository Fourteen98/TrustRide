import type { ReactNode } from 'react';

import { ThemedText } from './themed-text';

/** Small secondary text: explanations inside cards and under titles. */
export function MutedText({ children }: { children: ReactNode }) {
  return (
    <ThemedText type="small" themeColor="textSecondary">
      {children}
    </ThemedText>
  );
}

import type { ReactNode } from 'react';
import { ActivityIndicator } from 'react-native';

import { Spacing } from '@/constants/theme';
import type { Loaded } from '@/hooks/use-loader';
import { useTheme } from '@/hooks/use-theme';

import { Button } from './button';
import { Notice } from './notice';

/**
 * The three states of anything fetched from the server, handled the same way on every screen:
 * a spinner while loading, the error with "Try again" if it failed, and the content once it's there.
 *
 *     <Loadable state={plans}>{(data) => data.map(...)}</Loadable>
 */
export function Loadable<T>({ state, children }: { state: Loaded<T>; children: (data: T) => ReactNode }) {
  const theme = useTheme();

  return (
    <>
      <Notice text={state.error} />
      {state.loading ? <ActivityIndicator color={theme.primary} style={{ marginTop: Spacing.four }} /> : null}
      {state.error && state.data === null ? <Button label="Try again" variant="plain" onPress={state.reload} /> : null}
      {state.data !== null ? children(state.data) : null}
    </>
  );
}

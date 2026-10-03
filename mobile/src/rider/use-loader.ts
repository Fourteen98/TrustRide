import { useFocusEffect } from 'expo-router';
import { useCallback, useRef, useState } from 'react';

type State<T> = { data: T | null; error: string | null; loading: boolean; refreshing: boolean };

/**
 * Loads something when the screen comes into view, and again each time the person returns to it.
 * `reload` is for pull-to-refresh and for "try again".
 */
export function useLoader<T>(load: () => Promise<T>) {
  const [state, setState] = useState<State<T>>({ data: null, error: null, loading: true, refreshing: false });
  const loadRef = useRef(load);
  loadRef.current = load;

  const run = useCallback(async (refreshing: boolean) => {
    setState((current) => ({ ...current, error: null, refreshing, loading: current.data === null && !refreshing }));

    try {
      const data = await loadRef.current();
      setState({ data, error: null, loading: false, refreshing: false });
    } catch (e) {
      setState((current) => ({
        ...current,
        error: e instanceof Error ? e.message : 'Something went wrong. Try again.',
        loading: false,
        refreshing: false,
      }));
    }
  }, []);

  useFocusEffect(
    useCallback(() => {
      void run(false);
    }, [run]),
  );

  return { ...state, reload: () => run(true) };
}

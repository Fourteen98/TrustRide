import { useCallback, useState } from 'react';

import { messageOf } from '@/lib/errors';

/**
 * For anything a button does: sending a form, withdrawing, confirming.
 * It tracks "busy" for the button's spinner and keeps the error to show.
 *
 *     const save = useAction();
 *     <Notice text={save.error} />
 *     <Button loading={save.busy} onPress={() => save.run(() => riderApi.apply(body), { then: router.back })} />
 *
 * `run` resolves to true when it worked, so the caller can decide what happens next.
 */
export function useAction() {
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const run = useCallback(async (action: () => Promise<unknown>, options?: { then?: () => void }) => {
    setBusy(true);
    setError(null);

    try {
      await action();
      setBusy(false);
      options?.then?.();

      return true;
    } catch (e) {
      setError(messageOf(e));
      setBusy(false);

      return false;
    }
  }, []);

  return {
    run,
    busy,
    error,
    /** Show a complaint that didn't come from the server, such as a missing field. */
    fail: setError,
    clear: useCallback(() => setError(null), []),
  };
}

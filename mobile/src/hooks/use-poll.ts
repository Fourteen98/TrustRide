import { useEffect, useRef } from 'react';

/**
 * Calls `tick` every `everyMs` while `active` is true, and stops when it turns false or the screen goes away.
 * One call finishes before the next is scheduled, so a slow network never stacks requests.
 */
export function usePoll(tick: () => Promise<void>, active: boolean, everyMs = 4000) {
  const tickRef = useRef(tick);

  useEffect(() => {
    tickRef.current = tick;
  });

  useEffect(() => {
    if (!active) {
      return;
    }

    let stopped = false;
    let timer: ReturnType<typeof setTimeout>;

    const loop = async () => {
      try {
        await tickRef.current();
      } catch {
        // A missed beat is fine. The next one asks again.
      }

      if (!stopped) {
        timer = setTimeout(loop, everyMs);
      }
    };

    timer = setTimeout(loop, everyMs);

    return () => {
      stopped = true;
      clearTimeout(timer);
    };
  }, [active, everyMs]);
}

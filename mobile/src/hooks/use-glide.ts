import { useEffect, useRef, useState } from 'react';

type Point = { latitude: number; longitude: number };

type Options = {
  /**
   * "smooth" eases in and out: a quick, tidy hop to the new place. "steady" moves at an even pace the whole way, so a
   * van given `ms` equal to the time between updates is always moving and arrives just as the next update comes.
   */
  pace?: 'smooth' | 'steady';
  /** How often the shown point moves, in milliseconds. Every frame by default; a slower step is lighter on a busy map. */
  stepMs?: number;
};

/**
 * Follows a moving point: when it jumps to a new place, the value returned slides there over `ms` instead.
 * Used for vans, whose positions arrive every few seconds.
 */
export function useGlide(target: Point | null, ms = 1500, { pace = 'smooth', stepMs }: Options = {}): Point | null {
  const [shown, setShown] = useState<Point | null>(target);
  const from = useRef<Point | null>(target);

  useEffect(() => {
    if (!target) {
      from.current = null;

      return;
    }

    const start = from.current ?? target;
    const began = Date.now();
    let timer: ReturnType<typeof setTimeout> | number = 0;

    const step = () => {
      const t = Math.min(1, (Date.now() - began) / ms);
      const share = pace === 'steady' ? t : t < 0.5 ? 2 * t * t : 1 - (-2 * t + 2) ** 2 / 2;
      const next = {
        latitude: start.latitude + (target.latitude - start.latitude) * share,
        longitude: start.longitude + (target.longitude - start.longitude) * share,
      };

      from.current = next;
      setShown(next);

      if (t < 1) {
        timer = stepMs ? setTimeout(step, stepMs) : requestAnimationFrame(step);
      }
    };

    timer = stepMs ? setTimeout(step, stepMs) : requestAnimationFrame(step);

    return () => (stepMs ? clearTimeout(timer as ReturnType<typeof setTimeout>) : cancelAnimationFrame(timer as number));
  }, [target?.latitude, target?.longitude, ms, pace, stepMs]); // eslint-disable-line react-hooks/exhaustive-deps

  // No target means nothing to show, whatever was shown before.
  return target ? shown : null;
}

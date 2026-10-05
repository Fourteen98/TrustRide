import * as Location from 'expo-location';
import { useEffect, useRef, useState } from 'react';

import { crewApi, type Ping } from './api';

export type SenderState = 'off' | 'asking' | 'sending' | 'waiting' | 'denied';

/** The server takes 200 at a time. More than this many unsent and the oldest are dropped. */
const BATCH = 200;
const KEEP = 1000;

/**
 * Sends this phone's position while a trip is running and this screen is open.
 *
 * Readings are kept in a queue and sent every `intervalSeconds`. If the network is down they stay
 * in the queue and go together when it is back, oldest first, so the server sees the whole road.
 * Pass null for runId to stop.
 *
 * This is the foreground version. It stops when the phone is locked or the app is put away.
 * Sending with the screen off needs a development build and Play Store approval (tasks D1 and D9).
 */
export function useLocationSender(runId: number | null, intervalSeconds: number): { state: SenderState; unsent: number } {
  const [state, setState] = useState<SenderState>('off');
  const [unsent, setUnsent] = useState(0);
  const queue = useRef<Ping[]>([]);

  useEffect(() => {
    if (runId === null) {
      return;
    }

    let stopped = false;
    let watcher: Location.LocationSubscription | null = null;
    let timer: ReturnType<typeof setInterval> | null = null;
    let busy = false;

    const flush = async () => {
      if (busy || queue.current.length === 0) {
        return;
      }

      busy = true;
      const batch = queue.current.slice(0, BATCH);

      try {
        const reply = await crewApi.sendLocation(runId, batch);
        queue.current = queue.current.slice(batch.length);

        if (!stopped) {
          setState(reply.running ? 'sending' : 'off');
        }
      } catch {
        // No network. Keep them and try again on the next beat.
        if (!stopped) {
          setState('waiting');
        }
      } finally {
        busy = false;

        if (!stopped) {
          setUnsent(queue.current.length);
        }
      }
    };

    const begin = async () => {
      setState('asking');
      const { granted } = await Location.requestForegroundPermissionsAsync();

      if (stopped) {
        return;
      }

      if (!granted) {
        setState('denied');

        return;
      }

      watcher = await Location.watchPositionAsync(
        { accuracy: Location.Accuracy.High, timeInterval: intervalSeconds * 1000, distanceInterval: 0 },
        (reading) => {
          queue.current = [
            ...queue.current,
            {
              latitude: reading.coords.latitude,
              longitude: reading.coords.longitude,
              accuracy: reading.coords.accuracy ?? null,
              recorded_at: new Date(reading.timestamp).toISOString(),
            },
          ].slice(-KEEP);
        },
      );

      if (stopped) {
        watcher.remove();

        return;
      }

      setState('sending');
      timer = setInterval(() => void flush(), intervalSeconds * 1000);
      // The first reading arrives within a second or two. Send it straight away so the van appears on the map.
      setTimeout(() => void flush(), 3000);
    };

    void begin().catch(() => !stopped && setState('denied'));

    return () => {
      stopped = true;
      watcher?.remove();

      if (timer) {
        clearInterval(timer);
      }

      // The trip is over, or the screen is gone. Anything unsent is no longer wanted.
      queue.current = [];
      setState('off');
      setUnsent(0);
    };
  }, [runId, intervalSeconds]);

  return { state, unsent };
}

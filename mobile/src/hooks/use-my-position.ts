import * as Location from 'expo-location';
import { useEffect, useState } from 'react';

export type Position = { latitude: number; longitude: number };

/**
 * Where her phone is, roughly, for picking the stop nearest her. Only while the app is open, and only with
 * her permission: without it this stays null and the screen works without it.
 */
export function useMyPosition(): Position | null {
  const [position, setPosition] = useState<Position | null>(null);

  useEffect(() => {
    let stopped = false;

    (async () => {
      const { granted } = await Location.requestForegroundPermissionsAsync();

      if (!granted) {
        return;
      }

      const here =
        (await Location.getLastKnownPositionAsync()) ?? (await Location.getCurrentPositionAsync({ accuracy: Location.Accuracy.Balanced }));

      if (!stopped && here) {
        setPosition({ latitude: here.coords.latitude, longitude: here.coords.longitude });
      }
    })().catch(() => undefined);

    return () => {
      stopped = true;
    };
  }, []);

  return position;
}

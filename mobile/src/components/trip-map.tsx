import * as Location from 'expo-location';
import { useEffect, useImperativeHandle, useRef, useState, type Ref } from 'react';
import { Platform, StyleSheet, Text, View } from 'react-native';
import MapView, { Marker, Polyline, PROVIDER_GOOGLE, type LatLng } from 'react-native-maps';

import { useGlide } from '@/hooks/use-glide';

import { VanIcon } from './van-icon';

export type MapStop = { id: number; name: string; latitude: number | null; longitude: number | null; reached: boolean };
export type MapProps = {
  /** In the order the van travels them. The last is where the run ends. */
  stops: MapStop[];
  /** The road the run follows, as Google traced it. Null until traced: then the stops are joined directly. */
  path: number[][] | null;
  /** The road the van has driven so far today. */
  trail?: number[][];
  /** The van, with which way it faces in degrees from north when known. */
  position: { latitude: number; longitude: number; live: boolean; heading?: number | null } | null;
  /** Aveo's other vans on the road, drawn grey and unnamed. For the crew. */
  others?: { key: string; latitude: number; longitude: number; heading: number | null; live: boolean }[];
  /**
   * How often new positions arrive, in milliseconds. Each van drives at an even pace from one position to the next over
   * this long, so it is always moving and arrives just as the next one comes, instead of jumping and then standing still.
   */
  updateEveryMs?: number;
  /** Her stop, or for the crew the next stop: a labelled pin. For a rider it is also where her part of the route starts. */
  myStopId?: number | null;
  /** The word over that pin. */
  focusLabel?: string;
  /**
   * Whose map this is. A rider's makes her part of the route bold. The crew's makes the whole road ahead of the van
   * bold, and frames the van and the next stop.
   */
  emphasis?: 'rider' | 'crew';
  /** A count drawn on a stop's marker, by stop id: for the crew, how many riders wait there. */
  badges?: Record<number, number>;
  /**
   * The phone showing the map is in the van, as the driver's and the mate's are while the trip runs. The van is then
   * drawn where this phone is, live, labelled "You", and the map follows it until a finger moves the map.
   */
  selfIsVan?: boolean;
  /** Shown on the van marker, so she can match it to the car in the street. */
  vanLabel?: string | null;
  /** The web page with the same map, used by the web build instead of this. */
  mapUrl?: string | null;
  /** Whether a finger can pan and zoom. The small map on a card is still. */
  interactive?: boolean;
  /** Room taken by things laid over the top of the map, such as the "Where to?" bar. Framing keeps clear of it. */
  inset?: { top: number };
  ref?: Ref<TripMapHandle>;
};

/** What a screen can ask of the map from its own buttons. */
export type TripMapHandle = { showRoute: () => void; showMe: () => void };

const GOLD = '#F2B01E';
const INK = '#14324A';
const SOFT = '#9AA0A6';
// Accra, for the moment before anything else is known, so the map never opens on the whole world.
const ACCRA = { latitude: 5.6037, longitude: -0.187, latitudeDelta: 0.15, longitudeDelta: 0.15 };
const CLOSE = { latitudeDelta: 0.02, longitudeDelta: 0.02 };
// Her own position only helps frame the map when she is near her stop. Further away, the stop alone is framed.
const NEAR_METRES = 8000;
// How close to the route a crew phone must be to count as the van.
const ON_ROUTE_METRES = 1000;

const at = ([latitude, longitude]: number[]): LatLng => ({ latitude, longitude });

function metres(a: LatLng, b: LatLng): number {
  const r = 6371000;
  const dLat = ((b.latitude - a.latitude) * Math.PI) / 180;
  const dLng = ((b.longitude - a.longitude) * Math.PI) / 180;
  const h =
    Math.sin(dLat / 2) ** 2 + Math.cos((a.latitude * Math.PI) / 180) * Math.cos((b.latitude * Math.PI) / 180) * Math.sin(dLng / 2) ** 2;

  return 2 * r * Math.asin(Math.min(1, Math.sqrt(h)));
}

/** Degrees clockwise from north, from one point to the next. */
function bearing(a: LatLng, b: LatLng): number {
  const rad = Math.PI / 180;
  const y = Math.sin((b.longitude - a.longitude) * rad) * Math.cos(b.latitude * rad);
  const x =
    Math.cos(a.latitude * rad) * Math.sin(b.latitude * rad) -
    Math.sin(a.latitude * rad) * Math.cos(b.latitude * rad) * Math.cos((b.longitude - a.longitude) * rad);

  return (((Math.atan2(y, x) / rad) % 360) + 360) % 360;
}

/** The point of the line nearest to a place, by index. */
function nearest(line: LatLng[], point: LatLng): number {
  let best = 0;

  line.forEach((candidate, index) => {
    if (metres(candidate, point) < metres(line[best], point)) {
      best = index;
    }
  });

  return best;
}

/**
 * The map, drawn natively, the way ride apps draw it:
 * her part of the route is a bold dark line along the road and the rest is soft grey. While her van is coming,
 * the stretch from the van to her stop is the bold one. The road the van has driven today is gold. Her stop is a
 * labelled pin, the end of the route a labelled square, the van a gold tag with its number, and she is the blue dot.
 *
 * It opens on what matters to her, her stop and where she is, or the van and her stop while it runs.
 * "Route" shows the whole route and "Me" goes to her.
 *
 * Android draws it with Google Maps, iPhone with Apple Maps. A store build for Android needs
 * GOOGLE_MAPS_ANDROID_KEY, see the README.
 */
export function TripMap({
  stops,
  path,
  trail = [],
  position,
  myStopId,
  focusLabel = 'YOUR STOP',
  emphasis = 'rider',
  badges = {},
  selfIsVan = false,
  others = [],
  updateEveryMs = 15000,
  vanLabel,
  interactive = true,
  inset,
  ref,
}: MapProps) {
  const map = useRef<MapView>(null);
  const framed = useRef(false);
  const [me, setMe] = useState<LatLng | null>(null);
  const [myHeading, setMyHeading] = useState<number | null>(null);
  const following = useRef(true);
  const padding = { top: (inset?.top ?? 0) + 40, right: 50, bottom: 70, left: 50 };

  const pinned = stops.filter(
    (stop): stop is MapStop & { latitude: number; longitude: number } => stop.latitude !== null && stop.longitude !== null,
  );
  const mine = pinned.find((stop) => stop.id === myStopId) ?? null;
  const end = pinned.at(-1) ?? null;

  // The line to draw: the road when traced, otherwise straight from stop to stop.
  const line = path && path.length > 1 ? path.map(at) : pinned.map((stop) => ({ latitude: stop.latitude, longitude: stop.longitude }));
  // In the van, this phone is the van: its own position is newer than anything the server has. Only when the phone is
  // on the route, though. Far from it (a mate at home, a phone on a desk) the server's position is the van's.
  const self = selfIsVan && me !== null && line.length > 0 && metres(me, line[nearest(line, me)]) < ON_ROUTE_METRES;
  const vanHeading = self ? myHeading : (position?.heading ?? null);
  const van = useGlide(
    self ? me : position ? { latitude: position.latitude, longitude: position.longitude } : null,
    self ? 3000 : updateEveryMs,
    {
      pace: 'steady',
      stepMs: 100,
    },
  );

  const myIndex = mine ? nearest(line, mine) : 0;
  const vanIndex = van ? nearest(line, van) : null;
  const crew = emphasis === 'crew';
  const coming = !crew && vanIndex !== null && mine !== null && vanIndex < myIndex;

  // Bold, for a rider: from the van to her stop while it is coming to her; from the van on once she is aboard; her ride
  // otherwise. For the crew: the road still ahead of the van, all of it before the trip starts.
  const boldFrom = vanIndex ?? (crew ? 0 : myIndex);
  const boldTo = coming ? myIndex : line.length - 1;
  const bold = line.slice(boldFrom, boldTo + 1);
  const before = line.slice(0, boldFrom + 1);
  const after = coming ? line.slice(myIndex) : [];

  // What to open on: the van and her stop while it runs, otherwise her stop and her, when she is near it.
  const focus = (): LatLng[] => {
    if (crew) {
      return van && mine ? [van, mine] : line.length > 0 ? line : me ? [me] : [];
    }

    if (van) {
      return [van, coming && mine ? mine : (end ?? van)];
    }

    if (mine) {
      return me && metres(me, mine) < NEAR_METRES ? [mine, me] : [mine];
    }

    return me ? [me] : line;
  };

  const show = (points: LatLng[], animated: boolean) => {
    if (points.length === 1) {
      map.current?.animateToRegion({ ...points[0], ...CLOSE }, animated ? 400 : 0);
    } else if (points.length > 1) {
      map.current?.fitToCoordinates(points, { edgePadding: padding, animated });
    }
  };

  const findMe = async (): Promise<LatLng | null> => {
    const here =
      (await Location.getLastKnownPositionAsync()) ?? (await Location.getCurrentPositionAsync({ accuracy: Location.Accuracy.Balanced }));

    return here ? { latitude: here.coords.latitude, longitude: here.coords.longitude } : null;
  };

  useImperativeHandle(ref, () => ({
    showRoute: () => show(line.length > 1 ? [...line, ...(van ? [van] : [])] : focus(), true),
    showMe: () => {
      following.current = true;
      void findMe()
        .then((here) => here && show([here], true))
        .catch(() => undefined);
    },
  }));

  // In the van, keep the phone's position fresh every few seconds, and keep the van in view while following.
  useEffect(() => {
    if (!selfIsVan) {
      return;
    }

    let watcher: Location.LocationSubscription | null = null;
    let stopped = false;

    Location.requestForegroundPermissionsAsync()
      .then(async ({ granted }) => {
        if (!granted || stopped) {
          return;
        }

        watcher = await Location.watchPositionAsync(
          { accuracy: Location.Accuracy.High, timeInterval: 3000, distanceInterval: 5 },
          (fix) => {
            const here = { latitude: fix.coords.latitude, longitude: fix.coords.longitude };
            // The phone's compass course when it has one, otherwise the way it moved since the last fix.
            setMe((before) => {
              const course = fix.coords.heading;
              setMyHeading((old) =>
                course !== null && course >= 0 ? course : before && metres(before, here) > 8 ? bearing(before, here) : old,
              );

              return here;
            });

            if (following.current && framed.current) {
              map.current?.animateCamera({ center: here }, { duration: 800 });
            }
          },
        );

        if (stopped) {
          watcher.remove();
        }
      })
      .catch(() => undefined);

    return () => {
      stopped = true;
      watcher?.remove();
    };
  }, [selfIsVan]);

  // Her own blue dot needs permission to see where she is. Asked once, only for while the app is open.
  useEffect(() => {
    let stopped = false;

    Location.requestForegroundPermissionsAsync()
      .then(async ({ granted }) => {
        if (granted && !stopped) {
          const here = await findMe();

          if (!stopped) {
            setMe(here);
            framed.current = false; // frame again now that she can be included
          }
        }
      })
      .catch(() => undefined);

    return () => {
      stopped = true;
    };
  }, []);

  // Frame once when there is something to show, and once more when her position arrives.
  const frame = () => {
    const points = focus();

    if (!framed.current && points.length > 0) {
      framed.current = true;
      show(points, false);
    }
  };

  useEffect(frame);

  return (
    <View style={styles.fill}>
      <MapView
        ref={map}
        style={styles.fill}
        initialRegion={ACCRA}
        provider={Platform.OS === 'android' ? PROVIDER_GOOGLE : undefined}
        onMapReady={frame}
        showsUserLocation={me !== null && !self}
        onPanDrag={() => {
          following.current = false;
        }}
        showsMyLocationButton={false}
        showsCompass={interactive}
        showsPointsOfInterests={false}
        scrollEnabled={interactive}
        zoomEnabled={interactive}
        zoomTapEnabled={interactive}
        rotateEnabled={interactive}
        pitchEnabled={false}
        toolbarEnabled={false}>
        {before.length > 1 ? <Polyline coordinates={before} strokeColor={SOFT} strokeWidth={4} lineCap="round" lineJoin="round" /> : null}
        {after.length > 1 ? <Polyline coordinates={after} strokeColor={SOFT} strokeWidth={4} lineCap="round" lineJoin="round" /> : null}
        {trail.length > 1 ? (
          <Polyline
            coordinates={[...trail.map(at), ...(van ? [van] : [])]}
            strokeColor={GOLD}
            strokeWidth={5}
            lineCap="round"
            lineJoin="round"
          />
        ) : null}
        {bold.length > 1 ? (
          <Polyline
            coordinates={[...(van ? [van] : []), ...bold]}
            strokeColor={INK}
            strokeWidth={6}
            lineCap="round"
            lineJoin="round"
            zIndex={2}
          />
        ) : null}

        {pinned.map((stop) => {
          if (stop.id === mine?.id || stop.id === end?.id) {
            return null;
          }

          const waiting = badges[stop.id] ?? 0;

          return (
            <Marker
              key={`${stop.id}-${stop.reached}-${waiting}`}
              coordinate={stop}
              title={waiting > 0 ? `${stop.name}, ${waiting} waiting` : stop.name}
              anchor={{ x: 0.5, y: 0.5 }}
              tracksViewChanges={false}>
              {waiting > 0 ? (
                <View style={[styles.badge, { backgroundColor: stop.reached ? SOFT : INK }]}>
                  <Text style={styles.badgeText}>{waiting}</Text>
                </View>
              ) : (
                <View style={[styles.stop, { borderColor: stop.reached ? GOLD : SOFT }]} />
              )}
            </Marker>
          );
        })}

        {end ? (
          <Marker key={`end-${end.id}`} coordinate={end} title={end.name} anchor={{ x: 0.5, y: 1 }} zIndex={4} tracksViewChanges={false}>
            <View style={styles.pin}>
              <View style={styles.label}>
                <Text style={styles.labelText}>{end.name}</Text>
              </View>
              <View style={styles.square} />
            </View>
          </Marker>
        ) : null}

        {mine ? (
          <Marker
            key={`mine-${mine.id}`}
            coordinate={mine}
            title={`${mine.name} (${focusLabel.toLowerCase()})`}
            anchor={{ x: 0.5, y: 1 }}
            zIndex={5}
            tracksViewChanges={false}>
            <View style={styles.pin}>
              <View style={[styles.label, styles.labelMine]}>
                <Text style={styles.kicker}>
                  {focusLabel}
                  {badges[mine.id] ? ` · ${badges[mine.id]} WAITING` : ''}
                </Text>
                <Text style={[styles.labelText, styles.labelTextMine]}>{mine.name}</Text>
              </View>
              <View style={styles.stem} />
              <View style={styles.dot} />
            </View>
          </Marker>
        ) : null}

        {others.map((other) => (
          <OtherVan key={other.key} van={other} overMs={updateEveryMs} />
        ))}

        {van ? (
          <Marker
            coordinate={van}
            title={self ? 'You' : vanLabel ? `Your van, ${vanLabel}` : 'Your van'}
            anchor={{ x: 0.5, y: 0.5 }}
            flat
            zIndex={10}
            tracksViewChanges={false}
            key={`van-${self || position?.live}-${Math.round((vanHeading ?? 0) / 10)}`}>
            <VanIcon tone={self || position?.live ? 'ours' : 'stale'} heading={vanHeading} />
          </Marker>
        ) : null}
      </MapView>
    </View>
  );
}

/** Another Aveo van, unnamed, driving steadily from one reported position to the next. */
function OtherVan({ van, overMs }: { van: NonNullable<MapProps['others']>[number]; overMs: number }) {
  const at = useGlide({ latitude: van.latitude, longitude: van.longitude }, overMs, { pace: 'steady', stepMs: 150 });

  return at ? (
    <Marker
      key={`${Math.round((van.heading ?? 0) / 15)}-${van.live}`}
      coordinate={at}
      title="Another Aveo van"
      anchor={{ x: 0.5, y: 0.5 }}
      flat
      zIndex={6}
      tracksViewChanges={false}>
      <VanIcon tone={van.live ? 'other' : 'stale'} heading={van.heading} size={0.85} />
    </Marker>
  ) : null;
}

const shadow = { shadowColor: '#000', shadowOpacity: 0.2, shadowRadius: 4, shadowOffset: { width: 0, height: 2 }, elevation: 4 } as const;

const styles = StyleSheet.create({
  fill: { flex: 1 },
  stop: { width: 12, height: 12, borderRadius: 6, borderWidth: 3, backgroundColor: '#fff' },
  badge: {
    minWidth: 24,
    height: 24,
    borderRadius: 12,
    paddingHorizontal: 6,
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 2,
    borderColor: '#fff',
  },
  badgeText: { color: '#fff', fontSize: 12, fontWeight: '800' },
  pin: { alignItems: 'center' },
  label: { backgroundColor: '#fff', borderRadius: 8, paddingHorizontal: 8, paddingVertical: 4, marginBottom: 4, ...shadow },
  labelMine: { backgroundColor: INK },
  kicker: { color: GOLD, fontSize: 9, fontWeight: '800', letterSpacing: 1 },
  labelText: { color: INK, fontSize: 12, fontWeight: '700' },
  labelTextMine: { color: '#fff' },
  stem: { width: 2, height: 8, backgroundColor: INK },
  dot: { width: 14, height: 14, borderRadius: 7, backgroundColor: GOLD, borderWidth: 3, borderColor: '#fff', ...shadow },
  square: { width: 14, height: 14, backgroundColor: INK, borderWidth: 3, borderColor: '#fff', ...shadow },
});

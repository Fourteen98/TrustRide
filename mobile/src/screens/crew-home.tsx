import { useRouter } from 'expo-router';
import { useRef, useState } from 'react';
import { Platform, StyleSheet, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { useAuth } from '@/auth/auth-context';
import { ActionMenu, type MenuItem } from '@/components/action-menu';
import { Button } from '@/components/button';
import { Card } from '@/components/card';
import { HomeHeader, RoleSwitcher } from '@/components/home-header';
import { Loadable } from '@/components/loadable';
import { MapButton, MapTopBar, SheetStatus } from '@/components/map-controls';
import { MapSheet } from '@/components/map-sheet';
import { MutedText } from '@/components/muted-text';
import { Notice } from '@/components/notice';
import { Screen } from '@/components/screen';
import { ThemedText } from '@/components/themed-text';
import { TripMap, type TripMapHandle } from '@/components/trip-map';
import { Spacing } from '@/constants/theme';
import { crewApi, type CrewRun } from '@/crew/api';
import { RunCard, senderText, StopBlock } from '@/crew/run-card';
import { StopTimeline } from '@/crew/stop-timeline';
import { CREW_REFRESH_MS, currentRun, nextStop, useCrew, waitingAt } from '@/crew/use-crew';
import { useAction } from '@/hooks/use-action';
import { useTheme } from '@/hooks/use-theme';
import { clock } from '@/lib/dates';

/** Where a trip goes, from its last stop: "to Circle". Vans run all day, so a trip is named by where it heads and when. */
const towards = (run: CrewRun) => `to ${run.map.stops.at(-1)?.name ?? run.route}`;

/** The line at the top of the sheet: what the crew should do now. */
function statusLine(run: CrewRun | null, later: CrewRun[]): string {
  if (!run) {
    return later.length > 0 ? `Free until ${clock(later[0].departs_at)}` : 'No more runs today';
  }

  if (run.status === 'not_started') {
    return `Leaves ${clock(run.departs_at)} ${towards(run)}`;
  }

  if (run.status === 'ended') {
    const onBoard = run.stops.flatMap((stop) => stop.riders).filter((rider) => rider.pickup === 'picked_up').length;

    return `Trip ended · ${onBoard} picked up`;
  }

  const next = nextStop(run);

  return next ? `Next: ${next.name}, ${clock(next.time)}` : 'All stops passed. End the trip on arrival.';
}

/**
 * Home for the driver and the mate, the way a driver app looks: the van's route fills the screen with the road ahead
 * in bold, each stop showing how many riders wait there, and the next stop labelled. The sheet holds what to do now:
 * start the trip, then at each stop mark who got on. What each can do follows his permissions: the driver starts and
 * ends the trip, the mate marks pickups, and both phones send the van's position while a trip runs.
 *
 * The web build has no native map, so there it is the run cards on a plain screen.
 */
export function CrewHome() {
  const crew = useCrew();

  if (Platform.OS === 'web') {
    return (
      <Screen refreshing={crew.state.refreshing} onRefresh={crew.state.reload}>
        <HomeHeader />
        <Loadable state={crew.state}>
          {() => (
            <>
              {crew.runs.length === 0 ? <NoRun /> : null}
              {crew.runs.map((run) => (
                <RunCard
                  key={run.run_id}
                  run={run}
                  onChange={crew.update}
                  sender={run.run_id === crew.running?.run_id ? crew.sender : undefined}
                />
              ))}
            </>
          )}
        </Loadable>
      </Screen>
    );
  }

  return <CrewMap crew={crew} />;
}

function CrewMap({ crew }: { crew: ReturnType<typeof useCrew> }) {
  const router = useRouter();
  const theme = useTheme();
  const insets = useSafeAreaInsets();
  const { can } = useAuth();
  const action = useAction();
  const mapRef = useRef<TripMapHandle>(null);
  const [menuOpen, setMenuOpen] = useState(false);
  const [confirmingEnd, setConfirmingEnd] = useState(false);

  // Only the run that matters now: the one on the road, or the next to leave. Later ones wait in "Later today".
  const run = currentRun(crew.runs);
  const later = crew.runs.filter((item) => item.status === 'not_started' && item.run_id !== run?.run_id);
  const done = crew.runs.filter((item) => item.status === 'ended' && item.run_id !== run?.run_id);
  const next = run ? nextStop(run) : null;
  const running = run?.status === 'running';
  const canMark = running && can('trips.pickups.mark');
  const change = (request: () => Promise<CrewRun>) => action.run(async () => crew.update(await request()));

  const items: MenuItem[] = [];

  if (run && can('trips.run') && run.status === 'not_started') {
    items.push({
      label: 'Start trip',
      detail: 'Riders are told the van has left.',
      icon: 'play.fill',
      glyph: '▶',
      onPress: () => void change(() => crewApi.start(run.run_id)),
    });
  }

  if (run && can('trips.run') && running) {
    items.push({
      label: 'End trip',
      detail: "Riders stop seeing the van. It can't be started again today.",
      icon: 'flag.checkered',
      glyph: '⚑',
      tone: 'danger',
      onPress: () => setConfirmingEnd(true),
    });
  }

  if (can('drivers.commission.view')) {
    items.push({
      label: 'My earnings',
      detail: 'Commission this month and before.',
      icon: 'banknote',
      glyph: '₵',
      onPress: () => router.push('/earnings'),
    });
  }

  if (can('support.alert.raise')) {
    items.push({
      label: 'Report a problem',
      detail: 'A breakdown, an accident, or trouble on the road.',
      icon: 'exclamationmark.triangle',
      glyph: '!',
      onPress: () => router.push('/help'),
    });
  }

  return (
    <MapSheet
      map={
        run ? (
          <TripMap
            key={run.run_id}
            ref={mapRef}
            stops={run.map.stops}
            path={run.map.path}
            trail={run.map.trail}
            position={run.map.position}
            myStopId={running ? next?.stop_id : null}
            focusLabel="NEXT STOP"
            emphasis="crew"
            selfIsVan={running}
            badges={waitingAt(run)}
            others={run.map.others}
            updateEveryMs={CREW_REFRESH_MS}
            vanLabel={run.car_number}
            inset={{ top: insets.top + 60 }}
          />
        ) : (
          <TripMap key="none" ref={mapRef} stops={[]} path={null} position={null} inset={{ top: insets.top + 60 }} />
        )
      }
      top={<MapTopBar />}
      head={<SheetStatus text={statusLine(run, later)} live={running} />}
      float={
        <>
          {items.length > 0 ? <MapButton label="Trip options" text="Trip" tone="strong" onPress={() => setMenuOpen(true)} /> : null}
          {run ? <MapButton label="Show the route" text="Route" onPress={() => mapRef.current?.showRoute()} /> : null}
          <MapButton label="Show where I am" text="Me" onPress={() => mapRef.current?.showMe()} />
        </>
      }
      refreshing={crew.state.refreshing}
      onRefresh={crew.state.reload}>
      <RoleSwitcher />
      <Notice text={action.error} />

      <Loadable state={crew.state}>
        {() =>
          !run ? (
            <>
              {crew.runs.length === 0 ? <NoRun /> : null}
              {later[0] ? (
                <Card title={`Next trip · ${clock(later[0].departs_at)} ${towards(later[0])}`}>
                  <MutedText>
                    {later[0].route}, car {later[0].car_number}. Leaves {clock(later[0].departs_at)}. It shows here an hour before, ready to
                    start.
                  </MutedText>
                </Card>
              ) : null}
            </>
          ) : (
            <>
              {running && crew.sender ? (
                <Notice tone={crew.sender.state === 'denied' ? 'error' : 'info'} text={senderText[crew.sender.state]} />
              ) : null}

              {confirmingEnd ? (
                <Card title="End the trip?">
                  <MutedText>It can't be started again today, and riders stop seeing the van.</MutedText>
                  <Button
                    label="Yes, we have arrived"
                    loading={action.busy}
                    onPress={() => void change(() => crewApi.end(run.run_id)).then(() => setConfirmingEnd(false))}
                  />
                  <Button label="Not yet" variant="plain" onPress={() => setConfirmingEnd(false)} />
                </Card>
              ) : null}

              {run.status === 'not_started' ? (
                <Card title={`${run.route} · car ${run.car_number}`}>
                  <MutedText>
                    {run.stops.flatMap((stop) => stop.riders).filter((rider) => !rider.absent).length} riders booked today.
                    {can('trips.run')
                      ? ' Start the trip as you leave the first stop. Riders are told the van has left.'
                      : ' The driver starts the trip.'}
                  </MutedText>
                  {can('trips.run') ? (
                    <Button label="Start trip" loading={action.busy} onPress={() => void change(() => crewApi.start(run.run_id))} />
                  ) : null}
                </Card>
              ) : null}

              {next ? (
                <View style={[styles.next, { borderColor: theme.primary }]}>
                  <ThemedText type="small" style={[styles.kicker, { color: theme.primaryText }]}>
                    {running ? 'NEXT STOP' : 'FIRST STOP'}
                  </ThemedText>
                  <StopBlock
                    stop={next}
                    canMark={canMark}
                    onMark={(riderId, status) => void change(() => crewApi.mark(run.run_id, riderId, status))}
                  />
                </View>
              ) : null}

              <StopTimeline
                run={run}
                nextId={next?.stop_id ?? null}
                canMark={canMark}
                onMark={(riderId, status) => void change(() => crewApi.mark(run.run_id, riderId, status))}
              />
            </>
          )
        }
      </Loadable>

      {(run ? later : later.slice(1)).length > 0 || done.length > 0 ? (
        <View style={[styles.later, { borderColor: theme.border }]}>
          {(run ? later : later.slice(1)).map((item) => (
            <View key={item.run_id} style={styles.laterRow}>
              <ThemedText type="small" themeColor="textSecondary" style={styles.laterKicker}>
                LATER TODAY
              </ThemedText>
              <MutedText>
                {clock(item.departs_at)} {towards(item)} ·{' '}
                {item.stops.flatMap((stop) => stop.riders).filter((rider) => !rider.absent).length} booked
              </MutedText>
            </View>
          ))}
          {done.map((item) => (
            <MutedText key={item.run_id}>
              ✓ {clock(item.departs_at)} {towards(item)} done ·{' '}
              {item.stops.flatMap((stop) => stop.riders).filter((rider) => rider.pickup === 'picked_up').length} picked up
            </MutedText>
          ))}
        </View>
      ) : null}

      <ActionMenu
        title={run ? `${run.route}, car ${run.car_number}` : 'Trip'}
        items={items}
        open={menuOpen}
        onClose={() => setMenuOpen(false)}
      />
    </MapSheet>
  );
}

/** No Ride run today. A driver who does Ox is pointed to his parcels instead. */
function NoRun() {
  const { account } = useAuth();
  const doesOx = (account?.work_types ?? []).includes('ox');

  return (
    <Card title={doesOx ? 'No Ride run today' : 'No run today'}>
      {doesOx ? 'Your parcel runs are under Parcels, at the bottom.' : 'You have no run today. If that is wrong, tell Aveo under Help.'}
    </Card>
  );
}

const styles = StyleSheet.create({
  later: { borderTopWidth: 1, paddingTop: Spacing.three, marginTop: Spacing.two, gap: Spacing.two },
  laterRow: { gap: Spacing.half },
  laterKicker: { letterSpacing: 1.2, fontSize: 12 },
  next: { borderWidth: 2, borderRadius: 18, padding: Spacing.three, gap: Spacing.one },
  kicker: { letterSpacing: 1.2, fontSize: 12, fontWeight: 800 },
});

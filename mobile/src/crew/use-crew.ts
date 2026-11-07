import { useState } from 'react';

import { useAuth } from '@/auth/auth-context';
import { useLoader } from '@/hooks/use-loader';
import { usePoll } from '@/hooks/use-poll';

import { crewApi, type CrewRun } from './api';
import { useLocationSender } from './use-location-sender';

/**
 * Today's runs for the driver's or the mate's screen, kept fresh: after a button the run comes back from the server,
 * and every 20 seconds the other phone's starts and pickups are picked up. While a trip runs this phone sends the van's
 * position, if it may.
 */
export function useCrew() {
  const { can } = useAuth();
  const state = useLoader(crewApi.today);
  // Runs as the server last described them, after a button here or a poll. Until then, what the screen loaded with.
  const [changed, setChanged] = useState<Record<number, CrewRun>>({});

  const runs: CrewRun[] = (state.data?.runs ?? []).map((run) => changed[run.run_id] ?? run);
  const running = runs.find((run) => run.status === 'running') ?? null;

  const sender = useLocationSender(running && can('trips.location.send') ? running.run_id : null, state.data?.ping_interval_seconds ?? 15);

  usePoll(
    async () => {
      const fresh = await crewApi.today();
      setChanged(Object.fromEntries(fresh.runs.map((run) => [run.run_id, run])));
    },
    runs.some((run) => run.status !== 'ended'),
    running ? CREW_REFRESH_MS : 20000,
  );

  return {
    state,
    runs,
    running,
    /** The phone's position sending, for the run that is on the road. */
    sender: running && can('trips.location.send') ? sender : undefined,
    /** Put in the run as the server now has it, after start, end or a pickup. */
    update: (run: CrewRun) => setChanged((current) => ({ ...current, [run.run_id]: run })),
  };
}

/** How often the crew screen asks again while a van is on the road. The map glides each van over the same time. */
export const CREW_REFRESH_MS = 10000;

/** How many minutes before a run leaves it takes over the crew's screen, ready to start. */
export const SHOW_BEFORE_MINUTES = 60;

/** Minutes from now until a run's departure today, negative once it is due. */
export function minutesUntil(run: CrewRun, now = new Date()): number {
  const [hours, minutes] = run.departs_at.split(':').map(Number);

  return hours * 60 + minutes - (now.getHours() * 60 + now.getMinutes());
}

/**
 * The run that matters now: the one on the road, or one leaving within the hour (or already due and not started).
 * Null in between runs, when the screen shows only where he is and what comes later.
 */
export function currentRun(runs: CrewRun[], now = new Date()): CrewRun | null {
  return (
    runs.find((run) => run.status === 'running') ??
    runs.find((run) => run.status === 'not_started' && minutesUntil(run, now) <= SHOW_BEFORE_MINUTES) ??
    null
  );
}

/** The next stop ahead of the van: the first not yet reached. Before the trip, the first stop. Null once all are passed. */
export function nextStop(run: CrewRun): CrewRun['stops'][number] | null {
  return run.stops.find((stop) => !stop.reached) ?? null;
}

/** How many people are still to be picked up at each stop, by stop id, for the badges on the map: each rider and those with her. */
export function waitingAt(run: CrewRun): Record<number, number> {
  return Object.fromEntries(
    run.stops.map((stop) => [
      stop.stop_id,
      stop.riders
        .filter((rider) => !rider.absent && rider.pickup === null)
        .reduce((people, rider) => people + 1 + rider.on_plan + rider.paying_fare, 0),
    ]),
  );
}

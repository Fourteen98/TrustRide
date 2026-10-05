import { useRouter } from 'expo-router';
import { useState } from 'react';

import { useAuth } from '@/auth/auth-context';
import { Button } from '@/components/button';
import { Card } from '@/components/card';
import { Loadable } from '@/components/loadable';
import { MutedText } from '@/components/muted-text';
import { crewApi, type CrewRun } from '@/crew/api';
import { RunCard } from '@/crew/run-card';
import { useLocationSender } from '@/crew/use-location-sender';
import { useLoader } from '@/hooks/use-loader';
import { usePoll } from '@/hooks/use-poll';

/**
 * Home for the driver and the mate. Both see today's runs for their van, with the riders at each stop.
 * What each can do follows his permissions: the driver starts and ends the trip, the mate marks pickups,
 * and both phones send the van's position while a trip is running.
 */
export function CrewHome() {
  const router = useRouter();
  const { can } = useAuth();
  const state = useLoader(crewApi.today);
  // Runs as the server last described them, after a button here or a poll. Until then, what the screen loaded with.
  const [changed, setChanged] = useState<Record<number, CrewRun>>({});

  const runs = (state.data?.runs ?? []).map((run) => changed[run.run_id] ?? run);
  const running = runs.find((run) => run.status === 'running') ?? null;

  const sender = useLocationSender(
    running && can('trips.location.send') ? running.run_id : null,
    state.data?.ping_interval_seconds ?? 15,
  );

  // The other phone may start the trip or mark someone. Pick that up without a pull to refresh.
  usePoll(
    async () => {
      const fresh = await crewApi.today();
      setChanged(Object.fromEntries(fresh.runs.map((run) => [run.run_id, run])));
    },
    runs.some((run) => run.status !== 'ended'),
    20000,
  );

  return (
    <Loadable state={state}>
      {() => (
        <>
          {runs.length === 0 ? <Card title="No run today">You have no run today. If that is wrong, tell TrustRide under Help.</Card> : null}

          {runs.map((run) => (
            <RunCard
              key={run.run_id}
              run={run}
              onChange={(next) => setChanged((current) => ({ ...current, [next.run_id]: next }))}
              sender={run.run_id === running?.run_id && can('trips.location.send') ? sender : undefined}
            />
          ))}

          {can('drivers.commission.view') ? (
            <Card title="Commission">
              <MutedText>What TrustRide pays you each month, and how each month was assessed.</MutedText>
              <Button label="See my commission" variant="plain" onPress={() => router.push('/earnings')} />
            </Card>
          ) : null}
        </>
      )}
    </Loadable>
  );
}

import { Card } from '@/components/card';
import { Loadable } from '@/components/loadable';
import { Screen } from '@/components/screen';
import { useLoader } from '@/hooks/use-loader';
import { parcelsApi } from '@/ox/api';
import { RunView } from '@/ox/run-view';

/**
 * Aveo Ox for the driver: today's parcel runs. Shown only to a driver who does Ox work. Operators build the runs in
 * the console; here he starts, collects at each business, and delivers with the code each customer was texted.
 */
export default function ParcelsScreen() {
  const state = useLoader(parcelsApi.today);

  return (
    <Screen edges={['left', 'right']} refreshing={state.refreshing} onRefresh={() => void state.reload()}>
      <Loadable state={state}>
        {(runs) =>
          runs.length === 0 ? (
            <Card title="No parcels today">When Aveo puts parcels on a run for you, it shows here. Pull down to check again.</Card>
          ) : (
            <>
              {runs.map((run) => (
                <RunView key={run.run_id} run={run} onChange={state.reload} />
              ))}
            </>
          )
        }
      </Loadable>
    </Screen>
  );
}

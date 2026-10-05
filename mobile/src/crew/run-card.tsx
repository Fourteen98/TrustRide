import { useState } from 'react';
import { StyleSheet, View } from 'react-native';

import { useAuth } from '@/auth/auth-context';
import { Button } from '@/components/button';
import { Card } from '@/components/card';
import { Choice } from '@/components/choice';
import { MutedText } from '@/components/muted-text';
import { Notice } from '@/components/notice';
import { ThemedText } from '@/components/themed-text';
import { Spacing } from '@/constants/theme';
import { useAction } from '@/hooks/use-action';
import { useTheme } from '@/hooks/use-theme';
import { clock } from '@/lib/dates';

import { crewApi, type CrewRider, type CrewRun } from './api';
import type { SenderState } from './use-location-sender';

type Props = {
  run: CrewRun;
  /** Called with the run as the server now has it, after start, end or a pickup. */
  onChange: (run: CrewRun) => void;
  /** How this phone's position sending is doing, if this is the run it is sending for. */
  sender?: { state: SenderState; unsent: number };
};

const senderText: Record<SenderState, string | null> = {
  off: null,
  asking: 'Allow location so riders can see the van.',
  sending: 'Sending the van\'s position. Keep this screen open.',
  waiting: 'No network. Positions are saved and will go when it is back.',
  denied: 'Location is off for TrustRide, so riders can\'t see the van. Turn it on in your phone\'s settings.',
};

/** One run for the crew: start and end the trip, and who gets on at each stop. */
export function RunCard({ run, onChange, sender }: Props) {
  const { can } = useAuth();
  const action = useAction();
  const [confirmingEnd, setConfirmingEnd] = useState(false);
  const running = run.status === 'running';
  const riders = run.stops.flatMap((stop) => stop.riders).filter((rider) => !rider.absent);
  const onBoard = riders.filter((rider) => rider.pickup === 'picked_up').length;

  const change = (request: () => Promise<CrewRun>) => action.run(async () => onChange(await request()));

  return (
    <Card title={`${clock(run.departs_at)} · ${run.route}`}>
      <MutedText>
        Car {run.car_number}. {riders.length} {riders.length === 1 ? 'rider' : 'riders'} today
        {run.status === 'not_started' ? '.' : `, ${onBoard} on board.`}
        {run.status === 'ended' ? ' Trip ended.' : ''}
      </MutedText>

      <Notice text={action.error} />
      {running && sender ? <Notice tone={sender.state === 'denied' ? 'error' : 'info'} text={senderText[sender.state]} /> : null}

      {can('trips.run') && run.status === 'not_started' ? (
        <Button label="Start trip" loading={action.busy} onPress={() => change(() => crewApi.start(run.run_id))} />
      ) : null}

      {run.stops.map((stop) => (
        <View key={stop.stop_id} style={styles.stop}>
          <View style={styles.stopHead}>
            <ThemedText type="smallBold">
              {stop.reached ? '✓ ' : ''}
              {stop.name}
            </ThemedText>
            <MutedText>{clock(stop.time)}</MutedText>
          </View>
          {stop.riders.length === 0 ? <MutedText>Nobody today.</MutedText> : null}
          {stop.riders.map((rider) => (
            <RiderRow
              key={rider.rider_id}
              rider={rider}
              canMark={running && can('trips.pickups.mark')}
              onMark={(status) => change(() => crewApi.mark(run.run_id, rider.rider_id, status))}
            />
          ))}
        </View>
      ))}

      {can('trips.run') && running ? (
        confirmingEnd ? (
          <View style={{ gap: Spacing.two }}>
            <MutedText>End the trip? It can't be started again today, and riders stop seeing the van.</MutedText>
            <Button label="Yes, we have arrived" loading={action.busy} onPress={() => change(() => crewApi.end(run.run_id))} />
            <Button label="Not yet" variant="plain" onPress={() => setConfirmingEnd(false)} />
          </View>
        ) : (
          <Button label="End trip" variant="plain" onPress={() => setConfirmingEnd(true)} />
        )
      ) : null}
    </Card>
  );
}

function RiderRow({ rider, canMark, onMark }: { rider: CrewRider; canMark: boolean; onMark: (status: 'picked_up' | 'missed') => void }) {
  const theme = useTheme();

  if (rider.absent) {
    return (
      <View style={styles.rider}>
        <ThemedText type="default" themeColor="textSecondary" style={styles.name}>
          {rider.name}
        </ThemedText>
        <MutedText>Not riding today</MutedText>
      </View>
    );
  }

  return (
    <View style={[styles.rider, { borderColor: theme.border }]}>
      <ThemedText type="default" style={styles.name}>
        {rider.name}
      </ThemedText>
      {canMark ? (
        <View style={styles.marks} accessibilityRole="radiogroup" accessibilityLabel={rider.name}>
          <Choice shape="chip" label="On board" selected={rider.pickup === 'picked_up'} onPress={() => onMark('picked_up')} />
          <Choice shape="chip" label="Not here" selected={rider.pickup === 'missed'} onPress={() => onMark('missed')} />
        </View>
      ) : (
        <MutedText>{rider.pickup === 'picked_up' ? 'On board' : rider.pickup === 'missed' ? 'Not at the stop' : ''}</MutedText>
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  stop: { gap: Spacing.two, paddingTop: Spacing.two },
  stopHead: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'baseline', gap: Spacing.two },
  rider: { flexDirection: 'row', alignItems: 'center', flexWrap: 'wrap', gap: Spacing.two },
  name: { flex: 1, minWidth: 120 },
  marks: { flexDirection: 'row', gap: Spacing.two },
});

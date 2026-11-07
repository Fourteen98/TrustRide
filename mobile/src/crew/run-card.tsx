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

import { crewApi, type CrewRider, type CrewRun, type CrewStop } from './api';
import type { SenderState } from './use-location-sender';

type Props = {
  run: CrewRun;
  /** Called with the run as the server now has it, after start, end or a pickup. */
  onChange: (run: CrewRun) => void;
  /** How this phone's position sending is doing, if this is the run it is sending for. */
  sender?: { state: SenderState; unsent: number };
};

export const senderText: Record<SenderState, string | null> = {
  off: null,
  asking: 'Allow location so riders can see the van.',
  sending: "Sending the van's position. Keep this screen open.",
  waiting: 'No network. Positions are saved and will go when it is back.',
  denied: "Location is off for Aveo, so riders can't see the van. Turn it on in your phone's settings.",
};

/** One run for the crew on a plain screen, as on the web: start and end the trip, and who gets on at each stop. */
export function RunCard({ run, onChange, sender }: Props) {
  const { can } = useAuth();
  const action = useAction();
  const [confirmingEnd, setConfirmingEnd] = useState(false);
  const running = run.status === 'running';
  const riders = run.stops.flatMap((stop) => stop.riders).filter((rider) => !rider.absent);
  const onBoard = run.on_board.reduce((sum, rider) => sum + rider.people, 0);

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

      <OnBoardList run={run} />

      {run.stops.map((stop) => (
        <StopBlock
          key={stop.stop_id}
          stop={stop}
          canMark={running && can('trips.pickups.mark')}
          onMark={(riderId, status) => change(() => crewApi.mark(run.run_id, riderId, status))}
        />
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

/**
 * One stop for the crew: its name and time, each rider who gets on there with On board and Not here, and who gets
 * off there. Getting off needs no marking: she taps "I've got down" herself, or is taken as off once the van is there.
 */
export function StopBlock({
  stop,
  canMark,
  onMark,
}: {
  stop: CrewStop;
  canMark: boolean;
  onMark: (riderId: number, status: 'picked_up' | 'missed') => void;
}) {
  return (
    <View style={styles.stop}>
      <View style={styles.stopHead}>
        <ThemedText type="smallBold">
          {stop.reached ? '✓ ' : ''}
          {stop.name}
        </ThemedText>
        <MutedText>{clock(stop.time)}</MutedText>
      </View>
      {stop.landmark ? <MutedText>{stop.landmark}</MutedText> : null}
      {stop.riders.length === 0 && stop.getting_off.length === 0 ? <MutedText>Nobody gets on or off today.</MutedText> : null}
      {stop.riders.length > 0 && stop.getting_off.length > 0 ? <Label text="GETTING ON" /> : null}
      {stop.riders.map((rider) => (
        <RiderRow key={rider.rider_id} rider={rider} canMark={canMark} onMark={(status) => onMark(rider.rider_id, status)} />
      ))}
      {stop.getting_off.length > 0 ? <Label text="GETTING OFF" /> : null}
      {stop.getting_off.map((rider) => (
        <View key={rider.rider_id} style={styles.rider}>
          <ThemedText type="default" style={styles.name}>
            {rider.name}
            {rider.people > 1 ? ` +${rider.people - 1}` : ''}
          </ThemedText>
          <MutedText>{rider.on_board ? 'On board' : stop.reached ? 'Got off' : 'Not on yet'}</MutedText>
        </View>
      ))}
    </View>
  );
}

function Label({ text }: { text: string }) {
  return (
    <ThemedText type="small" themeColor="textSecondary" style={styles.label}>
      {text}
    </ThemedText>
  );
}

/** Everyone on the van now, in the order they get off: who, how many with her, and where. */
export function OnBoardList({ run }: { run: CrewRun }) {
  const theme = useTheme();
  const people = run.on_board.reduce((sum, rider) => sum + rider.people, 0);

  if (run.on_board.length === 0) {
    return null;
  }

  return (
    <View style={[styles.board, { backgroundColor: theme.backgroundElement }]}>
      <ThemedText type="small" themeColor="textSecondary" style={styles.label}>
        ON BOARD · {people} {people === 1 ? 'PERSON' : 'PEOPLE'}
      </ThemedText>
      {run.on_board.map((rider) => (
        <View key={rider.rider_id} style={styles.boardRow}>
          <ThemedText type="default" style={styles.name} numberOfLines={1}>
            {rider.name}
            {rider.people > 1 ? ` +${rider.people - 1}` : ''}
          </ThemedText>
          <MutedText>
            off at {rider.gets_off}, {clock(rider.off_time)}
          </MutedText>
        </View>
      ))}
    </View>
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

  // People travelling with her: on her plan they have paid; held for the day they each pay the fare.
  const withHer = [
    rider.on_plan > 0 ? `+${rider.on_plan} paid` : null,
    rider.paying_fare > 0 ? `+${rider.paying_fare} pay${rider.paying_fare === 1 ? 's' : ''} the fare` : null,
  ].filter(Boolean);

  return (
    <View style={[styles.rider, { borderColor: theme.border }]}>
      <View style={styles.name}>
        <ThemedText type="default">{rider.name}</ThemedText>
        <MutedText>to {rider.gets_off}</MutedText>
        {withHer.length > 0 ? (
          <ThemedText type="smallBold" style={{ color: theme.primaryText }}>
            {withHer.join(' · ')}
          </ThemedText>
        ) : null}
      </View>
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
  label: { letterSpacing: 1.2, fontSize: 12 },
  board: { borderRadius: 16, padding: Spacing.three, gap: Spacing.two },
  boardRow: { flexDirection: 'row', alignItems: 'baseline', gap: Spacing.two },
});

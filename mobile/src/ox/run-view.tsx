import { useState } from 'react';
import { Linking, StyleSheet, View } from 'react-native';

import { Button } from '@/components/button';
import { Card } from '@/components/card';
import { Choice } from '@/components/choice';
import { MutedText } from '@/components/muted-text';
import { Notice } from '@/components/notice';
import { TextField } from '@/components/text-field';
import { ThemedText } from '@/components/themed-text';
import { Spacing } from '@/constants/theme';
import { useAction } from '@/hooks/use-action';
import { useTheme } from '@/hooks/use-theme';

import { parcelsApi, type Drop, type ParcelRun, type Pickup } from './api';

/** The reasons a driver can pick when a parcel can't be handed over. The customer is texted the one he picks. */
const REASONS = ['Nobody at home', 'Customer not answering the phone', 'Address could not be found', 'Customer refused the parcel'];

/**
 * One parcel run, in the order he works it: start, collect at each business, then each door. After every step the
 * screen is loaded again, so what shows is always what the server holds.
 */
export function RunView({ run, onChange }: { run: ParcelRun; onChange: () => Promise<void> }) {
  const action = useAction();
  const [finishing, setFinishing] = useState(false);
  const { counts } = run;
  const out = run.status === 'out';

  return (
    <View style={styles.run}>
      <Card title={`${run.zone} · ${counts.total} ${counts.total === 1 ? 'parcel' : 'parcels'}`}>
        <MutedText>
          {run.status === 'planned'
            ? 'Start when you set off. Then collect at each business below.'
            : run.status === 'done'
              ? `Finished. ${counts.delivered} delivered${counts.failed ? `, ${counts.failed} not` : ''}.`
              : `${counts.delivered} delivered, ${counts.with_driver} with you, ${counts.to_collect} to collect${counts.failed ? `, ${counts.failed} not delivered` : ''}.`}
        </MutedText>
        <Notice text={action.error} />
        {run.status === 'planned' ? (
          <Button
            label="Start the run"
            loading={action.busy}
            onPress={() => void action.run(() => parcelsApi.start(run.run_id), { then: onChange })}
          />
        ) : null}
      </Card>

      {run.status !== 'done' ? (
        <>
          <Label text="COLLECT" />
          {run.pickups.map((pickup) => (
            <PickupCard key={pickup.business_id} pickup={pickup} runId={run.run_id} canCollect={out} onChange={onChange} />
          ))}

          <Label text="DELIVER" />
          {run.drops.map((drop) => (
            <DropCard key={drop.delivery_id} drop={drop} onChange={onChange} />
          ))}
        </>
      ) : null}

      {out ? (
        finishing ? (
          <Card title="Finish the run?">
            <MutedText>
              {counts.to_collect > 0
                ? `${counts.to_collect} not collected will go on tomorrow's run.`
                : 'Every parcel has been delivered or marked not delivered.'}
            </MutedText>
            <Button
              label="Yes, finish"
              loading={action.busy}
              onPress={() => void action.run(() => parcelsApi.finish(run.run_id), { then: onChange })}
            />
            <Button label="Not yet" variant="plain" onPress={() => setFinishing(false)} />
          </Card>
        ) : (
          <Button label="Finish the run" variant="plain" onPress={() => setFinishing(true)} />
        )
      ) : null}
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

function PickupCard({
  pickup,
  runId,
  canCollect,
  onChange,
}: {
  pickup: Pickup;
  runId: number;
  canCollect: boolean;
  onChange: () => Promise<void>;
}) {
  const action = useAction();

  return (
    <Card title={`${pickup.name} · ${pickup.parcels} ${pickup.parcels === 1 ? 'parcel' : 'parcels'}`}>
      <MutedText>
        {pickup.address}
        {pickup.landmark ? `. ${pickup.landmark}` : ''}
      </MutedText>
      <Notice text={action.error} />
      {pickup.collected ? (
        <MutedText>✓ Collected</MutedText>
      ) : (
        <View style={styles.row}>
          <Button label="Call" variant="plain" style={styles.grow} onPress={() => void Linking.openURL(`tel:${pickup.phone}`)} />
          {canCollect ? (
            <Button
              label="Collected"
              style={styles.grow}
              loading={action.busy}
              onPress={() => void action.run(() => parcelsApi.collect(runId, pickup.business_id), { then: onChange })}
            />
          ) : null}
        </View>
      )}
      {!pickup.collected && canCollect ? <MutedText>Each customer is texted a code when you press Collected.</MutedText> : null}
    </Card>
  );
}

function DropCard({ drop, onChange }: { drop: Drop; onChange: () => Promise<void> }) {
  const theme = useTheme();
  const action = useAction();
  const [code, setCode] = useState('');
  const [failing, setFailing] = useState(false);
  const [reason, setReason] = useState<string | null>(null);
  const withMe = drop.status === 'picked_up';

  return (
    <View style={[styles.drop, { borderColor: withMe ? theme.primary : theme.border, opacity: withMe ? 1 : 0.7 }]}>
      <ThemedText type="smallBold">
        {drop.position}. {drop.customer_name}
      </ThemedText>
      <MutedText>
        {drop.address}
        {drop.landmark ? `. ${drop.landmark}` : ''}
      </MutedText>
      {drop.notes ? <MutedText>Note: {drop.notes}</MutedText> : null}
      <MutedText>
        From {drop.business}
        {drop.reference ? `, order ${drop.reference}` : ''}
      </MutedText>

      {drop.status === 'assigned' ? <MutedText>Collect it from {drop.business} first.</MutedText> : null}
      {drop.status === 'delivered' ? <ThemedText type="smallBold">✓ Delivered</ThemedText> : null}
      {drop.status === 'failed' ? <MutedText>✗ Not delivered: {drop.failure_reason}</MutedText> : null}

      {withMe ? (
        <>
          <Notice text={action.error} />
          {failing ? (
            <>
              {REASONS.map((item) => (
                <Choice key={item} label={item} selected={reason === item} onPress={() => setReason(item)} />
              ))}
              <Button
                label="Tell the customer"
                disabled={!reason}
                loading={action.busy}
                onPress={() => reason && void action.run(() => parcelsApi.notDelivered(drop.delivery_id, reason), { then: onChange })}
              />
              <Button label="Back" variant="plain" onPress={() => setFailing(false)} />
            </>
          ) : (
            <>
              <TextField
                label="Her code"
                value={code}
                onChangeText={(value) => {
                  action.clear();
                  setCode(value.replace(/\D+/g, ''));
                }}
                keyboardType="number-pad"
                maxLength={6}
                placeholder="From her text message"
              />
              <Button
                label="Delivered"
                disabled={code.length < 4}
                loading={action.busy}
                onPress={() => void action.run(() => parcelsApi.deliver(drop.delivery_id, code), { then: onChange })}
              />
              <View style={styles.row}>
                <Button
                  label="Call her"
                  variant="plain"
                  style={styles.grow}
                  onPress={() => void Linking.openURL(`tel:${drop.customer_phone}`)}
                />
                <Button label="Couldn't deliver" variant="plain" style={styles.grow} onPress={() => setFailing(true)} />
              </View>
            </>
          )}
        </>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  run: { gap: Spacing.three },
  label: { letterSpacing: 1.2, fontSize: 12, marginTop: Spacing.two },
  row: { flexDirection: 'row', gap: Spacing.two },
  grow: { flex: 1 },
  drop: { borderWidth: 2, borderRadius: 16, padding: Spacing.three, gap: Spacing.two },
});

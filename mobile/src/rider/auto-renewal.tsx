import { useState } from 'react';
import { ActivityIndicator, View } from 'react-native';

import { Button } from '@/components/button';
import { Card } from '@/components/card';
import { MutedText } from '@/components/muted-text';
import { Notice } from '@/components/notice';
import { TextField } from '@/components/text-field';
import { Spacing } from '@/constants/theme';
import { useAction } from '@/hooks/use-action';
import { useTheme } from '@/hooks/use-theme';

import { riderApi, type AutoRenewal } from './api';

/**
 * A direct debit she has asked for and not yet approved. Most networks send a request to approve
 * with her PIN. Some text a code instead, and she types it here.
 */
type ProgressProps = { renewal: AutoRenewal; onConfirmed: () => void; onCancelled: () => void };

export function AutoRenewalProgress({ renewal, onConfirmed, onCancelled }: ProgressProps) {
  const theme = useTheme();
  const [code, setCode] = useState('');
  const action = useAction();

  return (
    <Card title={renewal.needs_code ? 'Enter your code' : 'Check your phone'}>
      <MutedText>
        {renewal.plan} plan, {renewal.amount_label} each time, from {renewal.network} ending {renewal.wallet}.
      </MutedText>
      <MutedText>{renewal.message}</MutedText>

      {renewal.needs_code ? (
        <>
          <TextField
            label={renewal.code_prefix ? `Code (after ${renewal.code_prefix}-)` : 'Code'}
            value={code}
            onChangeText={setCode}
            keyboardType="number-pad"
            autoComplete="one-time-code"
          />
          <Button
            label="Confirm"
            loading={action.busy}
            onPress={() => (code.trim() ? action.run(() => riderApi.sendAutoRenewalCode(code.trim()), { then: onConfirmed }) : action.fail('Enter the code.'))}
          />
        </>
      ) : (
        <View style={{ flexDirection: 'row', alignItems: 'center', gap: Spacing.two }}>
          <ActivityIndicator color={theme.primary} />
          <MutedText>Waiting for you to approve. Nothing is taken until you do.</MutedText>
        </View>
      )}

      <Notice text={action.error} />
      <Button label="Cancel" variant="plain" onPress={() => action.run(riderApi.stopAutoRenewal, { then: onCancelled })} />
    </Card>
  );
}

/** On the home screen, under her subscription: that it renews by itself, and the way to stop that. */
export function AutoRenewalLine({ renewal, onStopped }: { renewal: AutoRenewal; onStopped: () => void }) {
  const [confirming, setConfirming] = useState(false);
  const action = useAction();

  return (
    <View style={{ gap: Spacing.two }}>
      <MutedText>
        Renews by itself: {renewal.amount_label} from {renewal.network} ending {renewal.wallet}, the day before it ends.
      </MutedText>
      <Notice text={action.error} />
      {confirming ? (
        <>
          <MutedText>Stop it? Nothing more will be taken. The plan you have paid for stays as it is.</MutedText>
          <Button label="Yes, stop automatic payment" loading={action.busy} onPress={() => action.run(riderApi.stopAutoRenewal, { then: onStopped })} />
          <Button label="Keep it" variant="plain" onPress={() => setConfirming(false)} />
        </>
      ) : (
        <Button label="Stop automatic payment" variant="plain" onPress={() => setConfirming(true)} />
      )}
    </View>
  );
}

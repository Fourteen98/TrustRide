import { ActivityIndicator, View } from 'react-native';

import { Button } from '@/components/button';
import { Card } from '@/components/card';
import { MutedText } from '@/components/muted-text';
import { SupportButtons } from '@/components/support-buttons';
import { Spacing } from '@/constants/theme';
import { useTheme } from '@/hooks/use-theme';

import type { Purchase } from './api';

type Props = {
  purchase: Purchase;
  support: { phone: string | null; whatsapp: string | null };
  onDone: () => void;
  onRetry: () => void;
};

const titles: Record<Purchase['status'], string> = {
  pending: 'Check your phone',
  activated: 'Paid',
  failed: 'Payment not completed',
  needs_attention: 'We got your payment',
};

/** Where a payment stands, in her words, with the one thing she can do next. */
export function PaymentProgress({ purchase, support, onDone, onRetry }: Props) {
  const theme = useTheme();

  return (
    <Card title={titles[purchase.status]}>
      <MutedText>
        {purchase.plan} plan, {purchase.amount_label}, {purchase.network}.
      </MutedText>
      <MutedText>{purchase.message}</MutedText>

      {purchase.status === 'pending' ? (
        <View style={{ flexDirection: 'row', alignItems: 'center', gap: Spacing.two }}>
          <ActivityIndicator color={theme.primary} />
          <MutedText>Waiting for you to approve. No prompt? Dial your network's mobile money code and look under approvals.</MutedText>
        </View>
      ) : null}

      {purchase.status === 'activated' ? <Button label="Done" onPress={onDone} /> : null}
      {purchase.status === 'failed' ? <Button label="Try again" onPress={onRetry} /> : null}
      {purchase.status === 'needs_attention' ? <SupportButtons {...support} /> : null}
    </Card>
  );
}

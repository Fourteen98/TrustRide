import { useRouter } from 'expo-router';
import { useState } from 'react';
import { ActivityIndicator, View } from 'react-native';

import { useAuth } from '@/auth/auth-context';
import { Button } from '@/components/button';
import { Card } from '@/components/card';
import { Loadable } from '@/components/loadable';
import { MutedText } from '@/components/muted-text';
import { Notice } from '@/components/notice';
import { Screen } from '@/components/screen';
import { Spacing } from '@/constants/theme';
import { useLoader } from '@/hooks/use-loader';
import { usePoll } from '@/hooks/use-poll';
import { useTheme } from '@/hooks/use-theme';
import { riderApi, type RiderHome } from '@/rider/api';
import { AutoRenewalProgress } from '@/rider/auto-renewal';
import { PayForm } from '@/rider/pay-form';
import { PaymentProgress } from '@/rider/payment-progress';

/**
 * Task R3. She picks a plan and pays with mobile money, either once (a prompt each time) or by
 * direct debit (approve once, renewed by itself).
 *
 * What the screen shows is read off the server's home answer, never remembered here. So it is right
 * whether she pays now, comes back later, or the approval arrives while the app was closed.
 * While something is under way the screen asks again every few seconds.
 */
export default function PlansScreen() {
  const router = useRouter();
  const theme = useTheme();
  const { account } = useAuth();
  // What she started on this visit, so the screen can say "paid" or "not approved" when it settles.
  const [started, setStarted] = useState<'once' | 'auto' | null>(null);
  // True after she taps "Try again" on a failed payment, to bring the form back.
  const [retrying, setRetrying] = useState(false);
  const [fresh, setFresh] = useState<RiderHome | null>(null);

  const state = useLoader(async () => {
    const [choices, home] = await Promise.all([riderApi.plans(), riderApi.home()]);

    return { ...choices, home };
  });

  const home = fresh ?? state.data?.home ?? null;
  const auto = home?.auto_renewal ?? null;
  const takingFirstPayment = auto?.status === 'active' && !home?.subscription && !home?.purchase;
  const underWay = home?.purchase?.status === 'pending' || auto?.status === 'pending' || takingFirstPayment;
  const refresh = async () => setFresh(await riderApi.home());

  // Keep asking while something is under way. A direct debit that was declined has nothing left to wait for.
  const declined = started === 'auto' && !auto && !home?.purchase;

  usePoll(refresh, underWay || (started !== null && !home?.subscription && !declined));

  const begin = (how: 'once' | 'auto') => {
    setStarted(how);
    setRetrying(false);
    void refresh();
  };

  return (
    <Screen edges={['bottom']}>
      <Loadable state={state}>
        {({ plans, networks }) => {
          if (!home) {
            return null;
          }

          if (home.purchase && !(home.purchase.status === 'failed' && retrying)) {
            return <PaymentProgress purchase={home.purchase} support={home.support} onDone={() => router.back()} onRetry={() => setRetrying(true)} />;
          }

          if (auto?.status === 'pending') {
            return <AutoRenewalProgress renewal={auto} onConfirmed={() => void refresh()} onCancelled={() => (setStarted(null), void refresh())} />;
          }

          if (takingFirstPayment) {
            return (
              <Card title="Approved">
                <View style={{ flexDirection: 'row', alignItems: 'center', gap: Spacing.two }}>
                  <ActivityIndicator color={theme.primary} />
                  <MutedText>Taking your first payment of {auto.amount_label}. This takes a moment.</MutedText>
                </View>
              </Card>
            );
          }

          if (started && home.subscription) {
            return (
              <Card title="Paid">
                <MutedText>
                  Your seat is yours.{auto?.status === 'active' ? ' Your plan will renew by itself. You can stop that on the home screen.' : ''}
                </MutedText>
                <Button label="Done" onPress={() => router.back()} />
              </Card>
            );
          }

          if (home.seats.length === 0) {
            return <Notice tone="info" text="Apply for a seat first. Once your seat is reserved you can pay for it here." />;
          }

          if (auto?.status === 'active') {
            return <Notice tone="info" text="Your plan renews by itself, so there is nothing to pay. You can stop that on the home screen." />;
          }

          if (home.renewal) {
            return <Notice tone="info" text="You have already paid for your next subscription." />;
          }

          return (
            <>
              {declined ? <Notice text="The direct debit was not approved, so nothing was taken. Try again, or pay just this time." /> : null}
              <PayForm plans={plans} networks={networks} defaultPhone={account?.phone ?? ''} onStarted={begin} />
            </>
          );
        }}
      </Loadable>
    </Screen>
  );
}

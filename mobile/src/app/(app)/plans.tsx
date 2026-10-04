import { useRouter } from 'expo-router';
import { useState } from 'react';

import { Loadable } from '@/components/loadable';
import { Notice } from '@/components/notice';
import { Screen } from '@/components/screen';
import { useAuth } from '@/auth/auth-context';
import { useLoader } from '@/hooks/use-loader';
import { usePoll } from '@/hooks/use-poll';
import { riderApi, type Purchase } from '@/rider/api';
import { PayForm } from '@/rider/pay-form';
import { PaymentProgress } from '@/rider/payment-progress';

/**
 * Task R3. She picks a plan and pays with mobile money. The server sends a prompt to her phone,
 * and this screen asks every few seconds until the payment is settled one way or the other.
 */
export default function PlansScreen() {
  const router = useRouter();
  const { account } = useAuth();
  // The payment this screen is following. Undefined until she pays, or until home says one is under way.
  const [followed, setFollowed] = useState<Purchase | null | undefined>(undefined);

  const state = useLoader(async () => {
    const [choices, home] = await Promise.all([riderApi.plans(), riderApi.home()]);

    return { ...choices, home };
  });

  const purchase = followed === undefined ? (state.data?.home.purchase ?? null) : followed;

  usePoll(async () => setFollowed(await riderApi.purchase()), purchase?.status === 'pending');

  return (
    <Screen edges={['bottom']}>
      <Loadable state={state}>
        {({ plans, networks, home }) =>
          purchase ? (
            <PaymentProgress purchase={purchase} support={home.support} onDone={() => router.back()} onRetry={() => setFollowed(null)} />
          ) : home.seats.length === 0 ? (
            <Notice tone="info" text="Apply for a seat first. Once your seat is reserved you can pay for it here." />
          ) : home.renewal ? (
            <Notice tone="info" text="You have already paid for your next subscription." />
          ) : (
            <PayForm plans={plans} networks={networks} defaultPhone={account?.phone ?? ''} onStarted={setFollowed} />
          )
        }
      </Loadable>
    </Screen>
  );
}

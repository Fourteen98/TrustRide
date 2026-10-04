import { Linking, View } from 'react-native';

import { Button } from '@/components/button';
import { Card } from '@/components/card';
import { Loadable } from '@/components/loadable';
import { MutedText } from '@/components/muted-text';
import { Notice } from '@/components/notice';
import { Screen } from '@/components/screen';
import { Spacing } from '@/constants/theme';
import { useLoader } from '@/hooks/use-loader';
import { riderApi } from '@/rider/api';

/**
 * Task R3, without the payment. She sees the plans and how to pay. Paying in the app comes with Hubtel.
 * Until then she pays TrustRide directly and an operator switches her subscription on.
 */
export default function PlansScreen() {
  const state = useLoader(async () => {
    const [plans, home] = await Promise.all([riderApi.plans(), riderApi.home()]);

    return { plans, support: home.support };
  });

  return (
    <Screen edges={['bottom']}>
      <Loadable state={state}>
        {({ plans, support: { phone, whatsapp } }) => (
          <>
            {plans.map((plan) => (
              <Card key={plan.id} title={`${plan.name} · ${plan.price_label}`}>
                {`${plan.duration_days} days. ${plan.trips_per_day === 1 ? 'One trip a day.' : 'There and back each day.'} If you don't ride: ${plan.no_show_rule.toLowerCase()}.`}
              </Card>
            ))}

            <Card title="How to pay">
              <MutedText>
                Paying in the app is coming. For now, tell us which plan you want and pay TrustRide directly. We switch your seat on as soon
                as we see it. This is for your subscription only. Your fare still goes to the mate.
              </MutedText>
              <View style={{ gap: Spacing.two }}>
                {whatsapp ? (
                  <Button label="Message us on WhatsApp" onPress={() => Linking.openURL(`https://wa.me/${whatsapp.replace(/\D+/g, '')}`)} />
                ) : null}
                {phone ? <Button label={`Call ${phone}`} variant="plain" onPress={() => Linking.openURL(`tel:${phone}`)} /> : null}
                {!phone && !whatsapp ? <Notice tone="info" text="Ask the TrustRide team how to pay." /> : null}
              </View>
            </Card>
          </>
        )}
      </Loadable>
    </Screen>
  );
}

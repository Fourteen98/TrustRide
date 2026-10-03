import { ActivityIndicator, Linking, View } from 'react-native';

import { Button } from '@/components/button';
import { Card } from '@/components/card';
import { Notice } from '@/components/notice';
import { Screen } from '@/components/screen';
import { ThemedText } from '@/components/themed-text';
import { Spacing } from '@/constants/theme';
import { useTheme } from '@/hooks/use-theme';
import { riderApi } from '@/rider/api';
import { useLoader } from '@/rider/use-loader';

/**
 * Task R3, without the payment. She sees the plans and how to pay. Paying in the app comes with Hubtel.
 * Until then she pays TrustRide directly and an operator switches her subscription on.
 */
export default function PlansScreen() {
  const theme = useTheme();
  const { data, error, loading, reload } = useLoader(async () => {
    const [plans, home] = await Promise.all([riderApi.plans(), riderApi.home()]);

    return { plans, support: home.support };
  });

  const phone = data?.support.phone;
  const whatsapp = data?.support.whatsapp;

  return (
    <Screen edges={['bottom']}>
      <Notice text={error} />
      {loading ? <ActivityIndicator color={theme.primary} /> : null}
      {error && !data ? <Button label="Try again" variant="plain" onPress={reload} /> : null}

      {data?.plans.map((plan) => (
        <Card key={plan.id} title={`${plan.name} · ${plan.price_label}`}>
          <ThemedText type="small" themeColor="textSecondary">
            {plan.duration_days} days. {plan.trips_per_day === 1 ? 'One trip a day.' : 'There and back each day.'} If you don't ride:{' '}
            {plan.no_show_rule.toLowerCase()}.
          </ThemedText>
        </Card>
      ))}

      {data ? (
        <Card title="How to pay">
          <ThemedText type="small" themeColor="textSecondary">
            Paying in the app is coming. For now, tell us which plan you want and pay TrustRide directly. We switch your seat on as soon
            as we see it. This is for your subscription only. Your fare still goes to the mate.
          </ThemedText>
          <View style={{ gap: Spacing.two }}>
            {whatsapp ? (
              <Button label="Message us on WhatsApp" onPress={() => Linking.openURL(`https://wa.me/${whatsapp.replace(/\D+/g, '')}`)} />
            ) : null}
            {phone ? <Button label={`Call ${phone}`} variant="plain" onPress={() => Linking.openURL(`tel:${phone}`)} /> : null}
            {!phone && !whatsapp ? <Notice tone="info" text="Ask the TrustRide team how to pay." /> : null}
          </View>
        </Card>
      ) : null}
    </Screen>
  );
}

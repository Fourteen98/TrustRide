import { Linking, View } from 'react-native';

import { Spacing } from '@/constants/theme';

import { Button } from './button';
import { Notice } from './notice';

type Props = { phone: string | null; whatsapp: string | null; fallback?: string };

/** The ways to reach the TrustRide team: WhatsApp and a call. Shows a plain line if neither is set. */
export function SupportButtons({ phone, whatsapp, fallback = 'Ask the TrustRide team for help.' }: Props) {
  return (
    <View style={{ gap: Spacing.two }}>
      {whatsapp ? (
        <Button label="Message us on WhatsApp" variant="plain" onPress={() => Linking.openURL(`https://wa.me/${whatsapp.replace(/\D+/g, '')}`)} />
      ) : null}
      {phone ? <Button label={`Call ${phone}`} variant="plain" onPress={() => Linking.openURL(`tel:${phone}`)} /> : null}
      {!phone && !whatsapp ? <Notice tone="info" text={fallback} /> : null}
    </View>
  );
}

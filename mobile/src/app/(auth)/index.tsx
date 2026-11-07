import { Image } from 'expo-image';
import { useRouter } from 'expo-router';
import { useState } from 'react';
import { View } from 'react-native';

import { useAuth } from '@/auth/auth-context';
import { Button } from '@/components/button';
import { Screen } from '@/components/screen';
import { TextField } from '@/components/text-field';
import { ThemedText } from '@/components/themed-text';
import { Spacing } from '@/constants/theme';
import { useAction } from '@/hooks/use-action';
import { looksLikeGhanaPhone } from '@/lib/phone';

/** Step 1 of login: the phone number. Riders, drivers and mates all start here. */
export default function PhoneScreen() {
  const router = useRouter();
  const { requestCode } = useAuth();
  const send = useAction();
  const [phone, setPhone] = useState('');

  function submit() {
    if (!looksLikeGhanaPhone(phone)) {
      return send.fail('Enter a valid Ghana phone number, like 024 123 4567.');
    }

    return send.run(() => requestCode(phone), { then: () => router.push({ pathname: '/code', params: { phone } }) });
  }

  return (
    <Screen>
      <View style={{ gap: Spacing.two, marginTop: Spacing.six }}>
        <Image
          source={require('@/assets/images/logo.png')}
          style={{ width: 160, height: 36, marginBottom: Spacing.two }}
          contentFit="contain"
          contentPosition="left"
          accessibilityLabel="Aveo"
        />
        <ThemedText themeColor="textSecondary">Beat the bus-stop hustle. Enter your phone number and we'll text you a code.</ThemedText>
      </View>

      <TextField
        label="Phone number"
        value={phone}
        onChangeText={setPhone}
        error={send.error}
        placeholder="024 123 4567"
        keyboardType="phone-pad"
        autoComplete="tel"
        textContentType="telephoneNumber"
        autoFocus
        onSubmitEditing={submit}
      />

      <Button label="Send me a code" onPress={submit} loading={send.busy} />
    </Screen>
  );
}

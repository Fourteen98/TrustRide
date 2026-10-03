import { useRouter } from 'expo-router';
import { useState } from 'react';
import { View } from 'react-native';

import { useAuth } from '@/auth/auth-context';
import { Button } from '@/components/button';
import { Screen } from '@/components/screen';
import { TextField } from '@/components/text-field';
import { ThemedText } from '@/components/themed-text';
import { Spacing } from '@/constants/theme';
import { looksLikeGhanaPhone } from '@/lib/phone';

/** Step 1 of login: the phone number. Riders, drivers and mates all start here. */
export default function PhoneScreen() {
  const router = useRouter();
  const { requestCode } = useAuth();
  const [phone, setPhone] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [sending, setSending] = useState(false);

  async function submit() {
    if (!looksLikeGhanaPhone(phone)) {
      setError('Enter a valid Ghana phone number, like 024 123 4567.');

      return;
    }

    setError(null);
    setSending(true);

    try {
      await requestCode(phone);
      router.push({ pathname: '/code', params: { phone } });
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Something went wrong. Try again.');
    } finally {
      setSending(false);
    }
  }

  return (
    <Screen>
      <View style={{ gap: Spacing.two, marginTop: Spacing.six }}>
        <ThemedText type="subtitle">TrustRide</ThemedText>
        <ThemedText themeColor="textSecondary">
          Beat the bus-stop hustle. Enter your phone number and we'll text you a code.
        </ThemedText>
      </View>

      <TextField
        label="Phone number"
        value={phone}
        onChangeText={setPhone}
        error={error}
        placeholder="024 123 4567"
        keyboardType="phone-pad"
        autoComplete="tel"
        textContentType="telephoneNumber"
        autoFocus
        onSubmitEditing={submit}
      />

      <Button label="Send me a code" onPress={submit} loading={sending} />
    </Screen>
  );
}

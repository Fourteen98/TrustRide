import { useLocalSearchParams, useRouter } from 'expo-router';
import { useState } from 'react';
import { View } from 'react-native';

import { useAuth } from '@/auth/auth-context';
import { Button } from '@/components/button';
import { Screen } from '@/components/screen';
import { TextField } from '@/components/text-field';
import { ThemedText } from '@/components/themed-text';
import { Spacing } from '@/constants/theme';

/** Step 2 of login: the six-digit code. A good code signs the person in and the app moves to home by itself. */
export default function CodeScreen() {
  const router = useRouter();
  const { phone } = useLocalSearchParams<{ phone: string }>();
  const { verifyCode, requestCode } = useAuth();
  const [code, setCode] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);
  const [checking, setChecking] = useState(false);
  const [resending, setResending] = useState(false);

  async function submit(value = code) {
    if (value.length !== 6) {
      setError('The code has six digits.');

      return;
    }

    setError(null);
    setChecking(true);

    try {
      await verifyCode(phone, value);
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Something went wrong. Try again.');
      setChecking(false);
    }
  }

  async function resend() {
    setError(null);
    setNotice(null);
    setResending(true);

    try {
      await requestCode(phone);
      setCode('');
      setNotice('We sent a new code. The old one no longer works.');
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Something went wrong. Try again.');
    } finally {
      setResending(false);
    }
  }

  function change(text: string) {
    const digits = text.replace(/\D+/g, '').slice(0, 6);
    setCode(digits);

    if (digits.length === 6) {
      void submit(digits);
    }
  }

  return (
    <Screen>
      <View style={{ gap: Spacing.two, marginTop: Spacing.six }}>
        <ThemedText type="subtitle">Enter your code</ThemedText>
        <ThemedText themeColor="textSecondary">We texted a six-digit code to {phone}. It lasts 10 minutes.</ThemedText>
      </View>

      <TextField
        label="Code"
        value={code}
        onChangeText={change}
        error={error}
        placeholder="123456"
        keyboardType="number-pad"
        autoComplete="one-time-code"
        textContentType="oneTimeCode"
        maxLength={6}
        autoFocus
      />

      {notice ? <ThemedText type="small">{notice}</ThemedText> : null}

      <Button label="Log in" onPress={() => submit()} loading={checking} />
      <Button label="Send a new code" variant="plain" onPress={resend} loading={resending} />
      <Button label="Use a different number" variant="plain" onPress={() => router.back()} />
    </Screen>
  );
}

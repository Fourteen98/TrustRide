import { useLocalSearchParams, useRouter } from 'expo-router';
import { useState } from 'react';
import { View } from 'react-native';

import { useAuth } from '@/auth/auth-context';
import { Button } from '@/components/button';
import { MutedText } from '@/components/muted-text';
import { Screen } from '@/components/screen';
import { TextField } from '@/components/text-field';
import { ThemedText } from '@/components/themed-text';
import { Spacing } from '@/constants/theme';
import { useAction } from '@/hooks/use-action';

/** Step 2 of login: the six-digit code. A good code signs the person in and the app moves to home by itself. */
export default function CodeScreen() {
  const router = useRouter();
  const { phone } = useLocalSearchParams<{ phone: string }>();
  const { verifyCode, requestCode } = useAuth();
  const check = useAction();
  const resend = useAction();
  const [code, setCode] = useState('');
  const [resent, setResent] = useState(false);

  function submit(value = code) {
    if (value.length !== 6) {
      return check.fail('The code has six digits.');
    }

    return check.run(() => verifyCode(phone, value));
  }

  function sendAgain() {
    check.clear();
    setResent(false);

    return resend.run(() => requestCode(phone), {
      then: () => {
        setCode('');
        setResent(true);
      },
    });
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
        error={check.error ?? resend.error}
        placeholder="123456"
        keyboardType="number-pad"
        autoComplete="one-time-code"
        textContentType="oneTimeCode"
        maxLength={6}
        autoFocus
      />

      {resent ? <MutedText>We sent a new code. The old one no longer works.</MutedText> : null}

      <Button label="Log in" onPress={() => submit()} loading={check.busy} />
      <Button label="Send a new code" variant="plain" onPress={sendAgain} loading={resend.busy} />
      <Button label="Use a different number" variant="plain" onPress={() => router.back()} />
    </Screen>
  );
}

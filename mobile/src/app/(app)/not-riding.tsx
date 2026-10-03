import { useRouter } from 'expo-router';
import { useState } from 'react';
import { ActivityIndicator, StyleSheet, View } from 'react-native';

import { Button } from '@/components/button';
import { Card } from '@/components/card';
import { Choice } from '@/components/choice';
import { Notice } from '@/components/notice';
import { Screen } from '@/components/screen';
import { ThemedText } from '@/components/themed-text';
import { Spacing } from '@/constants/theme';
import { useTheme } from '@/hooks/use-theme';
import { longDate, moment, shortDate } from '@/lib/dates';
import { riderApi, type NoticePreview } from '@/rider/api';
import { useLoader } from '@/rider/use-loader';

/**
 * She says she won't ride on a day. Before she confirms, the app asks the server what will happen,
 * so she knows whether the day is added back. The server decides. The app only shows it.
 */
export default function NotRidingScreen() {
  const router = useRouter();
  const theme = useTheme();
  const { data: home, error: loadError, loading } = useLoader(riderApi.home);

  const [date, setDate] = useState<string | null>(null);
  const [preview, setPreview] = useState<NoticePreview | null>(null);
  const [checking, setChecking] = useState(false);
  const [sending, setSending] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function choose(day: string) {
    setDate(day);
    setPreview(null);
    setError(null);
    setChecking(true);

    try {
      setPreview(await riderApi.previewNotice(day));
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Something went wrong. Try again.');
    } finally {
      setChecking(false);
    }
  }

  async function confirm() {
    if (!date) {
      return;
    }

    setSending(true);
    setError(null);

    try {
      await riderApi.giveNotice(date);
      router.back();
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Something went wrong. Try again.');
      setSending(false);
    }
  }

  return (
    <Screen edges={['bottom']}>
      <Notice text={loadError} />
      {loading ? <ActivityIndicator color={theme.primary} /> : null}

      {home ? (
        <>
          <ThemedText themeColor="textSecondary">Which day won't you ride? We'll tell the driver not to wait for you.</ThemedText>

          {home.ride_dates.length === 0 ? <Notice tone="info" text="There are no days to choose in the next two weeks." /> : null}

          <View style={styles.chips} accessibilityRole="radiogroup" accessibilityLabel="Day">
            {home.ride_dates.map((day) => (
              <Choice key={day} shape="chip" label={shortDate(day)} selected={date === day} onPress={() => choose(day)} />
            ))}
          </View>
        </>
      ) : null}

      {checking ? <ActivityIndicator color={theme.primary} /> : null}

      {preview && date ? (
        <Card title={preview.credited ? 'This day will be added back' : 'This day will not be added back'}>
          <ThemedText type="small" themeColor="textSecondary">
            {preview.credited
              ? `Your subscription will end one day later, to make up for ${longDate(date)}.`
              : preview.in_time
                ? `Your plan's rule: ${preview.rule.toLowerCase()}.`
                : `To get a day back you need to tell us by ${moment(preview.deadline)}. That has passed, but telling us still helps the driver.`}
          </ThemedText>
          <ThemedText type="small" themeColor="textSecondary">
            You can't undo this in the app.
          </ThemedText>
          <Button label={`I won't ride on ${shortDate(date)}`} onPress={confirm} loading={sending} />
        </Card>
      ) : null}

      <Notice text={error} />
    </Screen>
  );
}

const styles = StyleSheet.create({
  chips: { flexDirection: 'row', flexWrap: 'wrap', gap: Spacing.two },
});

import { useRouter } from 'expo-router';
import { useState } from 'react';
import { ActivityIndicator, StyleSheet, View } from 'react-native';

import { useAuth } from '@/auth/auth-context';
import { Button } from '@/components/button';
import { Choice } from '@/components/choice';
import { Notice } from '@/components/notice';
import { Screen } from '@/components/screen';
import { TextField } from '@/components/text-field';
import { ThemedText } from '@/components/themed-text';
import { Spacing } from '@/constants/theme';
import { useTheme } from '@/hooks/use-theme';
import { clock } from '@/lib/dates';
import { riderApi } from '@/rider/api';
import { useLoader } from '@/rider/use-loader';

/** Task R2. She picks a route, a stop and a time. Only times a van actually runs are offered. */
export default function ApplyScreen() {
  const router = useRouter();
  const theme = useTheme();
  const { account, refresh } = useAuth();
  const { data: routes, error: loadError, loading, reload } = useLoader(riderApi.routes);

  const [name, setName] = useState(account?.name ?? '');
  const [routeId, setRouteId] = useState<number | null>(null);
  const [stopId, setStopId] = useState<number | null>(null);
  const [morning, setMorning] = useState<string | null>(null);
  const [evening, setEvening] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [sending, setSending] = useState(false);

  // With one route on offer there is nothing to choose, so it is picked for her.
  const route = routes?.find((r) => r.id === routeId) ?? (routes?.length === 1 ? routes[0] : null);

  /** Any new choice clears the last complaint, so an old error never sits under a corrected form. */
  function pick(change: () => void) {
    setError(null);
    change();
  }

  function chooseRoute(id: number) {
    setError(null);
    setRouteId(id);
    setStopId(null);
    setMorning(null);
    setEvening(null);
  }

  async function submit() {
    if (name.trim().length < 2) {
      return setError('Enter your name.');
    }

    if (!route || !stopId) {
      return setError('Choose your route and your stop.');
    }

    if (!morning && !evening) {
      return setError('Choose the time you want.');
    }

    setError(null);
    setSending(true);

    try {
      await riderApi.apply({ name: name.trim(), route_id: route.id, stop_id: stopId, morning_time: morning, evening_time: evening });
      await refresh().catch(() => undefined); // her name on the account may have just been set
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
      {loadError && !routes ? <Button label="Try again" variant="plain" onPress={reload} /> : null}

      {routes && routes.length === 0 ? (
        <Notice tone="info" text="We're not running any routes yet. Check back soon." />
      ) : null}

      {routes && routes.length > 0 ? (
        <>
          <TextField label="Your name" value={name} onChangeText={setName} autoComplete="name" textContentType="name" />

          {routes.length > 1 ? (
            <Group title="Route">
              {routes.map((r) => (
                <Choice key={r.id} label={r.name} detail={`To ${r.destination}`} selected={route?.id === r.id} onPress={() => chooseRoute(r.id)} />
              ))}
            </Group>
          ) : (
            <Group title="Route">
              <ThemedText>{routes[0].name}</ThemedText>
            </Group>
          )}

          {route ? (
            <>
              <Group title="Where do you get on?">
                {route.stops.map((stop) => (
                  <Choice key={stop.id} label={stop.name} detail={stop.landmark} selected={stopId === stop.id} onPress={() => pick(() => setStopId(stop.id))} />
                ))}
              </Group>

              {route.morning_times.length > 0 ? (
                <Group title="Morning, to your destination" hint="This is when the van leaves the first stop.">
                  <View style={styles.chips}>
                    {route.morning_times.map((slot) => (
                      <Choice
                        key={slot.time}
                        shape="chip"
                        label={clock(slot.time)}
                        detail={slot.seats_free ? null : 'Full, join the list'}
                        selected={morning === slot.time}
                        onPress={() => pick(() => setMorning(morning === slot.time ? null : slot.time))}
                      />
                    ))}
                  </View>
                </Group>
              ) : null}

              {route.evening_times.length > 0 ? (
                <Group title="Evening, back home" hint="Leave this empty if you only want the morning.">
                  <View style={styles.chips}>
                    {route.evening_times.map((slot) => (
                      <Choice
                        key={slot.time}
                        shape="chip"
                        label={clock(slot.time)}
                        detail={slot.seats_free ? null : 'Full, join the list'}
                        selected={evening === slot.time}
                        onPress={() => pick(() => setEvening(evening === slot.time ? null : slot.time))}
                      />
                    ))}
                  </View>
                </Group>
              ) : null}
            </>
          ) : null}

          <Notice text={error} />
          <Button label="Send my application" onPress={submit} loading={sending} />
        </>
      ) : null}
    </Screen>
  );
}

function Group({ title, hint, children }: { title: string; hint?: string; children: React.ReactNode }) {
  return (
    <View style={styles.group} accessibilityRole="radiogroup" accessibilityLabel={title}>
      <ThemedText type="smallBold">{title}</ThemedText>
      {hint ? (
        <ThemedText type="small" themeColor="textSecondary">
          {hint}
        </ThemedText>
      ) : null}
      {children}
    </View>
  );
}

const styles = StyleSheet.create({
  group: { gap: Spacing.two },
  chips: { flexDirection: 'row', flexWrap: 'wrap', gap: Spacing.two },
});

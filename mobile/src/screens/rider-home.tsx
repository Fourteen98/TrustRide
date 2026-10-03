import { useRouter } from 'expo-router';
import { useState } from 'react';
import { ActivityIndicator, View } from 'react-native';

import { useAuth } from '@/auth/auth-context';
import { Button } from '@/components/button';
import { Card } from '@/components/card';
import { HomeHeader } from '@/components/home-header';
import { Notice } from '@/components/notice';
import { Screen } from '@/components/screen';
import { ThemedText } from '@/components/themed-text';
import { Spacing } from '@/constants/theme';
import { useTheme } from '@/hooks/use-theme';
import { clock, longDate, shortDate } from '@/lib/dates';
import { riderApi, type RiderHome as HomeData } from '@/rider/api';
import { SeatCard } from '@/rider/seat-card';
import { useLoader } from '@/rider/use-loader';

/**
 * The rider's home. One call to the server, and what comes back decides what she sees:
 * nothing yet, an application waiting, a seat waiting for payment, or a seat with a running subscription.
 */
export function RiderHome() {
  const theme = useTheme();
  const { data, error, loading, refreshing, reload } = useLoader(riderApi.home);

  return (
    <Screen refreshing={refreshing} onRefresh={reload}>
      <HomeHeader />
      <Notice text={error} />
      {loading ? <ActivityIndicator color={theme.primary} style={{ marginTop: Spacing.five }} /> : null}
      {data ? <Content home={data} reload={reload} /> : null}
      {error && !data ? <Button label="Try again" variant="plain" onPress={reload} /> : null}
    </Screen>
  );
}

function Content({ home, reload }: { home: HomeData; reload: () => void }) {
  const router = useRouter();
  const { can } = useAuth();
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const { application, seats, subscription, renewal } = home;
  const hasSeat = seats.length > 0;
  const waiting = application?.status === 'pending';
  const canApply = !hasSeat && !waiting && can('riders.profile.manage');

  async function act(action: () => Promise<unknown>) {
    setBusy(true);
    setError(null);

    try {
      await action();
      reload();
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Something went wrong. Try again.');
    } finally {
      setBusy(false);
    }
  }

  return (
    <>
      <Notice text={error} />

      {home.deletion_requested ? (
        <Card title="You asked us to delete your data">
          <ThemedText type="small" themeColor="textSecondary">
            An operator will confirm it. Until then nothing has been deleted. Changed your mind?
          </ThemedText>
          <Button label="Keep my account" variant="plain" loading={busy} onPress={() => act(riderApi.cancelDeletion)} />
        </Card>
      ) : null}

      {waiting && application ? (
        <Card title="We're looking at your application">
          <ThemedText type="small" themeColor="textSecondary">
            {application.route.name}, from {application.stop.name}
            {application.morning_time ? `, ${clock(application.morning_time)}` : ''}
            {application.evening_time ? `, back at ${clock(application.evening_time)}` : ''}. We'll tell you as soon as a seat is
            yours.
          </ThemedText>
          <Button label="Withdraw it" variant="plain" loading={busy} onPress={() => act(riderApi.withdraw)} />
        </Card>
      ) : null}

      {canApply && application?.status === 'rejected' ? (
        <Card title="We couldn't give you a seat this time">
          <ThemedText type="small" themeColor="textSecondary">
            {application.rejection_reason ?? 'We could not serve that request.'}
            {application.rejection_note ? ` ${application.rejection_note}` : ''} You can apply again for a different stop or time.
          </ThemedText>
        </Card>
      ) : null}

      {canApply ? (
        <Card title={application ? 'Apply again' : 'Get a seat that waits for you'}>
          <ThemedText type="small" themeColor="textSecondary">
            Choose your route, your stop and the time you leave. You keep paying your fare to the mate as you do today.
          </ThemedText>
          <Button label="Apply for a seat" onPress={() => router.push('/apply')} />
        </Card>
      ) : null}

      {seats.map((seat) => (
        <SeatCard key={seat.id} seat={seat} />
      ))}

      {hasSeat && !subscription ? (
        <Card title="Your seat is reserved">
          <ThemedText type="small" themeColor="textSecondary">
            Choose a plan and pay to make it yours. We hold it for a few days.
          </ThemedText>
          {can('subscriptions.buy') ? <Button label="Choose a plan" onPress={() => router.push('/plans')} /> : null}
        </Card>
      ) : null}

      {subscription ? (
        <Card title={subscription.has_started ? `${subscription.days_left} days left` : `Starts ${longDate(subscription.starts_on)}`}>
          <ThemedText type="small" themeColor="textSecondary">
            {subscription.plan} plan, {subscription.price_label}. Ends {longDate(subscription.ends_on)}.
            {subscription.credited_days > 0
              ? ` Includes ${subscription.credited_days} ${subscription.credited_days === 1 ? 'day' : 'days'} added back.`
              : ''}
            {renewal ? ` Renewed until ${longDate(renewal.ends_on)}.` : ''}
          </ThemedText>
          <View style={{ gap: Spacing.two }}>
            {can('subscriptions.absence.give') && home.ride_dates.length > 0 ? (
              <Button label="I won't ride on a day" variant="plain" onPress={() => router.push('/not-riding')} />
            ) : null}
            {can('subscriptions.renew') && !renewal ? (
              <Button label="Renew" variant="plain" onPress={() => router.push('/plans')} />
            ) : null}
          </View>
        </Card>
      ) : null}

      {home.notices.length > 0 ? (
        <Card title="Days you're not riding">
          {home.notices.map((notice) => (
            <ThemedText key={notice.date} type="small" themeColor="textSecondary">
              {shortDate(notice.date)}: {notice.credited ? 'added back to your subscription' : 'not added back'}
            </ThemedText>
          ))}
        </Card>
      ) : null}
    </>
  );
}

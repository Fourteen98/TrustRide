import { useRouter } from 'expo-router';
import { View } from 'react-native';

import { useAuth } from '@/auth/auth-context';
import { Button } from '@/components/button';
import { Card } from '@/components/card';
import { HomeHeader } from '@/components/home-header';
import { Loadable } from '@/components/loadable';
import { MutedText } from '@/components/muted-text';
import { Notice } from '@/components/notice';
import { Screen } from '@/components/screen';
import { Spacing } from '@/constants/theme';
import { useAction } from '@/hooks/use-action';
import { useLoader } from '@/hooks/use-loader';
import { clock, longDate, shortDate } from '@/lib/dates';
import { TodaysTrips } from '@/rider/trip-card';
import { riderApi, type RiderHome as HomeData } from '@/rider/api';
import { SeatCard } from '@/rider/seat-card';

/**
 * The rider's home. One call to the server, and what comes back decides what she sees:
 * nothing yet, an application waiting, a seat waiting for payment, or a seat with a running subscription.
 */
export function RiderHome() {
  const home = useLoader(riderApi.home);

  return (
    <Screen refreshing={home.refreshing} onRefresh={home.reload}>
      <HomeHeader />
      <Loadable state={home}>{(data) => <Content home={data} reload={home.reload} />}</Loadable>
    </Screen>
  );
}

function Content({ home, reload }: { home: HomeData; reload: () => void }) {
  const router = useRouter();
  const { can } = useAuth();
  const action = useAction();

  const { application, seats, subscription, renewal, purchase } = home;
  const hasSeat = seats.length > 0;
  const waiting = application?.status === 'pending';
  const canApply = !hasSeat && !waiting && can('riders.profile.manage');

  return (
    <>
      <Notice text={action.error} />

      {home.deletion_requested ? (
        <Card title="You asked us to delete your data">
          <MutedText>An operator will confirm it. Until then nothing has been deleted. Changed your mind?</MutedText>
          <Button
            label="Keep my account"
            variant="plain"
            loading={action.busy}
            onPress={() => action.run(riderApi.cancelDeletion, { then: reload })}
          />
        </Card>
      ) : null}

      {waiting && application ? (
        <Card title="We're looking at your application">
          <MutedText>
            {application.route.name}, from {application.stop.name}
            {application.morning_time ? `, ${clock(application.morning_time)}` : ''}
            {application.evening_time ? `, back at ${clock(application.evening_time)}` : ''}. We'll tell you as soon as a seat is
            yours.
          </MutedText>
          <Button label="Withdraw it" variant="plain" loading={action.busy} onPress={() => action.run(riderApi.withdraw, { then: reload })} />
        </Card>
      ) : null}

      {canApply && application?.status === 'rejected' ? (
        <Card title="We couldn't give you a seat this time">
          <MutedText>
            {application.rejection_reason ?? 'We could not serve that request.'}
            {application.rejection_note ? ` ${application.rejection_note}` : ''} You can apply again for a different stop or time.
          </MutedText>
        </Card>
      ) : null}

      {canApply ? (
        <Card title={application ? 'Apply again' : 'Get a seat that waits for you'}>
          <MutedText>Choose your route, your stop and the time you leave. You keep paying your fare to the mate as you do today.</MutedText>
          <Button label="Apply for a seat" onPress={() => router.push('/apply')} />
        </Card>
      ) : null}

      {can('trips.follow') && subscription ? <TodaysTrips /> : null}

      {seats.map((seat) => (
        <SeatCard key={seat.id} seat={seat} />
      ))}

      {purchase && purchase.status !== 'failed' ? (
        <Card title={purchase.status === 'pending' ? 'Payment waiting' : 'We got your payment'}>
          <MutedText>{purchase.message}</MutedText>
          <Button label="See payment" variant="plain" onPress={() => router.push('/plans')} />
        </Card>
      ) : null}

      {hasSeat && !subscription && !purchase ? (
        <Card title="Your seat is reserved">
          <MutedText>Choose a plan and pay to make it yours. We hold it for a few days.</MutedText>
          {can('subscriptions.buy') ? <Button label="Choose a plan" onPress={() => router.push('/plans')} /> : null}
        </Card>
      ) : null}

      {hasSeat && !subscription && purchase?.status === 'failed' ? (
        <Card title="Your seat is reserved">
          <MutedText>Your last payment did not go through. Pay to make the seat yours. We hold it for a few days.</MutedText>
          {can('subscriptions.buy') ? <Button label="Try again" onPress={() => router.push('/plans')} /> : null}
        </Card>
      ) : null}

      {subscription ? (
        <Card title={subscription.has_started ? `${subscription.days_left} days left` : `Starts ${longDate(subscription.starts_on)}`}>
          <MutedText>
            {subscription.plan} plan, {subscription.price_label}. Ends {longDate(subscription.ends_on)}.
            {subscription.credited_days > 0
              ? ` Includes ${subscription.credited_days} ${subscription.credited_days === 1 ? 'day' : 'days'} added back.`
              : ''}
            {renewal ? ` Renewed until ${longDate(renewal.ends_on)}.` : ''}
          </MutedText>
          <View style={{ gap: Spacing.two }}>
            {can('subscriptions.absence.give') && home.ride_dates.length > 0 ? (
              <Button label="I won't ride on a day" variant="plain" onPress={() => router.push('/not-riding')} />
            ) : null}
            {can('subscriptions.renew') && !renewal ? <Button label="Renew" variant="plain" onPress={() => router.push('/plans')} /> : null}
          </View>
        </Card>
      ) : null}

      {home.notices.length > 0 ? (
        <Card title="Days you're not riding">
          {home.notices.map((notice) => (
            <MutedText key={notice.date}>
              {shortDate(notice.date)}: {notice.credited ? 'added back to your subscription' : 'not added back'}
            </MutedText>
          ))}
        </Card>
      ) : null}
    </>
  );
}

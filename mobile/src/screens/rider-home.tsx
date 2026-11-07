import { useRouter } from 'expo-router';
import { useRef, useState } from 'react';
import { Platform, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { useAuth } from '@/auth/auth-context';
import { Button } from '@/components/button';
import { ActionMenu } from '@/components/action-menu';
import { Card } from '@/components/card';
import { HomeHeader, RoleSwitcher } from '@/components/home-header';
import { Loadable } from '@/components/loadable';
import { MapButton, MapTopBar, SheetStatus } from '@/components/map-controls';
import { MapSheet } from '@/components/map-sheet';
import { MutedText } from '@/components/muted-text';
import { Notice } from '@/components/notice';
import { Screen } from '@/components/screen';
import { TripMap, type TripMapHandle } from '@/components/trip-map';
import { Spacing } from '@/constants/theme';
import { useAction } from '@/hooks/use-action';
import { useLoader } from '@/hooks/use-loader';
import { clock, longDate, shortDate } from '@/lib/dates';
import { AutoRenewalLine } from '@/rider/auto-renewal';
import { headline, TodaysTrips } from '@/rider/trip-card';
import { riderApi, type RiderHome as HomeData, type RiderTrip, type RouteMap } from '@/rider/api';
import { SeatCard } from '@/rider/seat-card';
import { useRideActions } from '@/rider/ride-actions';
import { useRiderMap } from '@/rider/use-rider-map';

/** The line at the top of the sheet: the thing that matters most right now. */
function statusLine(home: HomeData | null, live: RiderTrip | null, next: RiderTrip | null, route: RouteMap | null): string {
  if (live) {
    return headline(live);
  }

  if (next) {
    return `Van due at ${next.my_stop}, ${clock(next.my_time)}`;
  }

  if (route) {
    return `Your stop: ${route.my_stop}`;
  }

  if (home?.application?.status === 'pending') {
    return 'Your application is with us';
  }

  return 'Get a seat that waits for you';
}

/**
 * The rider's home. The map fills the screen: her blue dot, her route with her stop ringed in gold, and her van
 * live while it runs. Everything else sits in a sheet that rests low and pulls up. One call to the server for the
 * sheet, and what comes back decides what she sees: nothing yet, an application waiting, a seat waiting for
 * payment, or a seat with a running subscription.
 *
 * The web build has no native map, so there it is the sheet's content on a plain screen.
 */
export function RiderHome() {
  const { can } = useAuth();
  const home = useLoader(riderApi.home);
  const rider = useRiderMap({ followTrips: can('trips.follow') });
  const mapRef = useRef<TripMapHandle>(null);
  const insets = useSafeAreaInsets();

  // Today's ride whose menu is open, by its place in today's list. The floating "Ride" button opens the one on the road, or the next.
  const [menuFor, setMenuFor] = useState<number | null>(null);
  const current = rider.live ?? rider.next ?? rider.today.find((trip) => trip.status !== 'ended' || trip.alighted_at === null) ?? null;
  const menuTrip = menuFor !== null ? (rider.today[menuFor] ?? null) : null;

  const refresh = () => {
    void home.reload();
    void rider.reload();
  };
  // The seat behind the trip whose menu is open, when its van holds seats for people who pay the fare.
  const fareSeat = home.data?.seats.find((seat) => seat.run_id === menuTrip?.run_id && seat.extra_seats === 'fare') ?? null;
  const actions = useRideActions(menuTrip, menuFor ?? 0, () => void rider.reload(), fareSeat?.id ?? null);
  const content = (
    <>
      <Notice text={actions.error} />
      <Loadable state={home}>
        {(data) => (
          <Content
            home={data}
            reload={refresh}
            trips={rider.today}
            showMap={Platform.OS === 'web'}
            onOptions={Platform.OS === 'web' ? undefined : setMenuFor}
          />
        )}
      </Loadable>
    </>
  );

  if (Platform.OS === 'web') {
    return (
      <Screen refreshing={home.refreshing} onRefresh={refresh}>
        <HomeHeader />
        {content}
      </Screen>
    );
  }

  const { mode, ...map } = rider.map;

  return (
    <MapSheet
      map={<TripMap key={mode} ref={mapRef} {...map} inset={{ top: insets.top + 60 }} />}
      top={<MapTopBar />}
      head={<SheetStatus text={statusLine(home.data, rider.live, rider.next, rider.route)} live={rider.live !== null} />}
      float={
        <>
          {current ? (
            <MapButton label="Ride options" text="Ride" tone="strong" onPress={() => setMenuFor(rider.today.indexOf(current))} />
          ) : null}
          {mode !== 'me' ? <MapButton label="Show my route" text="Route" onPress={() => mapRef.current?.showRoute()} /> : null}
          <MapButton label="Show where I am" text="Me" onPress={() => mapRef.current?.showMe()} />
        </>
      }
      refreshing={home.refreshing}
      onRefresh={refresh}>
      <RoleSwitcher />
      {content}
      <ActionMenu
        title={menuTrip ? `${menuTrip.route}, car ${menuTrip.car_number}` : 'Your ride'}
        items={actions.items}
        open={menuTrip !== null && actions.items.length > 0}
        onClose={() => setMenuFor(null)}
      />
    </MapSheet>
  );
}

type ContentProps = { home: HomeData; reload: () => void; trips: RiderTrip[]; showMap: boolean; onOptions?: (index: number) => void };

function Content({ home, reload, trips, showMap, onOptions }: ContentProps) {
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
            {application.evening_time ? `, back at ${clock(application.evening_time)}` : ''}. We'll tell you as soon as a seat is yours.
          </MutedText>
          <Button
            label="Withdraw it"
            variant="plain"
            loading={action.busy}
            onPress={() => action.run(riderApi.withdraw, { then: reload })}
          />
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

      {can('trips.follow') && subscription ? <TodaysTrips trips={trips} showMap={showMap} onOptions={onOptions} /> : null}

      {seats.map((seat) => (
        <SeatCard
          key={seat.id}
          seat={seat}
          daysLeft={seat.status === 'active' ? subscription?.days_left : null}
          onChangeStop={() => router.push({ pathname: '/destination', params: { seat_id: String(seat.id) } })}
        />
      ))}

      {seats
        .filter((seat) => seat.status === 'active' && seat.extra_seats === 'fare' && can('subscriptions.guests.reserve'))
        .map((seat) => {
          const days = home.guests.filter((held) => held.run_id === seat.run_id).length;

          return (
            <Button
              key={`guests-${seat.id}`}
              label={`People with me${days > 0 ? ` · ${days} ${days === 1 ? 'day' : 'days'} held` : ''}`}
              variant="plain"
              onPress={() => router.push({ pathname: '/guests', params: { seat_id: String(seat.id) } })}
            />
          );
        })}

      {hasSeat && !showMap ? <Button label="All the stops on my route" variant="plain" onPress={() => router.push('/map')} /> : null}

      {home.auto_renewal?.status === 'pending' ? (
        <Card title="Automatic payment waiting">
          <MutedText>{home.auto_renewal.message}</MutedText>
          <Button label="Continue" variant="plain" onPress={() => router.push('/plans')} />
        </Card>
      ) : null}

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
            {home.auto_renewal?.status === 'active' ? (
              <AutoRenewalLine renewal={home.auto_renewal} onStopped={reload} />
            ) : can('subscriptions.renew') && !renewal ? (
              <Button label="Renew" variant="plain" onPress={() => router.push('/plans')} />
            ) : null}
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

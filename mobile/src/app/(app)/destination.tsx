import { useLocalSearchParams, useRouter } from 'expo-router';
import { useState } from 'react';

import { Button } from '@/components/button';
import { Choice } from '@/components/choice';
import { ChoiceGroup } from '@/components/choice-group';
import { Loadable } from '@/components/loadable';
import { MutedText } from '@/components/muted-text';
import { Notice } from '@/components/notice';
import { Screen } from '@/components/screen';
import { useAction } from '@/hooks/use-action';
import { useLoader } from '@/hooks/use-loader';
import { riderApi, type RouteMap, type Seat } from '@/rider/api';

/**
 * Where she goes. The stop she gets off at going out, and gets on at coming back, so the mate knows when she gets
 * down and she is told when her stop is next. Only stops after hers can be chosen. The server sets it on all her
 * seats on the route.
 */
export default function DestinationScreen() {
  const { seat_id: seatId } = useLocalSearchParams<{ seat_id?: string }>();
  const data = useLoader(async () => {
    const [home, routes] = await Promise.all([riderApi.home(), riderApi.routeMap()]);

    return { seat: home.seats.find((seat) => seat.id === Number(seatId)) ?? null, routes };
  });

  return (
    <Screen edges={['bottom']}>
      <Loadable state={data}>
        {({ seat, routes }) =>
          seat ? (
            <Form seat={seat} route={routes.find((route) => route.seat_id === seat.id) ?? null} />
          ) : (
            <Notice text="That seat is not yours any more." />
          )
        }
      </Loadable>
    </Screen>
  );
}

function Form({ seat, route }: { seat: Seat; route: RouteMap | null }) {
  const router = useRouter();
  const save = useAction();
  const [chosen, setChosen] = useState(seat.destination_stop_id);

  if (!route) {
    return <Notice text="Your route could not be loaded. Go back and try again." />;
  }

  // In the order the van goes out, whichever way this seat's run travels. Only the stops after hers.
  const outward = route.direction === 'outbound' ? route.stops : [...route.stops].reverse();
  const home = outward.findIndex((stop) => stop.id === seat.home_stop_id);
  const choices = outward.slice(home + 1);

  return (
    <>
      <MutedText>
        Where do you go on {seat.route}? Going out you get off there, and coming back you get on there. The mate sees it, and the app tells
        you when your stop is next.
      </MutedText>

      <ChoiceGroup title="I go to">
        {choices.map((stop, index) => (
          <Choice
            key={stop.id}
            label={stop.name}
            detail={[stop.landmark, index === choices.length - 1 ? 'End of the route' : null].filter(Boolean).join(' · ') || null}
            selected={chosen === stop.id}
            onPress={() => {
              save.clear();
              setChosen(stop.id);
            }}
          />
        ))}
      </ChoiceGroup>

      <Notice text={save.error} />
      <Button
        label="Save"
        loading={save.busy}
        disabled={chosen === seat.destination_stop_id}
        onPress={() => void save.run(() => riderApi.setDestination(seat.id, chosen), { then: () => router.back() })}
      />
    </>
  );
}

import { useRouter } from 'expo-router';
import { useState } from 'react';

import { useAuth } from '@/auth/auth-context';
import { Button } from '@/components/button';
import { Choice } from '@/components/choice';
import { ChoiceGroup } from '@/components/choice-group';
import { Loadable } from '@/components/loadable';
import { Notice } from '@/components/notice';
import { Screen } from '@/components/screen';
import { TextField } from '@/components/text-field';
import { ThemedText } from '@/components/themed-text';
import { useAction } from '@/hooks/use-action';
import { useLoader } from '@/hooks/use-loader';
import { clock } from '@/lib/dates';
import { riderApi, type RouteChoice } from '@/rider/api';

/** Task R2. She picks a route, a stop and a time. Only times a van actually runs are offered. */
export default function ApplyScreen() {
  const routes = useLoader(riderApi.routes);

  return (
    <Screen edges={['bottom']}>
      <Loadable state={routes}>
        {(list) =>
          list.length === 0 ? <Notice tone="info" text="We're not running any routes yet. Check back soon." /> : <Form routes={list} />
        }
      </Loadable>
    </Screen>
  );
}

function Form({ routes }: { routes: RouteChoice[] }) {
  const router = useRouter();
  const { account, refresh } = useAuth();
  const send = useAction();

  const [name, setName] = useState(account?.name ?? '');
  const [routeId, setRouteId] = useState<number | null>(null);
  const [stopId, setStopId] = useState<number | null>(null);
  const [morning, setMorning] = useState<string | null>(null);
  const [evening, setEvening] = useState<string | null>(null);

  // With one route on offer there is nothing to choose, so it is picked for her.
  const route = routes.find((r) => r.id === routeId) ?? (routes.length === 1 ? routes[0] : null);

  /** Any new choice clears the last complaint, so an old error never sits under a corrected form. */
  function pick(change: () => void) {
    send.clear();
    change();
  }

  function chooseRoute(id: number) {
    pick(() => {
      setRouteId(id);
      setStopId(null);
      setMorning(null);
      setEvening(null);
    });
  }

  function submit() {
    if (name.trim().length < 2) {
      return send.fail('Enter your name.');
    }

    if (!route || !stopId) {
      return send.fail('Choose your route and your stop.');
    }

    if (!morning && !evening) {
      return send.fail('Choose the time you want.');
    }

    return send.run(
      async () => {
        await riderApi.apply({ name: name.trim(), route_id: route.id, stop_id: stopId, morning_time: morning, evening_time: evening });
        await refresh().catch(() => undefined); // her name on the account may have just been set
      },
      { then: router.back },
    );
  }

  return (
    <>
      <TextField label="Your name" value={name} onChangeText={setName} autoComplete="name" textContentType="name" />

      <ChoiceGroup title="Route">
        {routes.length > 1 ? (
          routes.map((r) => (
            <Choice key={r.id} label={r.name} detail={`To ${r.destination}`} selected={route?.id === r.id} onPress={() => chooseRoute(r.id)} />
          ))
        ) : (
          <ThemedText>{routes[0].name}</ThemedText>
        )}
      </ChoiceGroup>

      {route ? (
        <>
          <ChoiceGroup title="Where do you get on?">
            {route.stops.map((stop) => (
              <Choice
                key={stop.id}
                label={stop.name}
                detail={stop.landmark}
                selected={stopId === stop.id}
                onPress={() => pick(() => setStopId(stop.id))}
              />
            ))}
          </ChoiceGroup>

          <TimeChoices
            title="Morning, to your destination"
            hint="This is when the van leaves the first stop."
            slots={route.morning_times}
            value={morning}
            onChange={(time) => pick(() => setMorning(time))}
          />
          <TimeChoices
            title="Evening, back home"
            hint="Leave this empty if you only want the morning."
            slots={route.evening_times}
            value={evening}
            onChange={(time) => pick(() => setEvening(time))}
          />
        </>
      ) : null}

      <Notice text={send.error} />
      <Button label="Send my application" onPress={submit} loading={send.busy} />
    </>
  );
}

type Slot = RouteChoice['morning_times'][number];

/** The times a van runs in one direction. Tapping the chosen time again clears it. */
function TimeChoices(props: { title: string; hint: string; slots: Slot[]; value: string | null; onChange: (time: string | null) => void }) {
  if (props.slots.length === 0) {
    return null;
  }

  return (
    <ChoiceGroup title={props.title} hint={props.hint} layout="chips">
      {props.slots.map((slot) => (
        <Choice
          key={slot.time}
          shape="chip"
          label={clock(slot.time)}
          detail={slot.seats_free ? null : 'Full, join the list'}
          selected={props.value === slot.time}
          onPress={() => props.onChange(props.value === slot.time ? null : slot.time)}
        />
      ))}
    </ChoiceGroup>
  );
}

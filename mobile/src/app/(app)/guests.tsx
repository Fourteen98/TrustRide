import { useLocalSearchParams, useRouter } from 'expo-router';
import { useState } from 'react';

import { Button } from '@/components/button';
import { Card } from '@/components/card';
import { Choice } from '@/components/choice';
import { ChoiceGroup } from '@/components/choice-group';
import { Loadable } from '@/components/loadable';
import { MutedText } from '@/components/muted-text';
import { Notice } from '@/components/notice';
import { Screen } from '@/components/screen';
import { Stepper } from '@/components/stepper';
import { useAction } from '@/hooks/use-action';
import { useLoader } from '@/hooks/use-loader';
import { clock, shortDate, today } from '@/lib/dates';
import { riderApi, type RiderHome } from '@/rider/api';

/**
 * People with me. On vans where they pay the fare, she holds seats on a day for people travelling with her.
 * The mate sees them at her stop. Each pays the usual fare. The server decides whether there is room.
 */
export default function GuestsScreen() {
  const { seat_id: seatId, date: asked } = useLocalSearchParams<{ seat_id?: string; date?: string }>();
  const home = useLoader(riderApi.home);

  return (
    <Screen edges={['bottom']}>
      <Loadable state={home}>{(data) => <Form home={data} seatId={Number(seatId)} asked={asked ?? null} reload={home.reload} />}</Loadable>
    </Screen>
  );
}

function Form({ home, seatId, asked, reload }: { home: RiderHome; seatId: number; asked: string | null; reload: () => Promise<void> }) {
  const router = useRouter();
  const save = useAction();
  const seat =
    home.seats.find((candidate) => candidate.id === seatId) ?? home.seats.find((candidate) => candidate.extra_seats === 'fare') ?? null;
  const days = [...new Set([...(asked ? [asked] : []), ...home.ride_dates])].filter((day) => day >= today()).sort();
  const [date, setDate] = useState<string | null>(asked ?? days[0] ?? null);
  const heldFor = (day: string | null) => home.guests.find((held) => held.run_id === seat?.run_id && held.date === day)?.seats ?? 0;
  const [seats, setSeats] = useState(Math.max(1, heldFor(date)));

  if (!seat || seat.extra_seats !== 'fare') {
    return <Notice tone="info" text="Your van does not hold seats for people with you." />;
  }

  const held = heldFor(date);

  return (
    <>
      <MutedText>
        Hold seats on your {clock(seat.time)} van to {seat.heading_to}, car {seat.car_number}, for people travelling with you. They get on
        with you at {seat.stop}, {clock(seat.time)}, and each pays the usual fare to the mate.
      </MutedText>

      {days.length === 0 ? (
        <Notice tone="info" text="There are no days to choose in the next two weeks." />
      ) : (
        <ChoiceGroup title="Day" layout="chips">
          {days.map((day) => (
            <Choice
              key={day}
              shape="chip"
              label={`${shortDate(day)}${heldFor(day) > 0 ? ` · +${heldFor(day)}` : ''}`}
              selected={date === day}
              onPress={() => {
                save.clear();
                setDate(day);
                setSeats(Math.max(1, heldFor(day)));
              }}
            />
          ))}
        </ChoiceGroup>
      )}

      {date ? (
        <>
          <Stepper label="People with you" hint="Not counting you." value={seats} min={1} max={seat.max_party - 1} onChange={setSeats} />
          {held > 0 ? (
            <Card title={`${held} ${held === 1 ? 'seat' : 'seats'} held on ${shortDate(date)}`}>
              <MutedText>Change the number and save, or let them go if plans change.</MutedText>
            </Card>
          ) : null}
          <Notice text={save.error} />
          <Button
            label={held > 0 ? 'Save' : `Hold ${seats} ${seats === 1 ? 'seat' : 'seats'}`}
            loading={save.busy}
            onPress={() =>
              void save.run(() => riderApi.holdGuests(seat.id, date, seats), { then: () => void reload().then(() => router.back()) })
            }
          />
          {held > 0 ? (
            <Button
              label="Let them go"
              variant="plain"
              loading={save.busy}
              onPress={() => void save.run(() => riderApi.holdGuests(seat.id, date, 0), { then: () => void reload() })}
            />
          ) : null}
        </>
      ) : null}
    </>
  );
}

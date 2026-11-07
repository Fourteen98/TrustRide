import { useState } from 'react';

import { Button } from '@/components/button';
import { Choice } from '@/components/choice';
import { ChoiceGroup } from '@/components/choice-group';
import { MutedText } from '@/components/muted-text';
import { Notice } from '@/components/notice';
import { Stepper } from '@/components/stepper';
import { TextField } from '@/components/text-field';
import { useAction } from '@/hooks/use-action';
import { beforeTheEnd } from '@/lib/dates';
import { cedis } from '@/lib/money';
import { looksLikeGhanaPhone } from '@/lib/phone';

import { riderApi, type Network, type Plan } from './api';

type Props = {
  plans: Plan[];
  networks: Network[];
  /** The number she logs in with. The prompt goes there unless she types another. */
  defaultPhone: string;
  /** How many days before the end an automatic payment is taken. Set by Aveo, so it is said before she agrees. */
  chargeDaysBefore: number;
  /** Called once the prompt, or the direct debit request, has gone to her phone. */
  onStarted: (how: 'once' | 'auto') => void;
  /** On vans that add people to her plan: the most it may be for, and what her seat holds now. Null elsewhere. */
  party?: { max: number; current: number } | null;
};

/** Pick a plan, pick a network, confirm the wallet number, pay. */
export function PayForm({ plans, networks, defaultPhone, chargeDaysBefore, onStarted, party = null }: Props) {
  const [planId, setPlanId] = useState<number | null>(plans.length === 1 ? plans[0].id : null);
  const [network, setNetwork] = useState<Network['key'] | null>(null);
  const [phone, setPhone] = useState(defaultPhone);
  const [how, setHow] = useState<'once' | 'auto'>('once');
  const [seats, setSeats] = useState(party?.current ?? 1);
  const pay = useAction();

  const plan = plans.find((candidate) => candidate.id === planId);
  // The server sets the amount: the plan's price for each person. This only shows it before she pays.
  const total = plan ? (seats > 1 ? cedis(plan.price * seats) : plan.price_label) : null;
  const canAuto = networks.find((candidate) => candidate.key === network)?.automatic ?? false;
  const auto = how === 'auto' && canAuto;

  const submit = () => {
    if (!plan) {
      return pay.fail('Choose a plan.');
    }

    if (!network) {
      return pay.fail('Choose the network you pay with.');
    }

    if (!looksLikeGhanaPhone(phone)) {
      return pay.fail('Enter the mobile money number to pay from.');
    }

    const body = { plan_id: plan.id, network: network as never, phone, ...(party ? { seats } : {}) };

    void pay.run(async () => {
      await (auto ? riderApi.startAutoRenewal(body) : riderApi.pay(body));
      onStarted(auto ? 'auto' : 'once');
    });
  };

  return (
    <>
      <ChoiceGroup title="Plan">
        {plans.map((option) => (
          <Choice
            key={option.id}
            label={`${option.name} · ${seats > 1 ? `${cedis(option.price * seats)} for ${seats}` : option.price_label}`}
            detail={`${option.duration_days} days. ${option.trips_per_day === 1 ? 'One trip a day.' : 'There and back each day.'} If you don't ride: ${option.no_show_rule.toLowerCase()}.`}
            selected={option.id === planId}
            onPress={() => setPlanId(option.id)}
          />
        ))}
      </ChoiceGroup>

      {party ? (
        <Stepper
          label="How many people, you included"
          hint="People travelling with you get a held seat every day of the plan, paid with it."
          value={seats}
          min={1}
          max={party.max}
          onChange={setSeats}
        />
      ) : null}

      <ChoiceGroup title="Pay with" layout="chips">
        {networks.map((option) => (
          <Choice
            key={option.key}
            shape="chip"
            label={option.label}
            selected={option.key === network}
            onPress={() => setNetwork(option.key)}
          />
        ))}
      </ChoiceGroup>

      <TextField label="Mobile money number" value={phone} onChangeText={setPhone} keyboardType="phone-pad" autoComplete="tel" />

      {network ? (
        <ChoiceGroup title="How to pay">
          <Choice
            label="Just this time"
            detail="You get a prompt and approve it. When the plan ends you pay again."
            selected={!auto}
            onPress={() => setHow('once')}
          />
          {canAuto ? (
            <Choice
              label="Automatically, each time it ends"
              detail={`Approve once. We take ${total ?? 'the price'} from this number ${beforeTheEnd(chargeDaysBefore)}, and tell you first. Stop it any time.`}
              selected={auto}
              onPress={() => setHow('auto')}
            />
          ) : (
            <MutedText>Automatic payment is not available on this network yet.</MutedText>
          )}
        </ChoiceGroup>
      ) : null}

      <MutedText>
        {auto
          ? 'You will get a direct debit request on that phone. Approve it once with your mobile money PIN. '
          : 'You will get a prompt on that phone. Approve it with your mobile money PIN. '}
        {seats > 1 ? `This pays for ${seats} seats. ` : 'This pays for your seat only. '}Your fare still goes to the mate.
      </MutedText>

      <Notice text={pay.error} />
      <Button
        label={auto ? `Set up automatic payment${total ? ` of ${total}` : ''}` : total ? `Pay ${total}` : 'Pay'}
        loading={pay.busy}
        onPress={submit}
      />
    </>
  );
}

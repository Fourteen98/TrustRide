import { useState } from 'react';

import { Button } from '@/components/button';
import { Choice } from '@/components/choice';
import { ChoiceGroup } from '@/components/choice-group';
import { MutedText } from '@/components/muted-text';
import { Notice } from '@/components/notice';
import { TextField } from '@/components/text-field';
import { useAction } from '@/hooks/use-action';
import { beforeTheEnd } from '@/lib/dates';
import { looksLikeGhanaPhone } from '@/lib/phone';

import { riderApi, type Network, type Plan } from './api';

type Props = {
  plans: Plan[];
  networks: Network[];
  /** The number she logs in with. The prompt goes there unless she types another. */
  defaultPhone: string;
  /** How many days before the end an automatic payment is taken. Set by TrustRide, so it is said before she agrees. */
  chargeDaysBefore: number;
  /** Called once the prompt, or the direct debit request, has gone to her phone. */
  onStarted: (how: 'once' | 'auto') => void;
};

/** Pick a plan, pick a network, confirm the wallet number, pay. */
export function PayForm({ plans, networks, defaultPhone, chargeDaysBefore, onStarted }: Props) {
  const [planId, setPlanId] = useState<number | null>(plans.length === 1 ? plans[0].id : null);
  const [network, setNetwork] = useState<Network['key'] | null>(null);
  const [phone, setPhone] = useState(defaultPhone);
  const [how, setHow] = useState<'once' | 'auto'>('once');
  const pay = useAction();

  const plan = plans.find((candidate) => candidate.id === planId);
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

    const body = { plan_id: plan.id, network: network as never, phone };

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
            label={`${option.name} · ${option.price_label}`}
            detail={`${option.duration_days} days. ${option.trips_per_day === 1 ? 'One trip a day.' : 'There and back each day.'} If you don't ride: ${option.no_show_rule.toLowerCase()}.`}
            selected={option.id === planId}
            onPress={() => setPlanId(option.id)}
          />
        ))}
      </ChoiceGroup>

      <ChoiceGroup title="Pay with" layout="chips">
        {networks.map((option) => (
          <Choice key={option.key} shape="chip" label={option.label} selected={option.key === network} onPress={() => setNetwork(option.key)} />
        ))}
      </ChoiceGroup>

      <TextField label="Mobile money number" value={phone} onChangeText={setPhone} keyboardType="phone-pad" autoComplete="tel" />

      {network ? (
        <ChoiceGroup title="How to pay">
          <Choice label="Just this time" detail="You get a prompt and approve it. When the plan ends you pay again." selected={!auto} onPress={() => setHow('once')} />
          {canAuto ? (
            <Choice
              label="Automatically, each time it ends"
              detail={`Approve once. We take ${plan?.price_label ?? 'the price'} from this number ${beforeTheEnd(chargeDaysBefore)}, and tell you first. Stop it any time.`}
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
        This pays for your seat only. Your fare still goes to the mate.
      </MutedText>

      <Notice text={pay.error} />
      <Button
        label={auto ? `Set up automatic payment${plan ? ` of ${plan.price_label}` : ''}` : plan ? `Pay ${plan.price_label}` : 'Pay'}
        loading={pay.busy}
        onPress={submit}
      />
    </>
  );
}

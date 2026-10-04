import { useState } from 'react';

import { Button } from '@/components/button';
import { Choice } from '@/components/choice';
import { ChoiceGroup } from '@/components/choice-group';
import { MutedText } from '@/components/muted-text';
import { Notice } from '@/components/notice';
import { TextField } from '@/components/text-field';
import { useAction } from '@/hooks/use-action';
import { looksLikeGhanaPhone } from '@/lib/phone';

import { riderApi, type Network, type Plan, type Purchase } from './api';

type Props = {
  plans: Plan[];
  networks: Network[];
  /** The number she logs in with. The prompt goes there unless she types another. */
  defaultPhone: string;
  onStarted: (purchase: Purchase) => void;
};

/** Pick a plan, pick a network, confirm the wallet number, pay. */
export function PayForm({ plans, networks, defaultPhone, onStarted }: Props) {
  const [planId, setPlanId] = useState<number | null>(plans.length === 1 ? plans[0].id : null);
  const [network, setNetwork] = useState<Network['key'] | null>(null);
  const [phone, setPhone] = useState(defaultPhone);
  const pay = useAction();

  const plan = plans.find((candidate) => candidate.id === planId);

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

    void pay.run(async () => onStarted(await riderApi.pay({ plan_id: plan.id, network: network as never, phone })));
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

      <MutedText>
        You will get a prompt on that phone. Approve it with your mobile money PIN. This pays for your seat only. Your fare still goes to
        the mate.
      </MutedText>

      <Notice text={pay.error} />
      <Button label={plan ? `Pay ${plan.price_label}` : 'Pay'} loading={pay.busy} onPress={submit} />
    </>
  );
}

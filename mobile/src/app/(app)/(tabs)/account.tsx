import { useState } from 'react';

import { useAuth } from '@/auth/auth-context';
import { Button } from '@/components/button';
import { Card } from '@/components/card';
import { Choice } from '@/components/choice';
import { ChoiceGroup } from '@/components/choice-group';
import { Detail } from '@/components/detail';
import { MutedText } from '@/components/muted-text';
import { Notice } from '@/components/notice';
import { Screen } from '@/components/screen';
import { useAction } from '@/hooks/use-action';
import { useLoader } from '@/hooks/use-loader';
import { riderApi } from '@/rider/api';

const channelLabels = { push: 'Notifications in the app', sms: 'SMS', whatsapp: 'WhatsApp' } as const;

export default function AccountScreen() {
  const { account, logout, can, preferChannel } = useAuth();
  const leave = useAction();
  const reach = useAction();

  return (
    <Screen edges={['left', 'right']}>
      <Detail label="Phone number">{account?.phone ?? ''}</Detail>
      <Detail label="Roles">{account?.roles.map((role) => role.label ?? role.name).join(', ') || 'None yet'}</Detail>

      {account && account.channels.length > 1 ? (
        <ChoiceGroup title="How we reach you" hint="If this way fails we still try the others. Login codes always come by SMS.">
          {account.channels.map((channel) => (
            <Choice
              key={channel}
              label={channelLabels[channel]}
              selected={account.preferred_channel === channel}
              onPress={() => reach.run(() => preferChannel(channel))}
            />
          ))}
        </ChoiceGroup>
      ) : null}
      <Notice text={reach.error} />

      <Button label="Log out" variant="plain" onPress={() => leave.run(logout)} loading={leave.busy} />

      {can('riders.data.delete') ? <DeleteMyData /> : null}
    </Screen>
  );
}

/** Task R6. Asking is two taps, on purpose. Nothing is deleted until an operator confirms. */
function DeleteMyData() {
  const home = useLoader(riderApi.home);
  const action = useAction();
  const [confirming, setConfirming] = useState(false);

  // Someone who has never applied has no rider data to delete.
  if (!home.data?.rider) {
    return null;
  }

  const done = () => {
    setConfirming(false);
    void home.reload();
  };

  if (home.data.deletion_requested) {
    return (
      <Card title="You asked us to delete your data">
        <MutedText>An operator will confirm it. Until then nothing has been deleted.</MutedText>
        <Notice text={action.error} />
        <Button
          label="Keep my account"
          variant="plain"
          loading={action.busy}
          onPress={() => action.run(riderApi.cancelDeletion, { then: done })}
        />
      </Card>
    );
  }

  return (
    <Card title="Delete my data">
      <MutedText>
        This removes your applications, your seat, your subscription and your login. It ends any subscription you have paid for, and it
        can't be undone.
      </MutedText>
      <Notice text={action.error} />
      {confirming ? (
        <>
          <Button
            label="Yes, ask Aveo to delete it"
            loading={action.busy}
            onPress={() => action.run(riderApi.requestDeletion, { then: done })}
          />
          <Button label="No, keep it" variant="plain" onPress={() => setConfirming(false)} />
        </>
      ) : (
        <Button label="Delete my data" variant="plain" onPress={() => setConfirming(true)} />
      )}
    </Card>
  );
}

import { useState } from 'react';
import { View } from 'react-native';

import { useAuth } from '@/auth/auth-context';
import { Button } from '@/components/button';
import { Card } from '@/components/card';
import { Notice } from '@/components/notice';
import { Screen } from '@/components/screen';
import { ThemedText } from '@/components/themed-text';
import { Spacing } from '@/constants/theme';
import { riderApi } from '@/rider/api';
import { useLoader } from '@/rider/use-loader';

export default function AccountScreen() {
  const { account, logout, can } = useAuth();
  const [leaving, setLeaving] = useState(false);

  async function leave() {
    setLeaving(true);
    await logout();
  }

  return (
    <Screen edges={['bottom']}>
      <View style={{ gap: Spacing.one }}>
        <ThemedText type="small" themeColor="textSecondary">
          Phone number
        </ThemedText>
        <ThemedText>{account?.phone}</ThemedText>
      </View>

      <View style={{ gap: Spacing.one }}>
        <ThemedText type="small" themeColor="textSecondary">
          Roles
        </ThemedText>
        <ThemedText>{account?.roles.map((role) => role.label ?? role.name).join(', ') || 'None yet'}</ThemedText>
      </View>

      <Button label="Log out" variant="plain" onPress={leave} loading={leaving} />

      {can('riders.data.delete') ? <DeleteMyData /> : null}
    </Screen>
  );
}

/** Task R6. Asking is two taps, on purpose. Nothing is deleted until an operator confirms. */
function DeleteMyData() {
  const { data: home, reload } = useLoader(riderApi.home);
  const [confirming, setConfirming] = useState(false);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  // Someone who has never applied has no rider data to delete.
  if (!home?.rider) {
    return null;
  }

  async function act(action: () => Promise<unknown>) {
    setBusy(true);
    setError(null);

    try {
      await action();
      setConfirming(false);
      reload();
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Something went wrong. Try again.');
    } finally {
      setBusy(false);
    }
  }

  if (home.deletion_requested) {
    return (
      <Card title="You asked us to delete your data">
        <ThemedText type="small" themeColor="textSecondary">
          An operator will confirm it. Until then nothing has been deleted.
        </ThemedText>
        <Notice text={error} />
        <Button label="Keep my account" variant="plain" loading={busy} onPress={() => act(riderApi.cancelDeletion)} />
      </Card>
    );
  }

  return (
    <Card title="Delete my data">
      <ThemedText type="small" themeColor="textSecondary">
        This removes your applications, your seat, your subscription and your login. It ends any subscription you have paid for, and it
        can't be undone.
      </ThemedText>
      <Notice text={error} />
      {confirming ? (
        <>
          <Button label="Yes, ask TrustRide to delete it" loading={busy} onPress={() => act(riderApi.requestDeletion)} />
          <Button label="No, keep it" variant="plain" onPress={() => setConfirming(false)} />
        </>
      ) : (
        <Button label="Delete my data" variant="plain" onPress={() => setConfirming(true)} />
      )}
    </Card>
  );
}

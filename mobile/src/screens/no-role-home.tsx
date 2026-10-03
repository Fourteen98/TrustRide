import { Card } from '@/components/card';

/** An account with no role that opens screens. Rare: it means staff removed every role. */
export function NoRoleHome() {
  return <Card title="Nothing to show yet">Your account has no role. Contact TrustRide support.</Card>;
}

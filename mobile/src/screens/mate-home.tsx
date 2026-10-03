import { useAuth } from '@/auth/auth-context';
import { Card } from '@/components/card';

/** The mate's home. Built in release 2. The mate marks pickups, because the driver is driving. */
export function MateHome() {
  const { can } = useAuth();

  return (
    <>
      {can('trips.riders.view') ? <Card title="Today's riders">Your riders by stop. Coming in task D3.</Card> : null}
      {can('trips.pickups.mark') ? (
        <Card title="Pickups">Mark each rider picked up or missed. Coming in task D3.</Card>
      ) : null}
    </>
  );
}

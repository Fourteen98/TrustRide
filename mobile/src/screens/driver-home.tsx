import { useAuth } from '@/auth/auth-context';
import { Card } from '@/components/card';

/** The driver's home. Built in release 2 (tasks D2 to D8). No location code ships before then. */
export function DriverHome() {
  const { can } = useAuth();

  return (
    <>
      {can('trips.riders.view') ? <Card title="Today's riders">Your riders by stop. Coming in task D3.</Card> : null}
      {can('trips.run') ? <Card title="Your run">Start and end the trip. Coming in task D2.</Card> : null}
      {can('drivers.commission.view') ? (
        <Card title="Commission">Your monthly commission and assessment. Coming in task D8.</Card>
      ) : null}
    </>
  );
}

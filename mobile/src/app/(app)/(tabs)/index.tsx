import { useAuth } from '@/auth/auth-context';
import { HomeHeader } from '@/components/home-header';
import { Screen } from '@/components/screen';
import { DriverHome } from '@/screens/driver-home';
import { MateHome } from '@/screens/mate-home';
import { NoRoleHome } from '@/screens/no-role-home';
import { RiderHome } from '@/screens/rider-home';

/**
 * Home. Which screens show depends on the account's active role.
 * The three screen sets are fixed in code. Roles made in the console switch them on.
 */
export default function Home() {
  const { activeRole } = useAuth();

  // The rider's and the crew's homes are maps with a sheet over them, so they bring their own frame.
  if (activeRole?.screens === 'rider') {
    return <RiderHome />;
  }

  if (activeRole?.screens === 'driver') {
    return <DriverHome />;
  }

  if (activeRole?.screens === 'mate') {
    return <MateHome />;
  }

  return (
    <Screen>
      <HomeHeader />
      <NoRoleHome />
    </Screen>
  );
}

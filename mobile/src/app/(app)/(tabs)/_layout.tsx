import { Tabs } from 'expo-router/tabs';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { useAuth } from '@/auth/auth-context';
import { FloatingTabBar, tabBarSpace } from '@/components/floating-tab-bar';
import { Icon } from '@/components/icon';
import { TabBarSpace } from '@/components/tab-bar-space';

/**
 * The tabs, in a bar that floats over the bottom of the screen: Home, Routes, Activity, Parcels, Earnings and Account.
 * Routes and Activity are the rider's, Earnings the driver's, Parcels a driver's who does Ox work.
 * Each shows only to someone with its permission.
 */
export default function TabsLayout() {
  const { can, account } = useAuth();
  const doesOx = can('deliveries.runs.drive') && (account?.work_types ?? []).includes('ox');
  const insets = useSafeAreaInsets();

  return (
    <TabBarSpace.Provider value={tabBarSpace(insets.bottom)}>
      <Tabs tabBar={(props) => <FloatingTabBar {...props} />} screenOptions={{ headerShadowVisible: false }}>
        <Tabs.Screen
          name="index"
          options={{
            title: 'Home',
            headerShown: false,
            tabBarIcon: ({ color, focused }) => <Icon name={focused ? 'house.fill' : 'house'} glyph="⌂" color={color} />,
          }}
        />
        <Tabs.Screen
          name="routes"
          options={{
            title: 'Routes',
            href: can('riders.profile.manage') ? undefined : null,
            headerShown: false,
            tabBarIcon: ({ color }) => <Icon name="magnifyingglass" glyph="⌕" color={color} />,
          }}
        />
        <Tabs.Screen
          name="activity"
          options={{
            title: 'Activity',
            href: can('trips.follow') ? undefined : null,
            tabBarIcon: ({ color, focused }) => (
              <Icon name={focused ? 'list.bullet.rectangle.fill' : 'list.bullet.rectangle'} glyph="≡" color={color} />
            ),
          }}
        />
        <Tabs.Screen
          name="parcels"
          options={{
            title: 'Parcels',
            href: doesOx ? undefined : null,
            tabBarIcon: ({ color, focused }) => <Icon name={focused ? 'shippingbox.fill' : 'shippingbox'} glyph="▣" color={color} />,
          }}
        />
        <Tabs.Screen
          name="earnings"
          options={{
            title: 'Earnings',
            href: can('drivers.commission.view') ? undefined : null,
            tabBarIcon: ({ color }) => <Icon name="banknote" glyph="₵" color={color} />,
          }}
        />
        <Tabs.Screen
          name="account"
          options={{
            title: 'Account',
            tabBarIcon: ({ color, focused }) => (
              <Icon name={focused ? 'person.crop.circle.fill' : 'person.crop.circle'} glyph="◯" color={color} />
            ),
          }}
        />
      </Tabs>
    </TabBarSpace.Provider>
  );
}

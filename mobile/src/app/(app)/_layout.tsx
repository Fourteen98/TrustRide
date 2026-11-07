import { Stack } from 'expo-router';

/** The tabs at the bottom, and the screens that open over them. */
export default function AppLayout() {
  return (
    <Stack screenOptions={{ headerBackTitle: 'Back' }}>
      <Stack.Screen name="(tabs)" options={{ headerShown: false }} />
      <Stack.Screen name="apply" options={{ title: 'Apply for a seat' }} />
      <Stack.Screen name="plans" options={{ title: 'Plans' }} />
      <Stack.Screen name="not-riding" options={{ title: 'Not riding' }} />
      <Stack.Screen name="guests" options={{ title: 'People with me' }} />
      <Stack.Screen name="destination" options={{ title: 'Where I go' }} />
      <Stack.Screen name="trip" options={{ title: 'Your van' }} />
      <Stack.Screen name="map" options={{ title: 'Your route' }} />
      <Stack.Screen name="help" options={{ title: 'Help' }} />
    </Stack>
  );
}

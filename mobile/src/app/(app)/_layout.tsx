import { Stack } from 'expo-router';

export default function AppLayout() {
  return (
    <Stack screenOptions={{ headerBackTitle: 'Back' }}>
      <Stack.Screen name="index" options={{ headerShown: false }} />
      <Stack.Screen name="apply" options={{ title: 'Apply for a seat' }} />
      <Stack.Screen name="plans" options={{ title: 'Plans' }} />
      <Stack.Screen name="not-riding" options={{ title: 'Not riding' }} />
      <Stack.Screen name="account" options={{ title: 'Account', presentation: 'modal' }} />
    </Stack>
  );
}

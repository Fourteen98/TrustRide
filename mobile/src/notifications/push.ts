import Constants, { ExecutionEnvironment } from 'expo-constants';
import * as Device from 'expo-device';
import { Platform } from 'react-native';

/**
 * Asks for permission to show notifications and returns this phone's push token, or null.
 *
 * Null is normal and means "no app notifications on this phone": she said no, or the app is running
 * somewhere push can't work. That is Expo Go, a simulator, the web build, and any build made before
 * the project was linked to an Expo account. Messages then reach her by SMS as before.
 *
 * The notifications package is only loaded when it can work, because loading it inside Expo Go
 * prints an error on Android.
 */
export async function getPushToken(): Promise<string | null> {
  if (Platform.OS === 'web' || !Device.isDevice || Constants.executionEnvironment === ExecutionEnvironment.StoreClient) {
    return null;
  }

  const projectId: string | undefined = Constants.expoConfig?.extra?.eas?.projectId ?? Constants.easConfig?.projectId;

  if (!projectId) {
    if (__DEV__) {
      console.warn('[push] No Expo project id in app.json, so this phone gets no app notifications. Run `npx eas init` once. See the README.');
    }

    return null;
  }

  try {
    const Notifications = await import('expo-notifications');

    // Show a notification even when the app is open on screen.
    Notifications.setNotificationHandler({
      handleNotification: async () => ({ shouldShowBanner: true, shouldShowList: true, shouldPlaySound: true, shouldSetBadge: false }),
    });

    if (Platform.OS === 'android') {
      await Notifications.setNotificationChannelAsync('default', { name: 'Aveo', importance: Notifications.AndroidImportance.HIGH });
    }

    const current = await Notifications.getPermissionsAsync();
    const granted = current.granted || (current.canAskAgain && (await Notifications.requestPermissionsAsync()).granted);

    if (!granted) {
      return null;
    }

    return (await Notifications.getExpoPushTokenAsync({ projectId })).data;
  } catch (cause) {
    if (__DEV__) {
      console.warn('[push] Could not get a push token:', cause);
    }

    return null;
  }
}

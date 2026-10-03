import * as SecureStore from 'expo-secure-store';
import { Platform } from 'react-native';

/** Keeps the login token in the phone's secure storage. On web it falls back to localStorage, for development only. */
const web = Platform.OS === 'web';

export async function getItem(key: string): Promise<string | null> {
  if (web) {
    return globalThis.localStorage?.getItem(key) ?? null;
  }

  return SecureStore.getItemAsync(key);
}

export async function setItem(key: string, value: string): Promise<void> {
  if (web) {
    globalThis.localStorage?.setItem(key, value);

    return;
  }

  await SecureStore.setItemAsync(key, value);
}

export async function removeItem(key: string): Promise<void> {
  if (web) {
    globalThis.localStorage?.removeItem(key);

    return;
  }

  await SecureStore.deleteItemAsync(key);
}

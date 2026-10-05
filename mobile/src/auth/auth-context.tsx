import { createContext, use, useCallback, useEffect, useMemo, useState, type ReactNode, useRef } from 'react';
import { Platform } from 'react-native';

import { api, call, setToken, setUnauthorisedHandler, type Account, type Role } from '@/api/client';

import { getPushToken } from '@/notifications/push';

import { getItem, removeItem, setItem } from './storage';

const TOKEN_KEY = 'trustride.token';
const ROLE_KEY = 'trustride.activeRole';

type Status = 'loading' | 'signedOut' | 'signedIn';

type AuthValue = {
  status: Status;
  account: Account | null;
  /** The role whose screens are showing. An account with one role never needs to switch. */
  activeRole: Role | null;
  requestCode: (phone: string) => Promise<void>;
  verifyCode: (phone: string, code: string) => Promise<void>;
  switchRole: (name: string) => Promise<void>;
  refresh: () => Promise<void>;
  /** Choose how TrustRide reaches her: in the app, or by SMS. */
  preferChannel: (channel: Account['preferred_channel']) => Promise<void>;
  logout: () => Promise<void>;
  /** For hiding buttons. The server still checks every request. */
  can: (permission: string) => boolean;
};

const AuthContext = createContext<AuthValue | null>(null);

/** Only roles that open a set of mobile screens can be the active role. */
function screenRoles(account: Account | null): Role[] {
  return account?.roles.filter((role) => role.screens !== null) ?? [];
}

export function AuthProvider({ children }: { children: ReactNode }) {
  const [status, setStatus] = useState<Status>('loading');
  const [account, setAccount] = useState<Account | null>(null);
  const [activeRoleName, setActiveRoleName] = useState<string | null>(null);
  const pushToken = useRef<string | null>(null);

  const signOutLocally = useCallback(async () => {
    setToken(null);
    setAccount(null);
    setStatus('signedOut');
    await removeItem(TOKEN_KEY);
  }, []);

  const loadAccount = useCallback(async () => {
    const data = await call(() => api.GET('/auth/me'));
    setAccount(data.account);
  }, []);

  // On launch: if a token is stored, use it to fetch the account.
  useEffect(() => {
    setUnauthorisedHandler(() => void signOutLocally());

    (async () => {
      const [storedToken, storedRole] = await Promise.all([getItem(TOKEN_KEY), getItem(ROLE_KEY)]);
      setActiveRoleName(storedRole);

      if (!storedToken) {
        setStatus('signedOut');

        return;
      }

      setToken(storedToken);

      try {
        await loadAccount();
        setStatus('signedIn');
      } catch {
        // Offline or the token expired. Either way, ask them to log in again.
        await signOutLocally();
      }
    })();

    return () => setUnauthorisedHandler(null);
  }, [loadAccount, signOutLocally]);

  // Once she is in, turn on app notifications for this phone if it can take them. Runs again each launch,
  // because a push token can change. Failing here must never get in the way of using the app.
  useEffect(() => {
    if (status !== 'signedIn') {
      return;
    }

    let stopped = false;

    (async () => {
      const token = await getPushToken();

      if (!token || stopped) {
        return;
      }

      pushToken.current = token;

      try {
        const data = await call(() => api.POST('/auth/push-device', { body: { token, platform: Platform.OS === 'ios' ? 'ios' : 'android' } }));

        if (!stopped) {
          setAccount(data.account);
        }
      } catch {
        // No network right now. The next launch tries again.
      }
    })();

    return () => {
      stopped = true;
    };
  }, [status]);

  const requestCode = useCallback(async (phone: string) => {
    await call(() => api.POST('/auth/code', { body: { phone } }));
  }, []);

  const verifyCode = useCallback(async (phone: string, code: string) => {
    const data = await call(() => api.POST('/auth/verify', { body: { phone, code, device_name: 'mobile' } }));

    setToken(data.token);
    await setItem(TOKEN_KEY, data.token);
    setAccount(data.account);
    setStatus('signedIn');
  }, []);

  const switchRole = useCallback(async (name: string) => {
    setActiveRoleName(name);
    await setItem(ROLE_KEY, name);
  }, []);

  const logout = useCallback(async () => {
    try {
      // Naming this phone's push token stops her notifications coming here after she has left.
      await api.POST('/auth/logout', { body: { push_token: pushToken.current } as never });
    } catch {
      // Logging out must work offline too.
    }

    await signOutLocally();
  }, [signOutLocally]);

  const value = useMemo<AuthValue>(() => {
    const roles = screenRoles(account);
    const activeRole = roles.find((role) => role.name === activeRoleName) ?? roles[0] ?? null;
    const permissions = new Set(account?.permissions ?? []);

    return {
      status,
      account,
      activeRole,
      requestCode,
      verifyCode,
      switchRole,
      refresh: loadAccount,
      preferChannel: async (channel) => setAccount((await call(() => api.PUT('/auth/preferred-channel', { body: { channel } }))).account),
      logout,
      can: (permission) => permissions.has(permission),
    };
  }, [status, account, activeRoleName, requestCode, verifyCode, switchRole, loadAccount, logout]);

  return <AuthContext value={value}>{children}</AuthContext>;
}

export function useAuth(): AuthValue {
  const value = use(AuthContext);

  if (!value) {
    throw new Error('useAuth must be used inside AuthProvider');
  }

  return value;
}

export { screenRoles };

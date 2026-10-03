import { createContext, use, useCallback, useEffect, useMemo, useState, type ReactNode } from 'react';

import { api, call, setToken, setUnauthorisedHandler, type Account, type Role } from '@/api/client';

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
      await api.POST('/auth/logout');
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

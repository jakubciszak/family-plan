import { createContext, use, useCallback, useEffect, useMemo, useState, type ReactNode } from 'react';

import apiClient, {
  ApiError,
  requestTokens,
  setSessionLostHandler,
  type AuthenticatedUser,
} from '@/api/client';
import { takeUpInvitation } from '@/api/invitations';
import { clearTokens, readTokens, writeTokens } from '@/api/tokenStore';

type AuthState = {
  user: AuthenticatedUser | null;
  restoring: boolean;
  isSuperAdmin: boolean;
  manages: boolean;
  signIn: (email: string, password: string, invite?: string, remember?: boolean) => Promise<void>;
  signOut: () => Promise<void>;
  refreshMemberships: () => Promise<void>;
};

type TeamMembership = { role: string };

const AuthContext = createContext<AuthState | null>(null);

export const AuthProvider = ({ children }: { children: ReactNode }) => {
  const [user, setUser] = useState<AuthenticatedUser | null>(null);
  const [restoring, setRestoring] = useState(true);
  const [administersTeam, setAdministersTeam] = useState(false);

  const signOut = useCallback(async () => {
    await clearTokens();
    setUser(null);
    setAdministersTeam(false);
  }, []);

  useEffect(() => {
    setSessionLostHandler(() => {
      setUser(null);
      setAdministersTeam(false);
    });
    return () => setSessionLostHandler(() => {});
  }, []);

  useEffect(() => {
    let cancelled = false;

    const restore = async () => {
      try {
        if (await readTokens()) {
          const me = await apiClient.get<AuthenticatedUser>('/api/auth/me');

          if (!cancelled) {
            setUser(me);
          }
        }
      } catch (error) {
        if (error instanceof ApiError && error.status === 401) {
          await clearTokens();
        }
      } finally {
        if (!cancelled) {
          setRestoring(false);
        }
      }
    };

    void restore();

    return () => {
      cancelled = true;
    };
  }, []);

  const signIn = useCallback(async (email: string, password: string, invite?: string, remember = true) => {
    const issued = await requestTokens(email, password);

    await writeTokens({
      accessToken: issued.token,
      refreshToken: issued.refresh_token,
    }, false, remember);

    await takeUpInvitation(invite);
    setUser(issued.user);
  }, []);

  const refreshMemberships = useCallback(async () => {
    if (!user) return;
    try {
      const { teams } = await apiClient.get<{ teams?: TeamMembership[] }>('/api/teams');
      setAdministersTeam((teams ?? []).some((team) => team.role === 'admin'));
    } catch {
      setAdministersTeam(false);
    }
  }, [user]);

  useEffect(() => {
    if (!user) return;
    let cancelled = false;
    apiClient.get<{ teams?: TeamMembership[] }>('/api/teams').then(({ teams }) => {
      if (!cancelled) setAdministersTeam((teams ?? []).some((team) => team.role === 'admin'));
    }).catch(() => undefined);
    return () => { cancelled = true; };
  }, [user]);

  const isSuperAdmin = user?.role === 'ROLE_ADMIN';

  const value = useMemo(
    () => ({
      user,
      restoring,
      isSuperAdmin,
      manages: isSuperAdmin || administersTeam,
      signIn,
      signOut,
      refreshMemberships,
    }),
    [user, restoring, isSuperAdmin, administersTeam, signIn, signOut, refreshMemberships]
  );

  return <AuthContext value={value}>{children}</AuthContext>;
};

export const useAuth = (): AuthState => {
  const value = use(AuthContext);

  if (!value) {
    throw new Error('useAuth must be used inside AuthProvider');
  }

  return value;
};

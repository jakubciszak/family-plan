import { createContext, use, useCallback, useEffect, useMemo, useRef, useState, type ReactNode } from 'react';
import { AppState } from 'react-native';

import {
  readPersonalisation,
  savePersonalisation,
  type Personalisation,
  type PersonalisationChanges,
} from '@/api/personalisation';
import { useAuth } from '@/auth/auth-context';
import i18n from '@/i18n';

type PersonalisationState = {
  own: Personalisation | null;
  loading: boolean;
  error: boolean;
  save: (changes: PersonalisationChanges) => Promise<void>;
  reload: () => Promise<void>;
};

const PersonalisationContext = createContext<PersonalisationState | null>(null);

export const PersonalisationProvider = ({ children }: { children: ReactNode }) => {
  const { user } = useAuth();
  return <SessionPersonalisation key={user?.id ?? 'guest'} authenticated={Boolean(user)}>{children}</SessionPersonalisation>;
};

const SessionPersonalisation = ({ children, authenticated }: { children: ReactNode; authenticated: boolean }) => {
  const active = useRef(true);
  const saves = useRef<Promise<void>>(Promise.resolve());
  const revision = useRef(0);
  const [error, setError] = useState(false);
  const [own, setOwn] = useState<Personalisation | null>(null);
  const [loading, setLoading] = useState(authenticated);

  const take = useCallback((held: Personalisation) => {
    if (!active.current) return;
    setOwn(held);
    setError(false);

    if (held.language && !i18n.language?.startsWith(held.language)) {
      void i18n.changeLanguage(held.language);
    }
  }, []);

  const reload = useCallback(async () => {
    if (!authenticated) return;
    const startedAt = revision.current;
    try {
      const held = await readPersonalisation();
      if (revision.current === startedAt) take(held);
    } catch {
      if (active.current) setError(true);
    } finally {
      setLoading(false);
    }
  }, [authenticated, take]);

  useEffect(() => {
    active.current = true;
    void reload();
    return () => { active.current = false; };
  }, [reload]);

  useEffect(() => {
    const watch = AppState.addEventListener('change', (next) => {
      if (next === 'active') {
        void reload();
      }
    });

    return () => watch.remove();
  }, [reload]);

  const save = useCallback(
    async (changes: PersonalisationChanges) => {
      revision.current += 1;
      const pending = saves.current.then(async () => {
        if (!active.current) return;
        try {
          take(await savePersonalisation(changes));
        } catch {
          if (active.current) setError(true);
        }
      });
      saves.current = pending;
      await pending;
    },
    [take]
  );

  const value = useMemo(
    () => ({ own, loading, error, save, reload }),
    [own, loading, error, save, reload]
  );

  return <PersonalisationContext value={value}>{children}</PersonalisationContext>;
};

export const usePersonalisation = (): PersonalisationState => {
  const value = use(PersonalisationContext);

  if (!value) {
    throw new Error('usePersonalisation must be used inside PersonalisationProvider');
  }

  return value;
};

import MaterialCommunityIcons from '@expo/vector-icons/MaterialCommunityIcons';
import { createContext, use, useMemo, type ReactNode } from 'react';
import { useColorScheme } from 'react-native';
import { PaperProvider } from 'react-native-paper';

import type { ThemeMode } from '@/api/personalisation';
import { usePersonalisation } from '@/personalisation/personalisation-context';

import { DEFAULT_SEED, isValidSeed } from './palette';
import { themeFor, type AppTheme } from './theme';

type ThemeState = {
  theme: AppTheme;
  mode: ThemeMode;
  dark: boolean;
};

const ThemeContext = createContext<ThemeState | null>(null);

export const AppThemeProvider = ({ children }: { children: ReactNode }) => {
  const scheme = useColorScheme();
  const { own } = usePersonalisation();

  const value = useMemo<ThemeState>(() => {
    const mode = own?.themeMode ?? 'system';
    const dark = mode === 'system' ? scheme === 'dark' : mode === 'dark';
    const seed = isValidSeed(own?.theme) ? own.theme : DEFAULT_SEED;

    return { theme: themeFor(seed, dark), mode, dark };
  }, [own, scheme]);

  return (
    <ThemeContext value={value}>
      <PaperProvider
        theme={value.theme}
        settings={{ icon: (props) => <MaterialCommunityIcons {...props} /> }}>
        {children}
      </PaperProvider>
    </ThemeContext>
  );
};

export const useAppTheme = (): ThemeState => {
  const value = use(ThemeContext);

  if (!value) {
    throw new Error('useAppTheme must be used inside AppThemeProvider');
  }

  return value;
};

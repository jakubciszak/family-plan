import {
  DarkTheme as NavigationDark,
  DefaultTheme as NavigationLight,
  Stack,
  ThemeProvider,
} from 'expo-router';
import * as SplashScreen from 'expo-splash-screen';
import { StatusBar } from 'expo-status-bar';
import { useEffect } from 'react';

import { AuthProvider, useAuth } from '@/auth/auth-context';
import '@/i18n';
import { PersonalisationProvider, usePersonalisation } from '@/personalisation/personalisation-context';
import { AppThemeProvider, useAppTheme } from '@/theme/theme-context';

void SplashScreen.preventAutoHideAsync();

function Shell() {
  const { restoring } = useAuth();
  const { loading: dressing } = usePersonalisation();
  const { theme, dark } = useAppTheme();

  const ready = !restoring && !dressing;

  useEffect(() => {
    if (ready) {
      void SplashScreen.hideAsync();
    }
  }, [ready]);

  const navigationBase = dark ? NavigationDark : NavigationLight;

  return (
    <ThemeProvider
      value={{
        ...navigationBase,
        colors: {
          ...navigationBase.colors,
          primary: theme.colors.primary,
          background: theme.colors.background,
          card: theme.colors.surface,
          text: theme.colors.onSurface,
          border: theme.colors.outlineVariant,
        },
      }}>
      <StatusBar style={dark ? 'light' : 'dark'} />
      <Stack screenOptions={{ headerShown: false }} />
    </ThemeProvider>
  );
}

export default function RootLayout() {
  return (
    <AuthProvider>
      <PersonalisationProvider>
        <AppThemeProvider>
          <Shell />
        </AppThemeProvider>
      </PersonalisationProvider>
    </AuthProvider>
  );
}

import { MD3DarkTheme, MD3LightTheme, type MD3Theme } from 'react-native-paper';

import { DEFAULT_SEED, paletteFor, type Palette } from './palette';

export type AppTheme = MD3Theme & { palette: Palette };

export const themeFor = (seed: string = DEFAULT_SEED, isDark = false): AppTheme => {
  const palette = paletteFor(seed, isDark);
  const base = isDark ? MD3DarkTheme : MD3LightTheme;

  return {
    ...base,
    palette,
    colors: {
      ...base.colors,
      primary: palette.primary,
      onPrimary: palette.onPrimary,
      primaryContainer: palette.primaryContainer,
      onPrimaryContainer: palette.onPrimaryContainer,
      secondary: palette.secondary,
      onSecondary: palette.onSecondary,
      secondaryContainer: palette.secondaryContainer,
      onSecondaryContainer: palette.onSecondaryContainer,
      tertiary: palette.tertiary,
      onTertiary: palette.onTertiary,
      tertiaryContainer: palette.tertiaryContainer,
      onTertiaryContainer: palette.onTertiaryContainer,
      error: palette.error,
      onError: palette.onError,
      errorContainer: palette.errorContainer,
      onErrorContainer: palette.onErrorContainer,
      background: palette.background,
      onBackground: palette.onBackground,
      surface: palette.surface,
      onSurface: palette.onSurface,
      surfaceVariant: palette.surfaceVariant,
      onSurfaceVariant: palette.onSurfaceVariant,
      outline: palette.outline,
      outlineVariant: palette.outlineVariant,
      shadow: palette.shadow,
      scrim: palette.scrim,
      inverseSurface: palette.inverseSurface,
      inverseOnSurface: palette.inverseOnSurface,
      inversePrimary: palette.inversePrimary,
      elevation: {
        level0: 'transparent',
        level1: palette.surfaceContainerLow,
        level2: palette.surfaceContainer,
        level3: palette.surfaceContainerHigh,
        level4: palette.surfaceContainerHigh,
        level5: palette.surfaceContainerHighest,
      },
    },
  };
};

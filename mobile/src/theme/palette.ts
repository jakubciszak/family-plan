import {
  argbFromHex,
  Hct,
  hexFromArgb,
  MaterialDynamicColors,
  SchemeTonalSpot,
  TonalPalette,
} from '@material/material-color-utilities';

export const DEFAULT_SEED = '#2E7D5B';

const ROLES = [
  'primary', 'onPrimary', 'primaryContainer', 'onPrimaryContainer',
  'secondary', 'onSecondary', 'secondaryContainer', 'onSecondaryContainer',
  'tertiary', 'onTertiary', 'tertiaryContainer', 'onTertiaryContainer',
  'error', 'onError', 'errorContainer', 'onErrorContainer',
  'background', 'onBackground',
  'surface', 'onSurface', 'surfaceVariant', 'onSurfaceVariant', 'surfaceTint',
  'surfaceDim', 'surfaceBright',
  'surfaceContainerLowest', 'surfaceContainerLow', 'surfaceContainer',
  'surfaceContainerHigh', 'surfaceContainerHighest',
  'outline', 'outlineVariant',
  'inverseSurface', 'inverseOnSurface', 'inversePrimary',
  'scrim', 'shadow',
] as const;

type Role = (typeof ROLES)[number];

const EXTRA_SEEDS = { success: '#2E7D32', streak: '#F57C00' } as const;

export type ExtraRole = keyof typeof EXTRA_SEEDS;

export type ExtraColors = Record<
  `${ExtraRole}` | `on${Capitalize<ExtraRole>}` | `${ExtraRole}Container` | `on${Capitalize<ExtraRole>}Container`,
  string
>;

export type Palette = Record<Role, string> & ExtraColors;

export const isValidSeed = (seed: unknown): seed is string =>
  /^#[0-9a-fA-F]{6}$/.test(String(seed ?? ''));

const capitalise = <T extends string>(value: T) =>
  (value.charAt(0).toUpperCase() + value.slice(1)) as Capitalize<T>;

const extrasFor = (isDark: boolean): ExtraColors =>
  Object.entries(EXTRA_SEEDS).reduce((acc, [name, hex]) => {
    const tones = TonalPalette.fromInt(argbFromHex(hex));
    const [base, container, onContainer] = isDark
      ? [tones.tone(80), tones.tone(30), tones.tone(90)]
      : [tones.tone(40), tones.tone(90), tones.tone(10)];

    return {
      ...acc,
      [name]: hexFromArgb(base),
      [`on${capitalise(name)}`]: hexFromArgb(isDark ? tones.tone(20) : tones.tone(100)),
      [`${name}Container`]: hexFromArgb(container),
      [`on${capitalise(name)}Container`]: hexFromArgb(onContainer),
    };
  }, {} as ExtraColors);

export const paletteFor = (seed: string, isDark: boolean): Palette => {
  const scheme = new SchemeTonalSpot(Hct.fromInt(argbFromHex(seed)), isDark, 0);

  const roles = ROLES.reduce(
    (acc, role) => ({ ...acc, [role]: hexFromArgb(MaterialDynamicColors[role].getArgb(scheme)) }),
    {} as Record<Role, string>
  );

  return { ...roles, ...extrasFor(isDark) };
};

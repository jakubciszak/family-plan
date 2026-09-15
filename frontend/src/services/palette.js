import {
    argbFromHex,
    hexFromArgb,
    Hct,
    MaterialDynamicColors,
    SchemeTonalSpot,
    TonalPalette,
} from '@material/material-color-utilities';

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
    'primaryFixed', 'primaryFixedDim', 'onPrimaryFixed', 'onPrimaryFixedVariant',
    'secondaryFixed', 'secondaryFixedDim', 'onSecondaryFixed', 'onSecondaryFixedVariant',
    'tertiaryFixed', 'tertiaryFixedDim', 'onTertiaryFixed', 'onTertiaryFixedVariant',
];

const EXTRA_SEEDS = { success: '#2E7D32', streak: '#F57C00' };

const kebab = (name) => name.replace(/([a-z0-9])([A-Z])/g, '$1-$2').toLowerCase();

const extras = (isDark) => Object.entries(EXTRA_SEEDS).flatMap(([name, hex]) => {
    const tones = TonalPalette.fromInt(argbFromHex(hex));
    const [base, container, onContainer] = isDark
        ? [tones.tone(80), tones.tone(30), tones.tone(90)]
        : [tones.tone(40), tones.tone(90), tones.tone(10)];

    return [
        [`--md-sys-color-${name}`, hexFromArgb(base)],
        [`--md-sys-color-on-${name}`, hexFromArgb(isDark ? tones.tone(20) : tones.tone(100))],
        [`--md-sys-color-${name}-container`, hexFromArgb(container)],
        [`--md-sys-color-on-${name}-container`, hexFromArgb(onContainer)],
    ];
});

export const paletteFor = (seed, isDark) => {
    const scheme = new SchemeTonalSpot(Hct.fromInt(argbFromHex(seed)), isDark, 0);

    return [
        ...ROLES.map((role) => [`--md-sys-color-${kebab(role)}`, hexFromArgb(MaterialDynamicColors[role].getArgb(scheme))]),
        ...extras(isDark),
    ];
};

export const isValidSeed = (seed) => /^#[0-9a-fA-F]{6}$/.test(String(seed || ''));

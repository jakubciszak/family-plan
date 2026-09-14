import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import {
    argbFromHex,
    hexFromArgb,
    Hct,
    SchemeTonalSpot,
    MaterialDynamicColors,
    TonalPalette,
} from '@material/material-color-utilities';

const SEED = '#2E7D5B';

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
const indent = (lines, pad) => lines.map((line) => pad + line).join('\n');

const schemeVars = (scheme, pad) => indent(
    ROLES.map((role) => `--md-sys-color-${kebab(role)}: ${hexFromArgb(MaterialDynamicColors[role].getArgb(scheme))};`),
    pad
);

const extraVars = (isDark, pad) => indent(
    Object.entries(EXTRA_SEEDS).flatMap(([name, hex]) => {
        const palette = TonalPalette.fromInt(argbFromHex(hex));
        const [base, container, onContainer] = isDark
            ? [palette.tone(80), palette.tone(30), palette.tone(90)]
            : [palette.tone(40), palette.tone(90), palette.tone(10)];
        return [
            `--md-sys-color-${name}: ${hexFromArgb(base)};`,
            `--md-sys-color-on-${name}: ${hexFromArgb(isDark ? palette.tone(20) : palette.tone(100))};`,
            `--md-sys-color-${name}-container: ${hexFromArgb(container)};`,
            `--md-sys-color-on-${name}-container: ${hexFromArgb(onContainer)};`,
        ];
    }),
    pad
);

const block = (scheme, isDark, pad) => `${schemeVars(scheme, pad)}\n${extraVars(isDark, pad)}`;

const source = Hct.fromInt(argbFromHex(SEED));
const light = new SchemeTonalSpot(source, false, 0);
const dark = new SchemeTonalSpot(source, true, 0);

const css = `:root,
[data-theme='light'] {
${block(light, false, '  ')}
}

[data-theme='dark'] {
${block(dark, true, '  ')}
}

@media (prefers-color-scheme: dark) {
  :root:not([data-theme='light']) {
${block(dark, true, '    ')}
  }
}
`;

const target = path.join(path.dirname(fileURLToPath(import.meta.url)), '..', 'src', 'styles', 'md3', 'color.css');
fs.mkdirSync(path.dirname(target), { recursive: true });
fs.writeFileSync(target, css);
console.log(`Generated ${path.relative(process.cwd(), target)} from seed ${SEED}`);

import { isValidSeed, paletteFor } from './palette';

const STYLE_ID = 'own-theme';

const asCss = (pairs) => pairs.map(([name, value]) => `${name}: ${value};`).join('');

/**
 * Paints the whole app from one colour, overriding the palette the stylesheet ships with.
 */
export const applyTheme = (seed) => {
    if (typeof document === 'undefined') {
        return;
    }

    const held = document.getElementById(STYLE_ID);

    if (!isValidSeed(seed)) {
        held?.remove();
        return;
    }

    const sheet = held || Object.assign(document.createElement('style'), { id: STYLE_ID });

    sheet.textContent = [
        `:root, [data-theme='light'] {${asCss(paletteFor(seed, false))}}`,
        `[data-theme='dark'] {${asCss(paletteFor(seed, true))}}`,
        `@media (prefers-color-scheme: dark) { :root:not([data-theme='light']) {${asCss(paletteFor(seed, true))}} }`,
    ].join('\n');

    if (!held) {
        document.head.appendChild(sheet);
    }
};

export const rememberTheme = (seed) => {
    try {
        isValidSeed(seed)
            ? localStorage.setItem('ownTheme', seed)
            : localStorage.removeItem('ownTheme');
    } catch {
        // storage unavailable
    }
};

export const recallTheme = () => {
    try {
        return localStorage.getItem('ownTheme');
    } catch {
        return null;
    }
};

import React from 'react';

const STORAGE_KEY = 'themeMode';
export const THEME_MODES = ['light', 'dark', 'system'];

const readStoredMode = () => {
    try {
        const stored = localStorage.getItem(STORAGE_KEY);
        return THEME_MODES.includes(stored) ? stored : 'system';
    } catch {
        return 'system';
    }
};

const resolve = (mode) => {
    if (mode !== 'system') {
        return mode;
    }
    return window.matchMedia?.('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
};

const surfaceColor = () =>
    getComputedStyle(document.documentElement)
        .getPropertyValue('--md-sys-color-surface')
        .trim();

export default function useThemeMode() {
    const [mode, setMode] = React.useState(readStoredMode);

    React.useEffect(() => {
        const root = document.documentElement;

        const apply = () => {
            const resolved = resolve(mode);
            root.dataset.theme = resolved;
            root.style.colorScheme = resolved;
            document.querySelector('meta[name="theme-color"]')?.setAttribute('content', surfaceColor());
        };

        apply();

        try {
            localStorage.setItem(STORAGE_KEY, mode);
        } catch {
            // storage unavailable
        }

        if (mode !== 'system') {
            return undefined;
        }

        const query = window.matchMedia('(prefers-color-scheme: dark)');
        query.addEventListener('change', apply);
        return () => query.removeEventListener('change', apply);
    }, [mode]);

    return [mode, setMode];
}

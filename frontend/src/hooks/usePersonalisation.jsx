import React from 'react';
import personalisationService from '../services/personalisationService';
import { applyTheme, recallTheme, rememberTheme } from '../services/theme';

const PersonalisationContext = React.createContext(null);

const applyBackdrop = (backdrop) => {
    const root = document.documentElement;

    if (!backdrop || (backdrop.pattern === 'plain' && !backdrop.pictureId)) {
        delete root.dataset.backdrop;
        root.style.removeProperty('--own-backdrop-image');
        root.style.removeProperty('--own-backdrop-dimming');
        return;
    }

    if (backdrop.pictureId) {
        root.dataset.backdrop = 'picture';
        root.style.setProperty('--own-backdrop-image', `url("/api/personalisation/pictures/${backdrop.pictureId}")`);
        root.style.setProperty('--own-backdrop-dimming', String((backdrop.dimming ?? 40) / 100));
        return;
    }

    root.dataset.backdrop = backdrop.pattern;
    root.style.removeProperty('--own-backdrop-image');
    root.style.removeProperty('--own-backdrop-dimming');
};

export function PersonalisationProvider({ children }) {
    const [own, setOwn] = React.useState(null);

    React.useEffect(() => {
        applyTheme(recallTheme());
    }, []);

    const take = React.useCallback((held) => {
        setOwn(held);
        applyTheme(held.theme);
        rememberTheme(held.theme);
        applyBackdrop(held.backdrop);

        return held;
    }, []);

    const reload = React.useCallback(
        () => personalisationService.getMine().then(take).catch(() => setOwn(null)),
        [take]
    );

    React.useEffect(() => {
        reload();
    }, [reload]);

    const save = React.useCallback(
        (changes) => personalisationService.save(changes).then(take),
        [take]
    );

    const value = React.useMemo(() => ({ own, save, reload }), [own, save, reload]);

    return (
        <PersonalisationContext.Provider value={value}>
            {children}
        </PersonalisationContext.Provider>
    );
}

export const usePersonalisation = () => React.useContext(PersonalisationContext)
    || { own: null, save: () => Promise.resolve(), reload: () => Promise.resolve() };

export default usePersonalisation;

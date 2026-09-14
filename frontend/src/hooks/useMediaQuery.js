import React from 'react';

export default function useMediaQuery(query) {
    const [matches, setMatches] = React.useState(
        () => window.matchMedia?.(query).matches ?? false
    );

    React.useEffect(() => {
        const media = window.matchMedia(query);
        const onChange = (event) => setMatches(event.matches);

        setMatches(media.matches);
        media.addEventListener('change', onChange);
        return () => media.removeEventListener('change', onChange);
    }, [query]);

    return matches;
}

export const useWindowClass = () => {
    const medium = useMediaQuery('(min-width: 600px)');
    const expanded = useMediaQuery('(min-width: 1240px)');

    if (expanded) {
        return 'expanded';
    }
    return medium ? 'medium' : 'compact';
};

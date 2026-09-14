import React from 'react';
import { ICON_PATHS } from './iconPaths';

function Icon({ name, size = 24, className = '', title }) {
    const path = ICON_PATHS[name];

    if (!path) {
        return null;
    }

    return (
        <svg
            className={className}
            width={size}
            height={size}
            viewBox="0 -960 960 960"
            fill="currentColor"
            role={title ? 'img' : 'presentation'}
            aria-hidden={title ? undefined : true}
            aria-label={title}
            focusable="false"
        >
            {title && <title>{title}</title>}
            <path d={path} />
        </svg>
    );
}

export default Icon;

import React from 'react';
import Icon from './Icon';
import useRipple from './useRipple';

function Fab({ icon, label, extended = false, className = '', onPointerDown, ...rest }) {
    const spawnRipple = useRipple();

    const classes = [
        'md-fab',
        extended ? 'md-fab--extended' : '',
        'md-ripple-host',
        className,
    ].filter(Boolean).join(' ');

    return (
        <button
            type="button"
            className={classes}
            aria-label={extended ? undefined : label}
            onPointerDown={(event) => {
                spawnRipple(event);
                onPointerDown?.(event);
            }}
            {...rest}
        >
            <Icon name={icon} size={24} />
            {extended && <span>{label}</span>}
        </button>
    );
}

export default Fab;

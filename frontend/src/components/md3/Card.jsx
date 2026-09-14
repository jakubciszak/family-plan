import React from 'react';
import useRipple from './useRipple';

function Card({
    variant = 'elevated',
    selected = false,
    onClick,
    className = '',
    children,
    ...rest
}) {
    const spawnRipple = useRipple();
    const interactive = typeof onClick === 'function';

    const classes = [
        'md-card',
        `md-card--${variant}`,
        interactive ? 'md-card--interactive md-ripple-host' : '',
        selected ? 'md-card--selected' : '',
        className,
    ].filter(Boolean).join(' ');

    if (!interactive) {
        return <div className={classes} {...rest}>{children}</div>;
    }

    return (
        <button
            type="button"
            className={classes}
            aria-pressed={selected}
            onClick={onClick}
            onPointerDown={spawnRipple}
            {...rest}
        >
            {children}
        </button>
    );
}

export default Card;

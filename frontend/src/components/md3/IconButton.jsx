import React from 'react';
import Icon from './Icon';
import useRipple from './useRipple';

function IconButton({
    icon,
    label,
    variant = 'standard',
    selected = false,
    size = 24,
    className = '',
    onPointerDown,
    children,
    ...rest
}) {
    const spawnRipple = useRipple();

    const classes = [
        'md-icon-button',
        variant !== 'standard' ? `md-icon-button--${variant}` : '',
        selected ? 'md-icon-button--selected' : '',
        'md-ripple-host',
        className,
    ].filter(Boolean).join(' ');

    return (
        <button
            type="button"
            className={classes}
            aria-label={label}
            title={label}
            onPointerDown={(event) => {
                spawnRipple(event);
                onPointerDown?.(event);
            }}
            {...rest}
        >
            {children || <Icon name={icon} size={size} />}
        </button>
    );
}

export default IconButton;

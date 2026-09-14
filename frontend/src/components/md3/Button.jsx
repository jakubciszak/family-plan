import React from 'react';
import Icon from './Icon';
import useRipple from './useRipple';

const LEGACY_CLASS = {
    filled: 'btn-primary',
    tonal: 'btn-secondary',
    outlined: 'btn-secondary',
    text: 'btn-secondary',
    elevated: 'btn-secondary',
};

function Button({
    variant = 'filled',
    tone,
    icon,
    trailingIcon,
    loading = false,
    disabled = false,
    fullWidth = false,
    className = '',
    children,
    onPointerDown,
    ...rest
}) {
    const spawnRipple = useRipple();

    const legacy = tone === 'danger'
        ? 'btn-danger'
        : tone === 'success'
            ? 'btn-success'
            : tone === 'warning'
                ? 'btn-warning'
                : LEGACY_CLASS[variant];

    const classes = [
        'md-button',
        `md-button--${variant}`,
        tone ? `md-button--${tone}` : '',
        fullWidth ? 'md-button--full' : '',
        loading ? 'md-button--loading' : '',
        'md-ripple-host',
        legacy,
        className,
    ].filter(Boolean).join(' ');

    return (
        <button
            className={classes}
            disabled={disabled || loading}
            aria-busy={loading || undefined}
            onPointerDown={(event) => {
                spawnRipple(event);
                onPointerDown?.(event);
            }}
            {...rest}
        >
            {loading && <span className="md-button__spinner" aria-hidden="true" />}
            {!loading && icon && <Icon name={icon} size={18} className="md-button__icon" />}
            <span>{children}</span>
            {!loading && trailingIcon && <Icon name={trailingIcon} size={18} className="md-button__icon" />}
        </button>
    );
}

export default Button;

import React from 'react';
import Icon from './Icon';

function Chip({ icon, tone, selected = false, className = '', children, onClick, ...rest }) {
    const classes = [
        'md-chip',
        tone ? `md-chip--${tone}` : '',
        selected ? 'md-chip--selected' : '',
        onClick ? 'md-ripple-host' : '',
        className,
    ].filter(Boolean).join(' ');

    const content = (
        <>
            {icon && <Icon name={icon} size={18} className="md-chip__icon" />}
            <span>{children}</span>
        </>
    );

    if (onClick) {
        return (
            <button type="button" className={classes} aria-pressed={selected} onClick={onClick} {...rest}>
                {content}
            </button>
        );
    }

    return <span className={classes} {...rest}>{content}</span>;
}

export default Chip;

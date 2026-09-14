import React from 'react';
import Icon from './Icon';

function Switch({ id, checked, onChange, disabled = false, label, labelledBy }) {
    return (
        <button
            type="button"
            id={id}
            role="switch"
            className="md-switch"
            aria-checked={checked}
            aria-label={label}
            aria-labelledby={labelledBy}
            disabled={disabled}
            onClick={() => onChange(!checked)}
        >
            <span className="md-switch__track">
                <span className="md-switch__handle">
                    {checked && <Icon name="check" size={16} />}
                </span>
            </span>
        </button>
    );
}

export default Switch;

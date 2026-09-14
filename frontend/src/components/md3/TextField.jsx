import React from 'react';
import Select from './Select';

function TextField({
    id,
    label,
    value,
    as = 'input',
    supportingText,
    error = false,
    className = '',
    children,
    ...rest
}) {
    const populated = as === 'select' || (value !== undefined && value !== null && `${value}` !== '');
    const supportingId = supportingText ? `${id}-supporting` : undefined;

    const classes = [
        'md-field',
        'form-group',
        as === 'textarea' ? 'md-field--textarea' : '',
        as === 'select' ? 'md-field--select' : '',
        populated ? 'md-field--populated' : '',
        error ? 'md-field--error' : '',
        className,
    ].filter(Boolean).join(' ');

    const fieldProps = {
        id,
        value,
        className: 'md-field__input',
        'aria-describedby': supportingId,
        'aria-invalid': error || undefined,
        ...rest,
    };

    return (
        <div className={classes}>
            <div className="md-field__box">
                {as === 'textarea' && <textarea {...fieldProps} />}
                {as === 'input' && <input {...fieldProps} />}
                {as === 'select' && (
                    <Select
                        id={id}
                        label={label}
                        value={value}
                        describedBy={supportingId}
                        invalid={error}
                        {...rest}
                    >
                        {children}
                    </Select>
                )}
                <label className="md-field__label" id={`${id}-label`} htmlFor={id}>{label}</label>
            </div>
            {supportingText && (
                <small id={supportingId} className="md-field__supporting">{supportingText}</small>
            )}
        </div>
    );
}

export default TextField;

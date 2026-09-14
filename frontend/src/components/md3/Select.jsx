import React from 'react';
import Icon from './Icon';
import useRipple from './useRipple';

const optionsFrom = (children) =>
    React.Children.toArray(children)
        .filter((child) => React.isValidElement(child) && child.type === 'option')
        .map((child) => ({
            value: child.props.value ?? '',
            label: React.Children.toArray(child.props.children).join(''),
            disabled: !!child.props.disabled,
        }));

function Select({ id, label, value, onChange, disabled, required, name, children, describedBy, invalid, triggerClassName = 'md-field__input', ariaLabel }) {
    const options = optionsFrom(children);
    const [open, setOpen] = React.useState(false);
    const [active, setActive] = React.useState(0);
    const rootRef = React.useRef(null);
    const nativeRef = React.useRef(null);
    const spawnRipple = useRipple();

    const selectedIndex = Math.max(0, options.findIndex((option) => `${option.value}` === `${value}`));
    const selected = options[selectedIndex];

    React.useEffect(() => {
        if (!open) {
            return undefined;
        }

        setActive(selectedIndex);

        const onPointerDown = (event) => {
            if (!rootRef.current?.contains(event.target)) {
                setOpen(false);
            }
        };

        document.addEventListener('pointerdown', onPointerDown);
        return () => document.removeEventListener('pointerdown', onPointerDown);
    }, [open, selectedIndex]);

    const commit = (option) => {
        setOpen(false);

        const native = nativeRef.current;
        if (!native) {
            return;
        }

        const setter = Object.getOwnPropertyDescriptor(window.HTMLSelectElement.prototype, 'value')?.set;
        setter?.call(native, option.value);
        native.dispatchEvent(new Event('change', { bubbles: true }));
    };

    const onKeyDown = (event) => {
        if (disabled) {
            return;
        }

        if (!open && ['Enter', ' ', 'ArrowDown', 'ArrowUp'].includes(event.key)) {
            event.preventDefault();
            setOpen(true);
            return;
        }

        if (!open) {
            return;
        }

        if (event.key === 'Escape') {
            event.preventDefault();
            setOpen(false);
        } else if (event.key === 'ArrowDown') {
            event.preventDefault();
            setActive((index) => Math.min(options.length - 1, index + 1));
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            setActive((index) => Math.max(0, index - 1));
        } else if (event.key === 'Home') {
            event.preventDefault();
            setActive(0);
        } else if (event.key === 'End') {
            event.preventDefault();
            setActive(options.length - 1);
        } else if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            if (options[active] && !options[active].disabled) {
                commit(options[active]);
            }
        }
    };

    return (
        <div className={`md-select${open ? ' md-select--open' : ''}`} ref={rootRef}>
            <select
                ref={nativeRef}
                id={id}
                name={name}
                value={value}
                onChange={onChange}
                disabled={disabled}
                required={required}
                className="md-select__native"
                tabIndex={-1}
                aria-hidden="true"
            >
                {children}
            </select>

            <button
                type="button"
                className={`md-select__trigger ${triggerClassName} md-ripple-host`}
                role="combobox"
                aria-haspopup="listbox"
                aria-expanded={open}
                aria-controls={`${id}-listbox`}
                aria-label={ariaLabel}
                aria-labelledby={ariaLabel ? undefined : `${id}-label`}
                aria-describedby={describedBy}
                aria-invalid={invalid || undefined}
                disabled={disabled}
                onPointerDown={spawnRipple}
                onClick={() => setOpen((current) => !current)}
                onKeyDown={onKeyDown}
            >
                <span className="md-select__value">{selected?.label ?? ''}</span>
                <Icon name="expand" size={24} className="md-select__arrow" />
            </button>

            {open && (
                <ul
                    className="md-select__menu"
                    role="listbox"
                    id={`${id}-listbox`}
                    aria-label={ariaLabel}
                    aria-labelledby={ariaLabel ? undefined : `${id}-label`}
                >
                    {options.map((option, index) => (
                        <li
                            key={`${option.value}`}
                            role="option"
                            aria-selected={index === selectedIndex}
                            className={[
                                'md-select__option',
                                index === selectedIndex ? 'is-selected' : '',
                                index === active ? 'is-active' : '',
                                option.disabled ? 'is-disabled' : '',
                            ].filter(Boolean).join(' ')}
                            onPointerEnter={() => setActive(index)}
                            onClick={() => !option.disabled && commit(option)}
                        >
                            <span className="md-select__option-check">
                                {index === selectedIndex && <Icon name="check" size={20} />}
                            </span>
                            {option.label}
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}

export default Select;

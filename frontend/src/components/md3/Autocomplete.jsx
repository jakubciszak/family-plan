import React, { useEffect, useRef, useState } from 'react';
import Icon from './Icon';
import TextField from './TextField';

const normalized = (text) => text.trim().toLocaleLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/ł/g, 'l');

export default function Autocomplete({ id, label, options, value, onChange, placeholder, emptyText }) {
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const [active, setActive] = useState(0);
    const activeRef = useRef(null);
    const selected = options.find((option) => option.value === value);
    const matches = options.filter((option) => normalized(option.label).includes(normalized(query)));
    const activeId = open && matches[active] ? `${id}-option-${active}` : undefined;

    useEffect(() => {
        if (open) activeRef.current?.scrollIntoView({ block: 'nearest' });
    }, [open, active, query]);

    const show = () => {
        if (open) return;
        setQuery('');
        setActive(Math.max(0, options.findIndex((option) => option.value === value)));
        setOpen(true);
    };

    const choose = (option) => {
        onChange(option.value);
        setOpen(false);
        setQuery('');
    };

    const handleKeyDown = (event) => {
        if (event.nativeEvent.isComposing) return;
        if (event.key === 'Escape') {
            if (open) event.preventDefault();
            setOpen(false);
        } else if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            if (!open) show();
            else setActive((index) => Math.max(0, Math.min(matches.length - 1, index + (event.key === 'ArrowDown' ? 1 : -1))));
        } else if (event.key === 'Enter' && open) {
            event.preventDefault();
            if (matches[active]) choose(matches[active]);
        }
    };

    return <div className={`md-autocomplete${open ? ' md-autocomplete--open' : ''}`}>
        <TextField
            id={id}
            label={label}
            role="combobox"
            value={open ? query : selected?.label || ''}
            placeholder={placeholder}
            autoComplete="off"
            aria-autocomplete="list"
            aria-expanded={open}
            aria-controls={open ? `${id}-listbox` : undefined}
            aria-activedescendant={activeId}
            onFocus={show}
            onClick={show}
            onBlur={() => setOpen(false)}
            onChange={(event) => { setQuery(event.target.value); setActive(0); setOpen(true); }}
            onKeyDown={handleKeyDown}
        />
        <Icon name="expand" className="md-autocomplete__arrow" />
        {open && <div className="md-select__menu md-autocomplete__menu">
            <ul id={`${id}-listbox`} role="listbox" aria-labelledby={`${id}-label`}>
                {matches.map((option, index) => <li
                    key={option.value}
                    id={`${id}-option-${index}`}
                    ref={index === active ? activeRef : null}
                    role="option"
                    aria-selected={option.value === value}
                    className={`md-select__option${index === active ? ' is-active' : ''}${option.value === value ? ' is-selected' : ''}`}
                    onMouseDown={(event) => event.preventDefault()}
                    onClick={() => choose(option)}
                >
                    <span className="md-select__option-check">{option.value === value && <Icon name="check" size={20} />}</span>
                    {option.label}
                </li>)}
            </ul>
            {!matches.length && <p className="md-autocomplete__empty" role="status">{emptyText}</p>}
        </div>}
    </div>;
}

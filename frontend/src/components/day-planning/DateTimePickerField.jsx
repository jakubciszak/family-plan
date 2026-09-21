import React, { useEffect, useId, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { useTranslation } from 'react-i18next';
import { Button, Icon } from '../md3';
import { browserTimeZone, dateInZone } from '../../services/dayPlanningTime';

const pad = (value, length = 2) => String(value).padStart(length, '0');
const calendarDate = (value) => {
    const parts = /^(-?[0-9]+)-([0-9]{2})-([0-9]{2})$/.exec(value);
    if (!parts) return new Date(NaN);
    const date = new Date(0);
    date.setUTCHours(12, 0, 0, 0);
    date.setUTCFullYear(Number(parts[1]), Number(parts[2]) - 1, Number(parts[3]));
    return date;
};
const dateString = (value) => `${pad(value.getUTCFullYear(), 4)}-${pad(value.getUTCMonth() + 1)}-${pad(value.getUTCDate())}`;
const shiftDay = (value, amount) => { const date = calendarDate(value); date.setUTCDate(date.getUTCDate() + amount); return dateString(date); };
const shiftMonth = (value, amount) => {
    const date = calendarDate(value);
    const day = date.getUTCDate();
    date.setUTCDate(1);
    date.setUTCMonth(date.getUTCMonth() + amount + 1);
    date.setUTCDate(0);
    date.setUTCDate(Math.min(day, date.getUTCDate()));
    return dateString(date);
};
const validDate = (value) => /^[0-9]{4}-[0-9]{2}-[0-9]{2}$/.test(value) && Number(value.slice(0, 4)) >= 1 && Number(value.slice(0, 4)) <= 9999 && !Number.isNaN(calendarDate(value).getTime()) && dateString(calendarDate(value)) === value;
const validTime = (value) => /^([01][0-9]|2[0-3]):[0-5][0-9]$/.test(value);
const validValue = (value, type, min) => (type === 'time' ? validTime(value) : type === 'date' ? validDate(value) : validDate(value.slice(0, 10)) && value[10] === 'T' && validTime(value.slice(11))) && (!min || value >= min);
const displayValue = (value, type, locale) => {
    if (!value || type === 'time' || !validDate(value.slice(0, 10))) return value;
    const [year, month, day] = value.slice(0, 10).split('-');
    const date = locale.startsWith('pl') ? `${day}.${month}.${year}` : `${month}/${day}/${year}`;
    return type === 'date' ? date : `${date} ${value.slice(11)}`;
};
const parseValue = (value, type, locale) => {
    const trimmed = value.trim();
    if (validValue(trimmed, type)) return trimmed;
    if (type === 'time') return trimmed;
    const parts = (locale.startsWith('pl') ? /^([0-9]{2})\.([0-9]{2})\.([0-9]{4})(?: ([0-9]{2}:[0-9]{2}))?$/ : /^([0-9]{2})\/([0-9]{2})\/([0-9]{4})(?: ([0-9]{2}:[0-9]{2}))?$/).exec(trimmed);
    if (!parts || type === 'date' && parts[4] || type === 'datetime-local' && !parts[4]) return '';
    const day = locale.startsWith('pl') ? parts[1] : parts[2];
    const month = locale.startsWith('pl') ? parts[2] : parts[1];
    return `${parts[3]}-${month}-${day}${type === 'date' ? '' : `T${parts[4]}`}`;
};
const todayIn = (zone) => { try { return dateInZone(new Date(), zone); } catch { return dateInZone(new Date(), browserTimeZone()); } };

function TimeColumn({ label, value, count, onChange, focusOnOpen }) {
    const ref = useRef(null);
    useEffect(() => { ref.current?.querySelector('[aria-selected="true"]')?.scrollIntoView({ block: 'nearest' }); }, [value]);
    return <div className="day-picker-time-column"><h3>{label}</h3><div className="day-picker-time-list" role="listbox" aria-label={label} ref={ref}>{Array.from({ length: count }, (_, index) => <button type="button" key={index} role="option" aria-selected={value === index} tabIndex={value === index ? 0 : -1} data-picker-focus={focusOnOpen && value === index ? 'true' : undefined} onClick={() => onChange(index)} onKeyDown={(event) => {
        const next = event.key === 'ArrowDown' ? (value + 1) % count : event.key === 'ArrowUp' ? (value + count - 1) % count : event.key === 'Home' ? 0 : event.key === 'End' ? count - 1 : null;
        if (next !== null) { event.preventDefault(); onChange(next); ref.current?.querySelectorAll('button')[next]?.focus(); }
    }}>{pad(index)}</button>)}</div></div>;
}

function PickerDialog({ label, value, type, min, timeZone, onChoose, onDismiss, returnFocus }) {
    const { t, i18n } = useTranslation();
    const locale = i18n.language;
    const today = todayIn(timeZone);
    const minimumDate = type === 'time' ? '' : min?.slice(0, 10);
    const initialDate = validDate(value.slice(0, 10)) ? value.slice(0, 10) : minimumDate || today;
    const date = minimumDate && initialDate < minimumDate ? minimumDate : initialDate;
    const initialTime = type === 'time' ? value : value.slice(11);
    const [selectedDate, setSelectedDate] = useState(date);
    const [month, setMonth] = useState(date.slice(0, 7));
    const [focusedDate, setFocusedDate] = useState(date);
    const [time, setTime] = useState(validTime(initialTime) ? initialTime : '09:00');
    const [panel, setPanel] = useState(type === 'time' ? 'time' : 'date');
    const dialog = useRef(null);
    const needsFocus = useRef(false);
    const heading = useId();
    const weekStart = locale.startsWith('pl') ? 1 : 0;
    const first = `${month}-01`;
    const gridStart = shiftDay(first, -((calendarDate(first).getUTCDay() + 7 - weekStart) % 7));
    const dates = Array.from({ length: 42 }, (_, index) => shiftDay(gridStart, index));
    const dateLabel = (day, options = {}) => new Intl.DateTimeFormat(locale, { timeZone: 'UTC', day: 'numeric', month: 'long', year: 'numeric', ...options }).format(calendarDate(day));
    const selected = type === 'time' ? time : type === 'date' ? selectedDate : `${selectedDate}T${time}`;
    useEffect(() => {
        const surface = dialog.current;
        surface.showModal();
        surface.querySelector('[data-picker-focus="true"]')?.focus();
        return () => { surface.close(); returnFocus?.focus(); };
    }, []);
    useEffect(() => {
        if (needsFocus.current) { dialog.current?.querySelector('[data-picker-focus="true"]')?.focus(); needsFocus.current = false; }
    }, [focusedDate, panel]);
    const moveFocus = (day) => {
        if (!validDate(day)) return;
        const next = minimumDate && day < minimumDate ? minimumDate : day;
        needsFocus.current = true;
        setFocusedDate(next);
        setMonth(next.slice(0, 7));
    };
    const calendarKey = (event, day) => {
        const weekday = (calendarDate(day).getUTCDay() + 7 - weekStart) % 7;
        const next = event.key === 'ArrowRight' ? shiftDay(day, 1) : event.key === 'ArrowLeft' ? shiftDay(day, -1) : event.key === 'ArrowDown' ? shiftDay(day, 7) : event.key === 'ArrowUp' ? shiftDay(day, -7) : event.key === 'Home' ? shiftDay(day, -weekday) : event.key === 'End' ? shiftDay(day, 6 - weekday) : event.key === 'PageUp' ? shiftMonth(day, event.shiftKey ? -12 : -1) : event.key === 'PageDown' ? shiftMonth(day, event.shiftKey ? 12 : 1) : null;
        if (next) { event.preventDefault(); moveFocus(next); }
    };
    const changeMonth = (next) => { setMonth(next.slice(0, 7)); setFocusedDate(minimumDate && next < minimumDate ? minimumDate : next); };
    const changePanel = (next) => { needsFocus.current = true; setPanel(next); };
    const trapFocus = (event) => {
        if (event.key !== 'Tab') return;
        const controls = [...dialog.current.querySelectorAll('button, input, select, [tabindex]')].filter((element) => !element.matches(':disabled') && element.tabIndex >= 0 && element.getClientRects().length);
        const firstControl = controls[0];
        const lastControl = controls.at(-1);
        if (event.shiftKey && document.activeElement === firstControl) { event.preventDefault(); lastControl?.focus(); }
        else if (!event.shiftKey && document.activeElement === lastControl) { event.preventDefault(); firstControl?.focus(); }
    };
    return createPortal(<dialog className="day-planning day-picker-dialog" aria-labelledby={heading} ref={dialog} onKeyDown={trapFocus} onCancel={(event) => { event.preventDefault(); onDismiss(); }} onClick={(event) => { if (event.target === event.currentTarget) onDismiss(); }}>
        <header className="day-picker-head"><p className="day-eyebrow" id={heading}>{label}</p><h2>{type === 'time' ? time : dateLabel(selectedDate, { weekday: 'short', month: 'short', year: undefined })}</h2>{type === 'datetime-local' && <p className="day-picker-selected-time">{time}</p>}</header>
        {type === 'datetime-local' && <div className="day-picker-tabs" role="tablist" aria-label={t('dayPlanning.picker.parts')}>{['date', 'time'].map((part) => <button type="button" key={part} role="tab" aria-selected={panel === part} onClick={() => changePanel(part)}>{t(`dayPlanning.picker.${part}`)}</button>)}</div>}
        <div className="day-picker-body">
            {panel === 'date' ? <>
                <div className="day-picker-month"><button type="button" className="day-picker-arrow" aria-label={t('dayPlanning.picker.previousMonth')} disabled={month === '0001-01'} onClick={() => { changeMonth(shiftMonth(`${month}-01`, -1)); }}><Icon name="chevronLeft" /></button><select aria-label={t('dayPlanning.picker.month')} value={month.slice(5, 7)} onChange={(event) => { changeMonth(`${month.slice(0, 4)}-${event.target.value}-01`); }}>{Array.from({ length: 12 }, (_, index) => <option key={index} value={pad(index + 1)}>{dateLabel(`2026-${pad(index + 1)}-01`, { day: undefined, year: undefined })}</option>)}</select><input type="number" aria-label={t('dayPlanning.picker.year')} min={1} max={9999} value={Number(month.slice(0, 4))} onChange={(event) => { const year = Number(event.target.value); if (year >= 1 && year <= 9999 && Number.isInteger(year)) { changeMonth(`${pad(year, 4)}-${month.slice(5, 7)}-01`); } }} /><button type="button" className="day-picker-arrow" aria-label={t('dayPlanning.picker.nextMonth')} disabled={month === '9999-12'} onClick={() => { changeMonth(shiftMonth(`${month}-01`, 1)); }}><Icon name="chevronRight" /></button></div>
                <div className="day-picker-calendar" role="grid" aria-label={dateLabel(first, { day: undefined })}><div role="row" className="day-picker-weekdays">{dates.slice(0, 7).map((day) => <span key={day} role="columnheader">{dateLabel(day, { weekday: 'short', day: undefined, month: undefined, year: undefined })}</span>)}</div>{Array.from({ length: 6 }, (_, week) => <div role="row" key={week}>{dates.slice(week * 7, week * 7 + 7).map((day) => <button type="button" role="gridcell" key={day} aria-label={dateLabel(day, { weekday: 'long' })} aria-selected={selectedDate === day} aria-current={day === today ? 'date' : undefined} disabled={!validDate(day) || !!minimumDate && day < minimumDate} className={day.slice(0, 7) === month ? '' : 'is-other-month'} tabIndex={day === focusedDate ? 0 : -1} data-picker-focus={day === focusedDate ? 'true' : undefined} onClick={() => { setSelectedDate(day); setFocusedDate(day); }} onKeyDown={(event) => calendarKey(event, day)}>{Number(day.slice(8, 10))}</button>)}</div>)}</div>
                <button type="button" className="day-picker-today" disabled={!!minimumDate && today < minimumDate} onClick={() => { setSelectedDate(today); moveFocus(today); }}>{t('dayPlanning.today')}</button>
            </> : <div className="day-picker-time"><TimeColumn label={t('dayPlanning.picker.hour')} value={Number(time.slice(0, 2))} count={24} onChange={(hour) => setTime(`${pad(hour)}:${time.slice(3)}`)} focusOnOpen /><span className="day-picker-colon" aria-hidden="true">:</span><TimeColumn label={t('dayPlanning.picker.minute')} value={Number(time.slice(3))} count={60} onChange={(minute) => setTime(`${time.slice(0, 2)}:${pad(minute)}`)} /></div>}
        </div>
        <footer className="day-picker-actions"><Button type="button" variant="text" onClick={onDismiss}>{t('common.cancel')}</Button><Button type="button" disabled={!validValue(selected, type, min)} onClick={() => onChoose(selected)}>{t('dayPlanning.picker.choose')}</Button></footer>
    </dialog>, document.body);
}

export default function DateTimePickerField({ label, type = 'date', value = '', onChange, required = false, min, timeZone = browserTimeZone(), compact = false, disabled = false }) {
    const { t, i18n } = useTranslation();
    const locale = i18n.language;
    const id = useId();
    const [text, setText] = useState(() => displayValue(value, type, locale));
    const [open, setOpen] = useState(false);
    const input = useRef(null);
    const returnFocus = useRef(null);
    const openedContext = useRef(null);
    const context = JSON.stringify([value, type, min, timeZone, disabled, locale]);
    useEffect(() => { setText(displayValue(value, type, locale)); }, [value, type, locale]);
    useEffect(() => { setOpen(false); }, [context]);
    const parsed = parseValue(text, type, locale);
    const valid = validValue(parsed, type, min);
    useEffect(() => { input.current?.setCustomValidity(valid || !required && !text ? '' : t('dayPlanning.picker.invalid')); }, [valid, required, text, t]);
    const show = (event) => {
        if (disabled || input.current?.matches(':disabled')) return;
        returnFocus.current = event.currentTarget;
        openedContext.current = context;
        setOpen(true);
    };
    const choose = (next) => {
        setOpen(false);
        if (openedContext.current !== context || disabled || input.current?.matches(':disabled') || !validValue(next, type, min)) return;
        setText(displayValue(next, type, locale));
        onChange(next);
    };
    const edit = (event) => {
        if (disabled || input.current?.matches(':disabled')) return;
        const next = event.target.value;
        const canonical = parseValue(next, type, locale);
        if (validValue(canonical, type, min)) { setText(displayValue(canonical, type, locale)); onChange(canonical); }
        else { setText(next); if (!required && !next) onChange(''); }
    };
    return <div className={`day-field day-picker-field${compact ? ' day-picker-field--compact' : ''}`}>
        <label htmlFor={id} className={compact ? 'sr-only' : ''}>{label}</label>
        <div className="day-picker-input"><input id={id} ref={input} type="text" aria-label={label} aria-haspopup="dialog" aria-expanded={open} aria-invalid={!valid && !!text} autoComplete="off" required={required} disabled={disabled} value={text} placeholder={t(`dayPlanning.picker.${type === 'time' ? 'timePlaceholder' : type === 'date' ? 'datePlaceholder' : 'dateTimePlaceholder'}`)} onClick={show} onKeyDown={(event) => { if (event.key === 'Enter' || event.key === 'ArrowDown' && event.altKey) { event.preventDefault(); show(event); } }} onChange={edit} /><button type="button" disabled={disabled} aria-label={t('dayPlanning.picker.open', { field: label })} onClick={show}><Icon name={type === 'time' ? 'schedule' : 'calendar'} size={22} /></button></div>
        {open && <PickerDialog label={label} type={type} value={value} min={min} timeZone={timeZone} returnFocus={returnFocus.current} onDismiss={() => setOpen(false)} onChoose={choose} />}
    </div>;
}

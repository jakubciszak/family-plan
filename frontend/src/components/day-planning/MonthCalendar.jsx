import React, { useEffect, useLayoutEffect, useMemo, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Icon } from '../md3';
import { addDays, capitalize, dateInZone, displayDate, displayTime, personName } from '../../services/dayPlanningTime';
import { layoutWeek, monthWeeks } from '../../services/weekCalendarLayout';

/** Event line height and the gap between lines; they match .day-month-event and .day-month-events in day-planning.css. */
const LINE = 20;
const GAP = 2;

export default function MonthCalendar({ events, busy, date, people, zone, onOpen, onShowDay, onShowMore }) {
    const { t, i18n } = useTranslation();
    const { start, weeks } = monthWeeks(date);
    const month = date.slice(0, 7);
    const [now, setNow] = useState(() => Date.now());
    const today = dateInZone(now, zone);
    const body = useRef(null);
    const [capacity, setCapacity] = useState(3);
    useEffect(() => {
        const timer = window.setInterval(() => setNow(Date.now()), 60000);
        return () => window.clearInterval(timer);
    }, []);
    const rows = useMemo(() => {
        const items = [
            ...events.map((event) => ({ ...event, key: `${event.id}-${event.occurrenceKey}` })),
            ...busy.map((item, index) => ({ kind: 'busy', key: `busy-${index}`, personId: item.personId, start: item.start, end: item.end })),
        ];
        return Array.from({ length: weeks }, (_, week) => layoutWeek(items, addDays(start, week * 7), zone).map((day) => ({ date: day.date, segments: [...day.allDay, ...day.timed] })));
    }, [events, busy, start, weeks, zone]);
    useLayoutEffect(() => {
        const element = body.current;
        if (!element) return undefined;
        const measure = () => {
            const list = element.querySelector('.day-month-events');
            if (list) setCapacity(Math.max(1, Math.floor((list.clientHeight + GAP) / (LINE + GAP))));
        };
        measure();
        const observer = typeof ResizeObserver === 'function' ? new ResizeObserver(measure) : null;
        observer?.observe(element);
        return () => observer?.disconnect();
    }, [weeks]);
    const long = (value) => capitalize(displayDate(value, i18n.language, { weekday: 'long', year: 'numeric' }));
    const names = (item) => people.filter((person) => item.kind === 'busy' ? person.id === item.personId : item.participantIds?.map(String).includes(person.id)).map(personName).join(', ');
    const title = (item) => item.kind === 'busy' ? t('dayPlanning.busy') : item.title;
    const time = (item) => item.allDay ? t('dayPlanning.allDay') : `${displayTime(item.start, i18n.language, zone)}–${displayTime(item.end, i18n.language, zone)}`;
    const entry = ({ item, continuesBefore, continuesAfter }) => {
        const who = item.kind === 'busy' || people.length > 1 ? names(item) : '';
        const label = [title(item), time(item), who].filter(Boolean).join(', ');
        const className = `day-month-event${item.allDay || continuesBefore || continuesAfter ? ' day-month-event--span' : ''}${item.kind === 'busy' ? ' day-event--busy' : ''}${item.participation === 'DECLINED' ? ' day-event--declined' : ''}`;
        const style = { '--day-event-color': item.kind === 'busy' ? 'var(--md-sys-color-outline)' : item.tags?.[0]?.color || 'var(--md-sys-color-primary)' };
        const content = <>
            {continuesBefore && <span aria-hidden="true">‹</span>}
            {!item.allDay && !continuesBefore && <span className="day-month-time">{displayTime(item.start, i18n.language, zone)}</span>}
            {(item.kind === 'busy' || item.visibility === 'PRIVATE') && <Icon name="lock" size={11} />}
            <span className="day-month-title">{item.kind === 'busy' && who ? `${title(item)} · ${who}` : title(item)}</span>
            {continuesAfter && <span aria-hidden="true">›</span>}
        </>;
        return item.kind === 'busy'
            ? <div className={className} style={style} title={label} aria-label={label}>{content}</div>
            : <button type="button" className={className} style={style} title={label} aria-label={label} onClick={() => onOpen(item)}>{content}</button>;
    };
    return <div className="day-month" role="region" aria-label={t('dayPlanning.monthView')} style={{ '--day-month-weeks': weeks }}>
        <div className="day-month-weekdays" aria-hidden="true">{rows[0].map((day) => <span key={day.date}>{displayDate(day.date, i18n.language, { weekday: 'short', day: undefined, month: undefined })}</span>)}</div>
        <div className="day-month-body" ref={body}>
            {rows.map((row) => <div className="day-month-week" key={row[0].date}>{row.map((day) => {
                const shown = day.segments.length > capacity ? day.segments.slice(0, capacity - 1) : day.segments;
                const hidden = day.segments.length - shown.length;
                const count = day.segments.length ? `, ${t('dayPlanning.eventsCount', { count: day.segments.length })}` : '';
                return <div key={day.date} data-date={day.date} className={`day-month-cell${day.date.slice(0, 7) !== month ? ' is-outside' : ''}${day.date === today ? ' is-today' : ''}`}>
                    <button type="button" className="day-month-day" aria-label={`${t('dayPlanning.showDay', { date: long(day.date) })}${count}`} aria-current={day.date === today ? 'date' : undefined} onClick={() => onShowDay(day.date)}>
                        {day.date.endsWith('-01') ? displayDate(day.date, i18n.language, { month: 'short' }) : Number(day.date.slice(8))}
                    </button>
                    <ul className="day-month-events">
                        {shown.map((segment) => <li key={segment.item.key}>{entry(segment)}</li>)}
                        {hidden > 0 && <li><button type="button" className="day-month-more" aria-label={`${t('dayPlanning.moreEvents', { count: hidden })}: ${long(day.date)}`} onClick={() => onShowMore(day.date)}><span className="day-month-more-long">{t('dayPlanning.moreEvents', { count: hidden })}</span><span className="day-month-more-short">+{hidden}</span></button></li>}
                    </ul>
                </div>;
            })}</div>)}
        </div>
    </div>;
}

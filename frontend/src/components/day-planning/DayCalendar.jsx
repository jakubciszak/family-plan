import React from 'react';
import { useTranslation } from 'react-i18next';
import { Icon } from '../md3';
import { addDays, dateInZone, displayDate, displayTime, eventOnDate, minutesOnDate, personName } from '../../services/dayPlanningTime';

const eventKey = (event) => `${event.id || event.personId}-${event.occurrenceKey || event.start}-${event.end}`;
const belongs = (event, personId) => event.kind === 'busy' ? event.personId === personId : event.participantIds?.map(String).includes(personId);
const layout = (events, date, zone) => {
    const timed = events.map((event) => ({ event, start: minutesOnDate(event.start, date, zone), end: minutesOnDate(event.end, date, zone) })).sort((a, b) => a.start - b.start || b.end - a.end);
    let group = [];
    let groupEnd = 0;
    const result = [];
    const flush = () => {
        const columns = [];
        group.forEach((item) => {
            let column = columns.findIndex((end) => end <= item.start);
            if (column < 0) column = columns.length;
            columns[column] = item.end;
            item.column = column;
        });
        result.push(...group.map((item) => ({ ...item, columns: columns.length })));
    };
    timed.forEach((item) => {
        if (group.length && item.start >= groupEnd) { flush(); group = []; groupEnd = 0; }
        group.push(item);
        groupEnd = Math.max(groupEnd, item.end);
    });
    flush();
    return result;
};

export default function DayCalendar({ events, busy, date, mode, people, zone, onOpen }) {
    const { t, i18n } = useTranslation();
    const all = [...events, ...busy.map((item) => ({ ...item, kind: 'busy' }))];
    const columns = mode === 'week'
        ? Array.from({ length: 7 }, (_, index) => { const day = addDays(date, index); return { key: day, date: day, label: displayDate(day, i18n.language, { weekday: 'short', month: 'short' }), items: all.filter((event) => eventOnDate(event, day, zone)) }; })
        : people.map((person) => ({ key: person.id, date, label: personName(person), items: all.filter((event) => belongs(event, person.id) && eventOnDate(event, date, zone)) }));
    const timed = columns.flatMap((column) => column.items.filter((event) => !event.allDay).map((event) => ({ start: minutesOnDate(event.start, column.date, zone), end: minutesOnDate(event.end, column.date, zone) })));
    const firstHour = Math.max(0, Math.floor(Math.min(8 * 60, ...timed.map((item) => item.start)) / 60));
    const lastHour = Math.min(24, Math.ceil(Math.max(20 * 60, ...timed.map((item) => item.end)) / 60));
    const hours = Array.from({ length: lastHour - firstHour + 1 }, (_, index) => index + firstHour);
    const title = (event) => event.kind === 'busy' ? t('dayPlanning.busy') : event.title;
    const time = (event) => {
        if (event.allDay) return t('dayPlanning.allDay');
        const startDay = dateInZone(event.start, zone);
        const endDay = dateInZone(event.end, zone);
        const start = displayTime(event.start, i18n.language, zone);
        const end = displayTime(event.end, i18n.language, zone);
        return startDay === endDay ? `${start}–${end}` : `${displayDate(startDay, i18n.language, { month: 'short' })} ${start} – ${displayDate(endDay, i18n.language, { month: 'short' })} ${end}`;
    };
    const content = (event, compact = false) => <><strong>{event.kind === 'busy' || event.visibility === 'PRIVATE' ? <Icon name="lock" size={14} /> : null}{title(event)}</strong><span>{time(event)}{event.recurring ? ` · ${t('dayPlanning.repeats')}` : ''}</span>{!compact && event.tags?.length > 0 && <small>{event.tags.map((tag) => tag.name).join(' · ')}</small>}{event.participation === 'DECLINED' && <small>{t('dayPlanning.declined')}</small>}</>;
    const empty = !columns.some((column) => column.items.length);
    return <>
        {empty && <div className="day-empty"><Icon name="calendar" size={36} /><h3>{t('dayPlanning.empty')}</h3><p>{t('dayPlanning.emptyHint')}</p></div>}
        {!columns.length && <p className="day-hint">{t('dayPlanning.selectPeople')}</p>}
        {!empty && <>
            <div className="day-calendar-wrap"><div className="day-calendar" style={{ '--day-columns': columns.length }}>
                <div className="day-calendar-corner">{zone.split('/').pop()?.replaceAll('_', ' ')}</div>{columns.map((column) => <div className="day-column-title" key={column.key}>{column.label}</div>)}
                <div className="day-allday-label">{t('dayPlanning.allDay')}</div>{columns.map((column) => <div className="day-allday" key={column.key}>{column.items.filter((event) => event.allDay).map((event) => <button key={eventKey(event)} className="day-all-event" onClick={() => onOpen(event)}>{title(event)}</button>)}</div>)}
                <div className="day-hour-labels" style={{ height: (lastHour - firstHour) * 56 }}>{hours.slice(0, -1).map((hour) => <span key={hour} style={{ top: (hour - firstHour) * 56 }}>{String(hour).padStart(2, '0')}:00</span>)}</div>
                {columns.map((column) => <div className="day-timeline" key={column.key} style={{ height: (lastHour - firstHour) * 56 }}>{layout(column.items.filter((event) => !event.allDay), column.date, zone).map(({ event, start, end, column: index, columns: count }) => {
                    const style = { top: (start / 60 - firstHour) * 56, height: Math.max(28, (end - start) / 60 * 56 - 3), left: `calc(${index * 100 / count}% + 3px)`, width: `calc(${100 / count}% - 6px)`, '--day-event-color': event.tags?.[0]?.color || 'var(--md-sys-color-primary)' };
                    return event.kind === 'busy' ? <div key={eventKey(event)} className="day-event day-event--busy" style={style} aria-label={`${t('dayPlanning.busy')} ${time(event)}`}>{content(event, true)}</div> : <button key={eventKey(event)} className={`day-event${event.participation === 'DECLINED' ? ' day-event--declined' : ''}`} style={style} onClick={() => onOpen(event)} aria-label={`${title(event)}, ${time(event)}`}>{content(event, end - start < 80)}</button>;
                })}</div>)}
            </div></div>
            <div className="day-agenda">{columns.map((column) => <section className="day-agenda-column" key={column.key}><h3>{column.label}</h3>{!column.items.length && <p className="day-hint">{t('dayPlanning.noEvents')}</p>}{[...column.items].sort((a, b) => new Date(a.start) - new Date(b.start)).map((event) => event.kind === 'busy' ? <div key={eventKey(event)} className="day-agenda-event day-event--busy">{content(event)}</div> : <button key={eventKey(event)} className="day-agenda-event" onClick={() => onOpen(event)}>{content(event)}</button>)}</section>)}</div>
        </>}
    </>;
}

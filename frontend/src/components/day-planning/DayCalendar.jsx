import React from 'react';
import { useTranslation } from 'react-i18next';
import { Icon } from '../md3';
import { addDays, dateInZone, displayDate, displayTime, eventOnDate, personName } from '../../services/dayPlanningTime';

const eventKey = (event) => `${event.id || event.personId}-${event.occurrenceKey || event.start}-${event.end}`;
const belongs = (event, personId) => event.kind === 'busy' ? event.personId === personId : event.participantIds?.map(String).includes(personId);

export default function DayCalendar({ events, busy, date, mode, people, zone, onOpen }) {
    const { t, i18n } = useTranslation();
    const all = [...events, ...busy.map((item) => ({ ...item, kind: 'busy' }))];
    const columns = mode === 'week'
        ? Array.from({ length: 7 }, (_, index) => { const day = addDays(date, index); return { key: day, date: day, label: displayDate(day, i18n.language, { weekday: 'short', month: 'short' }), items: all.filter((event) => eventOnDate(event, day, zone)) }; })
        : people.map((person) => ({ key: person.id, date, label: personName(person), items: all.filter((event) => belongs(event, person.id) && eventOnDate(event, date, zone)) }));
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
            <div className="day-agenda">{columns.map((column) => <section className="day-agenda-column" key={column.key}><h3>{column.label}</h3>{!column.items.length && <p className="day-hint">{t('dayPlanning.noEvents')}</p>}{[...column.items].sort((a, b) => new Date(a.start) - new Date(b.start)).map((event) => event.kind === 'busy' ? <div key={eventKey(event)} className="day-agenda-event day-event--busy">{content(event)}</div> : <button key={eventKey(event)} className="day-agenda-event" onClick={() => onOpen(event)} aria-label={`${title(event)}, ${time(event)}`}>{content(event)}</button>)}</section>)}</div>
        </>}
    </>;
}

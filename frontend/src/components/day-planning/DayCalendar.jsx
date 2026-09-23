import React from 'react';
import { useTranslation } from 'react-i18next';
import { Icon } from '../md3';
import { dateInZone, displayDate, displayTime, eventOnDate, personName } from '../../services/dayPlanningTime';

const eventKey = (event) => `${event.id || event.personId}-${event.occurrenceKey || event.start}-${event.end}`;
const belongs = (event, personId) => event.kind === 'busy' ? event.personId === personId : event.participantIds?.map(String).includes(personId);

export default function DayCalendar({ events, busy, date, people, zone, perPerson, filtered, onOpen }) {
    const { t, i18n } = useTranslation();
    const all = [...events, ...busy.map((item) => ({ ...item, kind: 'busy' }))].filter((event) => eventOnDate(event, date, zone)).sort((a, b) => new Date(a.start) - new Date(b.start));
    const columns = people.map((person) => ({ key: person.id, label: personName(person), items: all.filter((event) => belongs(event, person.id)) }));
    const title = (event) => event.kind === 'busy' ? t('dayPlanning.busy') : event.title;
    const time = (event) => {
        if (event.allDay) return t('dayPlanning.allDay');
        const startDay = dateInZone(event.start, zone);
        const endDay = dateInZone(event.end, zone);
        const start = displayTime(event.start, i18n.language, zone);
        const end = displayTime(event.end, i18n.language, zone);
        return startDay === endDay ? `${start}–${end}` : `${displayDate(startDay, i18n.language, { month: 'short' })} ${start} – ${displayDate(endDay, i18n.language, { month: 'short' })} ${end}`;
    };
    const details = (event) => [...(event.tags || []).map((tag) => tag.name), event.recurring && t('dayPlanning.repeats'), event.participation === 'DECLINED' && t('dayPlanning.declined')].filter(Boolean).join(' · ');
    const row = (event) => {
        const content = <><span className="day-agenda-time">{time(event)}</span><span className="day-agenda-body"><strong>{event.kind === 'busy' || event.visibility === 'PRIVATE' ? <Icon name="lock" size={14} /> : null}{title(event)}</strong>{details(event) && <small>{details(event)}</small>}</span></>;
        const style = { '--day-event-color': event.kind === 'busy' ? 'var(--md-sys-color-outline)' : event.tags?.[0]?.color || 'var(--md-sys-color-primary)' };
        const className = `day-agenda-event${event.kind === 'busy' ? ' day-event--busy' : ''}${event.participation === 'DECLINED' ? ' day-event--declined' : ''}`;
        return <li key={eventKey(event)}>{event.kind === 'busy'
            ? <div className={className} style={style}>{content}</div>
            : <button type="button" className={className} style={style} onClick={() => onOpen(event)} aria-label={`${title(event)}, ${time(event)}`}>{content}</button>}</li>;
    };
    if (!columns.length) return <p className="day-empty">{t('dayPlanning.selectPeople')}</p>;
    if (!columns.some((column) => column.items.length)) return <div className="day-empty"><Icon name="calendar" size={32} /><p>{t(filtered ? 'dayPlanning.emptyFiltered' : 'dayPlanning.empty')}</p></div>;
    return <div className={`day-agenda${perPerson ? ' day-agenda--people' : ''}`}>{columns.map((column) => <section className="day-agenda-column" key={column.key}>
        {perPerson && <h3>{column.label}</h3>}
        {column.items.length ? <ul>{column.items.map(row)}</ul> : <p className="day-hint">{t('dayPlanning.noEvents')}</p>}
    </section>)}</div>;
}

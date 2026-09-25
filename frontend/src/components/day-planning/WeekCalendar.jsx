import React, { useEffect, useMemo, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Icon } from '../md3';
import { capitalize, dateInZone, displayDate, displayTime, minutesOnDate, personName } from '../../services/dayPlanningTime';
import { layoutWeek } from '../../services/weekCalendarLayout';

export default function WeekCalendar({ events, busy, date, people, zone, onOpen, onShowDay }) {
    const { t, i18n } = useTranslation();
    const scroller = useRef(null);
    const [now, setNow] = useState(() => Date.now());
    useEffect(() => {
        const timer = window.setInterval(() => setNow(Date.now()), 60000);
        return () => window.clearInterval(timer);
    }, []);
    const days = useMemo(() => layoutWeek([
        ...events.map((event) => ({ ...event, key: `${event.id}-${event.occurrenceKey}` })),
        ...busy.map((item, index) => ({ kind: 'busy', key: `busy-${index}`, personId: item.personId, start: item.start, end: item.end })),
    ], date, zone), [events, busy, date, zone]);
    const today = dateInZone(now, zone);
    useEffect(() => {
        const first = Math.min(8 * 60, ...days.flatMap((day) => day.timed.map((segment) => segment.startMinute)));
        if (scroller.current) scroller.current.scrollTop = Math.max(0, first - 72);
    }, [date, zone]);
    const names = (item) => people.filter((person) => item.kind === 'busy' ? person.id === item.personId : item.participantIds?.map(String).includes(person.id)).map(personName).join(', ');
    const title = (item) => item.kind === 'busy' ? t('dayPlanning.busy') : item.title;
    const time = (item) => item.allDay ? t('dayPlanning.allDay') : `${displayTime(item.start, i18n.language, zone)}–${displayTime(item.end, i18n.language, zone)}`;
    const content = ({ item, continuesBefore, continuesAfter }, compact) => <>
        <strong>{item.kind === 'busy' || item.visibility === 'PRIVATE' ? <Icon name="lock" size={12} /> : null}{continuesBefore && <span aria-label={t('dayPlanning.continuesBefore')}>‹</span>}{title(item)}{continuesAfter && <span aria-label={t('dayPlanning.continuesAfter')}>›</span>}</strong>
        {!compact && <><span>{time(item)}</span>{(item.kind === 'busy' || people.length > 1) && names(item) && <small>{names(item)}</small>}</>}
    </>;
    const render = (segment, allDay = false) => {
        const { item, day, startMinute, displayEndMinute, column, columns, overlapCount } = segment;
        const duration = displayEndMinute - startMinute;
        const label = `${title(item)}, ${time(item)}${names(item) ? `, ${names(item)}` : ''}${overlapCount ? `, ${t('dayPlanning.overlapping')}` : ''}`;
        const className = `day-week-event${allDay ? ' day-week-event--allday' : ''}${item.kind === 'busy' ? ' day-event--busy' : ''}${item.participation === 'DECLINED' ? ' day-event--declined' : ''}${!allDay && duration < 40 ? ' day-week-event--short' : ''}`;
        const style = { '--day-event-color': item.kind === 'busy' ? 'var(--md-sys-color-outline)' : item.tags?.[0]?.color || 'var(--md-sys-color-primary)', ...(!allDay && { top: startMinute, height: duration - 1, left: `calc(${column * 100 / columns}% + 2px)`, width: `calc(${100 / columns}% - 4px)` }) };
        const attributes = { className, style, 'aria-label': label, 'data-overlap-count': overlapCount, title: label };
        return item.kind === 'busy'
            ? <div key={`${item.key}-${day}`} {...attributes}>{content(segment, !allDay && duration < 40)}</div>
            : <button key={`${item.key}-${day}`} type="button" {...attributes} onClick={() => onOpen(item)}>{content(segment, !allDay && duration < 40)}</button>;
    };
    return <>
        <div className="day-week-scroll" ref={scroller} role="region" aria-label={t('dayPlanning.weekView')} tabIndex={0}>
            <div className="day-week-grid">
                <div className="day-week-corner">{zone.split('/').pop()?.replaceAll('_', ' ')}</div>
                {days.map((day) => {
                    const label = <><span>{displayDate(day.date, i18n.language, { weekday: 'short', day: undefined, month: undefined })}</span><strong>{displayDate(day.date, i18n.language, { month: 'short' })}</strong></>;
                    return <div className={`day-week-header${day.date === today ? ' is-today' : ''}`} key={day.date} data-date={day.date}>
                        {onShowDay ? <button type="button" className="day-week-day" aria-label={t('dayPlanning.showDay', { date: capitalize(displayDate(day.date, i18n.language, { weekday: 'long', year: 'numeric' })) })} aria-current={day.date === today ? 'date' : undefined} onClick={() => onShowDay(day.date)}>{label}</button> : label}
                    </div>;
                })}
                <div className="day-week-allday-label">{t('dayPlanning.allDay')}</div>
                {days.map((day) => <div key={day.date} className="day-week-allday" data-date={day.date}>{day.allDay.map((segment) => render(segment, true))}</div>)}
                <div className="day-week-hours">{Array.from({ length: 24 }, (_, hour) => <span key={hour} style={{ top: hour * 60 }}>{String(hour).padStart(2, '0')}:00</span>)}</div>
                {days.map((day) => <div className="day-week-timeline" key={day.date} data-date={day.date}>
                    {day.timed.map((segment) => render(segment))}
                    {day.date === today && <div className="day-week-now" style={{ top: minutesOnDate(now, today, zone) }} aria-hidden="true" />}
                </div>)}
            </div>
        </div>
    </>;
}

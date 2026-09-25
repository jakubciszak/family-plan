import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button, Dialog, Fab, Icon, IconButton } from '../components/md3';
import DayCalendar from '../components/day-planning/DayCalendar';
import DateTimePickerField from '../components/day-planning/DateTimePickerField';
import MonthCalendar from '../components/day-planning/MonthCalendar';
import WeekCalendar from '../components/day-planning/WeekCalendar';
import DayEventDetail from '../components/day-planning/DayEventDetail';
import DayEventEditor from '../components/day-planning/DayEventEditor';
import DayTagManager from '../components/day-planning/DayTagManager';
import service from '../services/dayPlanningService';
import teamService from '../services/teamService';
import { addDays, addMonths, browserTimeZone, capitalize, dateInZone, displayDate, displayTime, peopleOf, personName, weekStart, zonedIso } from '../services/dayPlanningTime';
import { monthWeeks } from '../services/weekCalendarLayout';
import '../styles/day-planning.css';

const durations = [15, 30, 45, 60, 90, 120, 180, 240, 360, 480];
const periods = ['day', 'week', 'month'];
const byName = (a, b) => a.name.localeCompare(b.name);
const loadTags = async (teams) => {
    const catalogs = await Promise.all([service.tags(''), ...teams.map((team) => service.tags(team.id))]);
    return [...new Map(catalogs.flatMap((data) => data.tags || []).map((tag) => [tag.id, tag])).values()].sort(byName);
};

export default function DayPlanning({ user, onFullscreenChange }) {
    const { t, i18n } = useTranslation();
    const selfId = String(user.id);
    const [zone, setZone] = useState(browserTimeZone);
    const [date, setDate] = useState(() => dateInZone(new Date(), browserTimeZone()));
    const [view, setView] = useState('mine');
    const [period, setPeriod] = useState('day');
    const [teams, setTeams] = useState([]);
    const [teamsReady, setTeamsReady] = useState(false);
    const [chosenTeam, setChosenTeam] = useState('');
    const [rosters, setRosters] = useState({});
    const [picked, setPicked] = useState(null);
    const [tags, setTags] = useState([]);
    const [tagIds, setTagIds] = useState([]);
    const [calendar, setCalendar] = useState({ events: [], busy: [] });
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');
    const [teamError, setTeamError] = useState('');
    const [catalogError, setCatalogError] = useState('');
    const [refresh, setRefresh] = useState(0);
    const [detail, setDetail] = useState(null);
    const [detailScope, setDetailScope] = useState('occurrence');
    const [actionBusy, setActionBusy] = useState(false);
    const [detailError, setDetailError] = useState('');
    const [organizer, setOrganizer] = useState('');
    const [participationConfirmation, setParticipationConfirmation] = useState(null);
    const [editing, setEditing] = useState(null);
    const [showTags, setShowTags] = useState(false);
    const [notice, setNotice] = useState('');
    const [planner, setPlanner] = useState({ days: 7, duration: 60, windowStart: '08:00', windowEnd: '20:00' });
    const [suggestions, setSuggestions] = useState(null);
    const [planning, setPlanning] = useState(false);
    const [planningError, setPlanningError] = useState('');
    const planningGeneration = useRef(0);
    const detailGeneration = useRef(0);
    const [teamsVersion, setTeamsVersion] = useState(0);
    const [fullscreen, setFullscreen] = useState(false);
    const [dayList, setDayList] = useState(null);
    const wantsFullscreen = useRef(false);
    const ownsNativeFullscreen = useRef(false);
    const reload = () => setRefresh((value) => value + 1);
    const month = period === 'month' ? monthWeeks(date) : null;
    const fromDate = period === 'week' ? weekStart(date) : month ? month.start : date;
    const toDate = period === 'week' ? addDays(fromDate, 7) : month ? month.end : addDays(date, 1);
    const self = useMemo(() => ({ ...user, id: selfId, name: t('dayPlanning.you') }), [user, selfId, t]);
    const teamId = teams.some((team) => team.id === chosenTeam) ? chosenTeam : (teams.find((team) => (rosters[team.id] || []).some((person) => person.id !== selfId)) || teams[0])?.id || '';
    const teamPeople = useMemo(() => [self, ...(rosters[teamId] || []).filter((person) => person.id !== selfId)], [self, rosters, teamId, selfId]);
    const directory = useMemo(() => [self, ...Object.values(rosters).flat()], [self, rosters]);
    const selectedKey = (picked?.teamId === teamId ? picked.ids : teamPeople.map((person) => person.id)).filter((id) => teamPeople.some((person) => person.id === id)).join(',');
    const selected = useMemo(() => selectedKey ? selectedKey.split(',') : [], [selectedKey]);
    const shownPeople = view === 'mine' ? [self] : teamPeople.filter((person) => selected.includes(person.id));
    const teamQuery = view === 'team' ? `${teamId}|${selectedKey}` : '';
    const nameOf = (id) => personName(directory.find((person) => person.id === String(id))) || t('dayPlanning.teamMember');
    useEffect(() => {
        let active = true;
        teamService.getTeams()
            .then((data) => { if (active) { setTeams(data.teams || []); setTeamError(''); } })
            .catch(() => { if (active) setTeamError(t('dayPlanning.teamError')); })
            .finally(() => { if (active) setTeamsReady(true); });
        return () => { active = false; };
    }, [teamsVersion, t]);
    useEffect(() => { if (!teams.length) setView('mine'); }, [teams]);
    useEffect(() => {
        if (!teamsReady) return undefined;
        let active = true;
        setCatalogError('');
        Promise.all([loadTags(teams), Promise.all(teams.map((team) => teamService.getTeamMembers(team.id)))]).then(([nextTags, rosterData]) => {
            if (!active) return;
            setTags(nextTags);
            setTagIds((current) => current.filter((id) => nextTags.some((tag) => tag.id === id)));
            setRosters(Object.fromEntries(teams.map((team, index) => [team.id, peopleOf(rosterData[index])])));
        }).catch(() => { if (active) { setRosters({}); setCatalogError(t('dayPlanning.catalogError')); } });
        return () => { active = false; };
    }, [teams, teamsReady, t]);
    const reloadTags = useCallback(async () => {
        const next = await loadTags(teams);
        setTags(next);
        setTagIds((current) => current.filter((id) => next.some((tag) => tag.id === id)));
        reload();
    }, [teams]);
    useEffect(() => {
        let active = true;
        setCalendar({ events: [], busy: [] });
        setError('');
        if (document.hidden || view === 'planner' || (view === 'team' && (!teamId || !selected.length))) { setLoading(false); return undefined; }
        setLoading(true);
        let query;
        try { query = { from: zonedIso(`${fromDate}T00:00`, zone), to: zonedIso(`${toDate}T00:00`, zone), tagIds, ...(view === 'team' ? { teamId, personIds: selected } : {}) }; }
        catch { setError(t('dayPlanning.invalidTime')); setLoading(false); return undefined; }
        service.calendar(query).then((data) => { if (active) setCalendar(data); }).catch((failure) => { if (active) setError(failure.response?.data?.message || t('dayPlanning.loadError')); }).finally(() => { if (active) setLoading(false); });
        return () => { active = false; };
    }, [fromDate, toDate, view, teamQuery, tagIds, zone, refresh, t]);
    useEffect(() => {
        setSuggestions(null);
        setPlanningError('');
        setPlanning(false);
        planningGeneration.current++;
    }, [date, teamId, selectedKey, planner, zone]);
    useEffect(() => {
        const invalidate = () => {
            detailGeneration.current++;
            planningGeneration.current++;
            setDetail(null);
            setDayList(null);
            setCalendar({ events: [], busy: [] });
            setSuggestions(null);
            setPlanning(false);
            setActionBusy(false);
            reload();
            if (!document.hidden) setTeamsVersion((value) => value + 1);
        };
        window.addEventListener('focus', invalidate);
        window.addEventListener('day-planning:changed', invalidate);
        document.addEventListener('visibilitychange', invalidate);
        return () => {
            window.removeEventListener('focus', invalidate);
            window.removeEventListener('day-planning:changed', invalidate);
            document.removeEventListener('visibilitychange', invalidate);
        };
    }, []);
    const enterFullscreen = () => {
        wantsFullscreen.current = true;
        setFullscreen(true);
        const root = document.documentElement;
        if (document.fullscreenElement || !root.requestFullscreen) return;
        root.requestFullscreen({ navigationUI: 'hide' }).then(() => {
            if (wantsFullscreen.current) ownsNativeFullscreen.current = true;
            else document.exitFullscreen().catch(() => {});
        }).catch(() => {});
    };
    const leaveFullscreen = useCallback(() => {
        wantsFullscreen.current = false;
        setFullscreen(false);
        if (ownsNativeFullscreen.current && document.fullscreenElement) document.exitFullscreen().catch(() => {});
        ownsNativeFullscreen.current = false;
    }, []);
    useEffect(() => {
        if (!fullscreen) return undefined;
        const root = document.documentElement;
        const onChange = () => { if (!document.fullscreenElement && ownsNativeFullscreen.current) leaveFullscreen(); };
        const onKey = (event) => { if (event.key === 'Escape' && !event.defaultPrevented && !document.querySelector('.md-scrim, dialog[open]')) leaveFullscreen(); };
        root.classList.add('day-fullscreen-open');
        document.addEventListener('fullscreenchange', onChange);
        document.addEventListener('keydown', onKey);
        return () => {
            root.classList.remove('day-fullscreen-open');
            document.removeEventListener('fullscreenchange', onChange);
            document.removeEventListener('keydown', onKey);
        };
    }, [fullscreen, leaveFullscreen]);
    useEffect(() => () => { if (ownsNativeFullscreen.current && document.fullscreenElement) document.exitFullscreen().catch(() => {}); }, []);
    useEffect(() => {
        onFullscreenChange?.(fullscreen);
        return () => onFullscreenChange?.(false);
    }, [fullscreen, onFullscreenChange]);
    const closeDayList = useCallback(() => setDayList(null), []);
    const shift = (direction) => setDate(period === 'month' ? addMonths(date, direction) : addDays(date, period === 'week' ? 7 * direction : direction));
    const showDay = (value) => { setDayList(null); setDate(value); setPeriod('day'); };
    const togglePerson = (id) => { if (view === 'planner' && id === selfId) return; setPicked({ teamId, ids: selected.includes(id) ? selected.filter((value) => value !== id) : [...selected, id] }); };
    const startCreate = (slot) => { setNotice(''); setEditing({ seed: { date, teamId: view !== 'mine' ? teamId : '', personIds: slot ? [...new Set([selfId, ...selected])] : [selfId], ...slot } }); };
    const showDetail = async (item) => {
        const generation = ++detailGeneration.current;
        setDetail(null);
        setDetailError('');
        setParticipationConfirmation(null);
        setOrganizer('');
        setActionBusy(true);
        try {
            const data = await service.occurrence(item.id, item.occurrenceKey);
            if (generation !== detailGeneration.current) return;
            setDetail(data);
            setDetailScope('occurrence');
            if (data.teamId && !data.participantIds?.includes(data.ownerId) && !directory.some((person) => person.id === data.ownerId)) {
                teamService.getTeamMembers(data.teamId)
                    .then((roster) => { if (generation === detailGeneration.current) setOrganizer(personName(peopleOf(roster).find((person) => person.id === data.ownerId)) || ''); })
                    .catch(() => {});
            }
        }
        catch (failure) { if (generation === detailGeneration.current) { setError(failure.response?.status === 404 ? t('dayPlanning.noAccess') : t('dayPlanning.loadError')); reload(); } }
        finally { if (generation === detailGeneration.current) setActionBusy(false); }
    };
    const edit = async (item, scope) => {
        setActionBusy(true);
        setDetailError('');
        try {
            const definition = await service.definition(item.id);
            const current = scope === 'occurrence' && item.recurring ? await service.occurrence(item.id, item.occurrenceKey) : definition;
            setEditing({ event: current, occurrenceKey: scope === 'occurrence' && item.recurring ? item.occurrenceKey : undefined });
            setDetail(null);
        } catch (failure) { setDetailError(failure.response?.status === 404 ? t('dayPlanning.noAccess') : t('dayPlanning.loadError')); }
        finally { setActionBusy(false); }
    };
    const deleteEvent = async () => {
        if (!window.confirm(t(detail.recurring && detailScope === 'occurrence' ? 'dayPlanning.confirmDeleteOccurrence' : 'dayPlanning.confirmDeleteSeries', { title: detail.title }))) return;
        setActionBusy(true);
        setDetailError('');
        try {
            if (detail.recurring && detailScope === 'occurrence') await service.exception(detail.id, detail.occurrenceKey, { cancelled: true }, detail.version);
            else await service.remove(detail.id, detail.version);
            setDetail(null);
            setNotice(t('dayPlanning.deleted'));
            reload();
        } catch (failure) { setDetailError(failure.response?.status === 412 ? t('dayPlanning.staleDetail') : failure.response?.data?.message || t('dayPlanning.saveError')); }
        finally { setActionBusy(false); }
    };
    const participate = async (confirmed = null) => {
        setActionBusy(true);
        setDetailError('');
        try {
            const status = confirmed?.status || (detail.participation === 'DECLINED' ? 'INCLUDED' : 'DECLINED');
            const occurrenceKey = confirmed ? confirmed.occurrenceKey : detail.recurring && detailScope === 'occurrence' ? detail.occurrenceKey : null;
            await service.participate(detail.id, status, occurrenceKey, confirmed ? { conflictConfirmation: confirmed.token } : {});
            await showDetail(detail);
            reload();
        } catch (failure) {
            const data = failure.response?.data;
            if (data?.code === 'planning_conflict') setParticipationConfirmation({ token: data.confirmationToken, status: 'INCLUDED', occurrenceKey: detail.recurring && detailScope === 'occurrence' ? detail.occurrenceKey : null });
            else setDetailError(data?.message || t('dayPlanning.saveError'));
        }
        finally { setActionBusy(false); }
    };
    const findSlots = async (event) => {
        event.preventDefault();
        const generation = ++planningGeneration.current;
        setPlanning(true);
        setPlanningError('');
        setSuggestions(null);
        try {
            const data = await service.suggestions({ teamId: teamId || null, personIds: [...new Set([selfId, ...selected])], from: zonedIso(`${date}T00:00`, zone), to: zonedIso(`${addDays(date, Number(planner.days))}T00:00`, zone), durationMinutes: Number(planner.duration), windowStart: planner.windowStart, windowEnd: planner.windowEnd, timeZone: zone });
            if (generation === planningGeneration.current) setSuggestions(data);
        } catch (failure) { if (generation === planningGeneration.current) setPlanningError(failure.response?.data?.message || t('dayPlanning.planningError')); }
        finally { if (generation === planningGeneration.current) setPlanning(false); }
    };
    const rangeLabel = () => {
        if (period === 'day') return capitalize(displayDate(date, i18n.language, { weekday: 'long', year: 'numeric' }));
        if (period === 'month') return capitalize(new Intl.DateTimeFormat(i18n.language, { month: 'long', year: 'numeric', timeZone: 'UTC' }).format(new Date(`${date}T12:00:00Z`)));
        const format = new Intl.DateTimeFormat(i18n.language, { day: 'numeric', month: 'long', year: 'numeric', timeZone: 'UTC' });
        const [first, last] = [fromDate, addDays(toDate, -1)].map((value) => new Date(`${value}T12:00:00Z`));
        return format.formatRange ? format.formatRange(first, last) : `${format.format(first)} – ${format.format(last)}`;
    };
    const durationLabel = (minutes) => minutes < 60 ? t('dayPlanning.durationMinutes', { count: minutes }) : t('dayPlanning.durationHours', { value: new Intl.NumberFormat(i18n.language, { maximumFractionDigits: 1 }).format(minutes / 60) });
    if (editing) return <div className={`day-planning${fullscreen ? ' is-fullscreen is-editing' : ''}`}><DayEventEditor key={`${editing.event?.id || 'new'}-${editing.occurrenceKey || 'series'}-${editing.event?.version || 0}`} {...editing} zone={zone} user={user} teams={teams} onTagCreated={(tag) => setTags((current) => [...current.filter((item) => item.id !== tag.id), tag].sort(byName))} onClose={() => { setEditing(null); reloadTags().catch(() => setCatalogError(t('dayPlanning.catalogError'))); }} onReload={async () => {
        try { const current = editing.occurrenceKey ? await service.occurrence(editing.event.id, editing.occurrenceKey) : await service.definition(editing.event.id); setEditing({ ...editing, event: current }); }
        catch { setEditing(null); setError(t('dayPlanning.noAccess')); reload(); }
    }} onSaved={() => { setEditing(null); setNotice(t('dayPlanning.saved')); reload(); }} /></div>;
    const views = teams.length ? [['mine', 'myPlan'], ['team', 'teamPlan'], ['planner', 'findTime']] : [];
    const zones = [...new Set([browserTimeZone(), 'Europe/Warsaw', 'Europe/London', 'America/New_York', 'UTC'])];
    const tabs = views.length > 0 && !fullscreen;
    const calendarProps = { events: calendar.events || [], busy: calendar.busy || [], people: shownPeople, zone, onOpen: showDetail };
    const calendarView = () => {
        if (loading || actionBusy && !detail) return <p role="status" className="day-loading">{t('common.loading')}</p>;
        if (error) return <div role="alert" className="day-alert">{error}<Button type="button" variant="text" onClick={reload}>{t('dayPlanning.retry')}</Button></div>;
        if (view === 'team' && !teamId) return <p className="day-empty">{t('dayPlanning.chooseTeam')}</p>;
        if (period === 'month') return <MonthCalendar {...calendarProps} date={date} onShowDay={showDay} onShowMore={setDayList} />;
        if (period === 'week') return <WeekCalendar {...calendarProps} date={fromDate} onShowDay={showDay} />;
        return <DayCalendar {...calendarProps} date={fromDate} perPerson={view === 'team'} filtered={tagIds.length > 0} />;
    };
    return <section className={`day-planning${fullscreen ? ' is-fullscreen' : ''}`} aria-label={fullscreen ? t('dayPlanning.title') : undefined}>
        {!fullscreen && <header className="day-head"><h2>{t('dayPlanning.title')}</h2><Button type="button" icon="add" onClick={() => startCreate()}>{t('dayPlanning.newEvent')}</Button></header>}
        {tabs && <div className="day-tabs" role="tablist" aria-label={t('dayPlanning.views')}>{views.map(([value, label], index) => <button key={value} type="button" role="tab" id={`day-tab-${value}`} aria-selected={view === value} aria-controls="day-content" tabIndex={view === value ? 0 : -1} onKeyDown={(event) => {
            const next = event.key === 'ArrowRight' ? (index + 1) % views.length : event.key === 'ArrowLeft' ? (index + views.length - 1) % views.length : event.key === 'Home' ? 0 : event.key === 'End' ? views.length - 1 : null;
            if (next !== null) { event.preventDefault(); setView(views[next][0]); document.getElementById(`day-tab-${views[next][0]}`)?.focus(); }
        }} onClick={() => setView(value)}>{t(`dayPlanning.${label}`)}</button>)}</div>}
        {notice && <div role="status" className="day-success">{notice}</div>}
        {teamError && <div role="alert" className="day-alert">{teamError}<Button type="button" variant="text" onClick={() => setTeamsVersion((value) => value + 1)}>{t('dayPlanning.retry')}</Button></div>}
        {catalogError && <div role="alert" className="day-alert">{catalogError}<Button type="button" variant="text" onClick={() => setTeamsVersion((value) => value + 1)}>{t('dayPlanning.retry')}</Button></div>}
        <div id="day-content" role={tabs ? 'tabpanel' : undefined} aria-labelledby={tabs ? `day-tab-${view}` : undefined}>
            {view !== 'planner' && <div className="day-toolbar">
                <div className="day-nav">
                    <Button type="button" variant="outlined" onClick={() => setDate(dateInZone(new Date(), zone))}>{t('dayPlanning.today')}</Button>
                    <IconButton icon="chevronLeft" label={t('dayPlanning.previous')} onClick={() => shift(-1)} />
                    <IconButton icon="chevronRight" label={t('dayPlanning.next')} onClick={() => shift(1)} />
                    <DateTimePickerField label={t('dayPlanning.date')} required value={date} onChange={setDate} timeZone={zone} buttonLabel={rangeLabel()} />
                </div>
                <div className="day-toolbar-end">
                    {fullscreen && views.length > 0 && <select className="day-team-select" aria-label={t('dayPlanning.views')} value={view} onChange={(e) => setView(e.target.value)}>{views.filter(([value]) => value !== 'planner').map(([value, label]) => <option key={value} value={value}>{t(`dayPlanning.${label}`)}</option>)}</select>}
                    <div className="day-segmented" role="group" aria-label={t('dayPlanning.calendarView')}>{periods.map((value) => <button key={value} type="button" aria-pressed={period === value} onClick={() => setPeriod(value)}>{t(`dayPlanning.${value}`)}</button>)}</div>
                    <IconButton className="day-fullscreen-toggle" icon={fullscreen ? 'fullscreenExit' : 'fullscreen'} label={t(fullscreen ? 'dayPlanning.exitFullscreen' : 'dayPlanning.fullscreen')} onClick={fullscreen ? leaveFullscreen : enterFullscreen} />
                </div>
            </div>}
            {view !== 'mine' && <div className="day-people" role="group" aria-label={t('dayPlanning.people')}>
                {teams.length > 1 && <select className="day-team-select" aria-label={t('dayPlanning.team')} value={teamId} onChange={(e) => setChosenTeam(e.target.value)}>{teams.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</select>}
                {teamPeople.map((person) => <label className="day-choice" key={person.id}><input type="checkbox" checked={view === 'planner' && person.id === selfId || selected.includes(person.id)} disabled={view === 'planner' && person.id === selfId} onChange={() => togglePerson(person.id)} />{personName(person)}</label>)}
                {view === 'planner' && <p className="day-hint">{t('dayPlanning.authorRequired')}</p>}
            </div>}
            {view !== 'planner' ? <>
                {tags.length > 0 && <div className="day-filter" role="group" aria-label={t('dayPlanning.tags')}>
                    {tags.map((tag) => <button key={tag.id} type="button" aria-pressed={tagIds.includes(tag.id)} className={`day-chip${tagIds.includes(tag.id) ? ' is-selected' : ''}`} onClick={() => setTagIds((current) => current.includes(tag.id) ? current.filter((id) => id !== tag.id) : [...current, tag.id])}><span className="day-dot" style={{ background: tag.color }} />{tag.name}</button>)}
                    {tagIds.length > 0 && <Button type="button" variant="text" onClick={() => setTagIds([])}>{t('dayPlanning.clearFilter')}</Button>}
                    <IconButton icon="edit" label={t('dayPlanning.manageTags')} onClick={() => setShowTags(true)} />
                </div>}
                {tagIds.length > 0 && <p className="day-hint day-filter-hint">{t('dayPlanning.filterHint')}</p>}
                <div className="day-calendar-area">{calendarView()}</div>
                {calendar.coverage?.complete === false && <p role="alert" className="day-alert">{t('dayPlanning.incomplete')}</p>}
            </> : <>
                <form className="day-planner" onSubmit={findSlots}><fieldset disabled={planning} className="day-fields">
                    <div className="day-row">
                        <DateTimePickerField label={t('dayPlanning.searchFrom')} required value={date} onChange={setDate} timeZone={zone} />
                        <label className="day-field">{t('dayPlanning.searchDays')}<select aria-label={t('dayPlanning.searchDays')} value={planner.days} onChange={(e) => setPlanner({ ...planner, days: Number(e.target.value) })}>{[1, 3, 7, 14, 30].map((days) => <option key={days} value={days}>{t('dayPlanning.daysCount', { count: days })}</option>)}</select></label>
                        <label className="day-field">{t('dayPlanning.duration')}<select aria-label={t('dayPlanning.duration')} value={planner.duration} onChange={(e) => setPlanner({ ...planner, duration: Number(e.target.value) })}>{durations.map((minutes) => <option key={minutes} value={minutes}>{durationLabel(minutes)}</option>)}</select></label>
                        <DateTimePickerField label={t('dayPlanning.windowStart')} type="time" required value={planner.windowStart} onChange={(value) => setPlanner({ ...planner, windowStart: value })} />
                        <DateTimePickerField label={t('dayPlanning.windowEnd')} type="time" required value={planner.windowEnd} onChange={(value) => setPlanner({ ...planner, windowEnd: value })} />
                    </div>
                    <div><Button type="submit" disabled={!!catalogError || planner.windowEnd <= planner.windowStart}>{t(planning ? 'dayPlanning.searching' : 'dayPlanning.search')}</Button></div>
                </fieldset></form>
                {planningError && <p role="alert" className="day-alert">{planningError}</p>}
                {suggestions && <div className="day-suggestions" aria-live="polite"><h3>{t('dayPlanning.suggestions')}</h3>{suggestions.coverage?.complete === false ? <p role="alert">{t('dayPlanning.incomplete')}</p> : suggestions.slots.length ? suggestions.slots.map((slot) => <button type="button" className="day-slot" key={slot.start} onClick={() => startCreate(slot)}><span><strong>{capitalize(displayDate(dateInZone(slot.start, zone), i18n.language, { weekday: 'long' }))}</strong><span>{displayTime(slot.start, i18n.language, zone)}–{displayTime(slot.end, i18n.language, zone)}</span></span><span>{t('dayPlanning.useSlot')} <Icon name="chevronRight" size={18} /></span></button>) : <p>{t('dayPlanning.noSlots')}</p>}</div>}
            </>}
        </div>
        {!fullscreen && <footer className="day-footer">
            <label className="day-zone"><Icon name="language" size={18} /><select aria-label={t('dayPlanning.displayZone')} value={zone} onChange={(e) => setZone(e.target.value)}>{zones.map((value) => <option key={value}>{value}</option>)}</select></label>
            {view === 'team' && <p className="day-privacy-note"><Icon name="lock" size={16} />{t('dayPlanning.privacyNote')}</p>}
        </footer>}
        {fullscreen && <Fab className="day-fab" icon="add" label={t('dayPlanning.newEvent')} extended onClick={() => startCreate()} />}
        {dayList && <Dialog open onClose={closeDayList} headline={capitalize(displayDate(dayList, i18n.language, { weekday: 'long', year: 'numeric' }))} actions={<><Button type="button" variant="text" onClick={() => showDay(dayList)}>{t('dayPlanning.openDay')}</Button><Button type="button" variant="text" onClick={closeDayList}>{t('dayPlanning.close')}</Button></>}>
            <div className="day-planning day-day-list"><DayCalendar {...calendarProps} date={dayList} perPerson={view === 'team'} filtered={tagIds.length > 0} onOpen={(item) => { setDayList(null); showDetail(item); }} /></div>
        </Dialog>}
        {detail && <DayEventDetail detail={detail} zone={zone} nameOf={nameOf} organizer={organizer} scope={detailScope} onScope={(value) => { setDetailScope(value); setParticipationConfirmation(null); }} busy={actionBusy} error={detailError} confirmation={participationConfirmation} onEdit={() => edit(detail, detailScope)} onDelete={deleteEvent} onParticipate={participate} onDismissConfirmation={() => setParticipationConfirmation(null)} onReload={() => showDetail(detail)} onClose={() => setDetail(null)} />}
        {showTags && <DayTagManager tags={tags} teams={teams} onChanged={reloadTags} onClose={() => setShowTags(false)} />}
    </section>;
}

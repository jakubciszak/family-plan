import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button, Dialog, Icon, IconButton } from '../components/md3';
import DayCalendar from '../components/day-planning/DayCalendar';
import WeekCalendar from '../components/day-planning/WeekCalendar';
import DayEventEditor from '../components/day-planning/DayEventEditor';
import DayTagManager from '../components/day-planning/DayTagManager';
import service from '../services/dayPlanningService';
import teamService from '../services/teamService';
import { addDays, browserTimeZone, dateInZone, displayDate, displayTime, peopleOf, personName, weekStart, zonedIso } from '../services/dayPlanningTime';
import '../styles/day-planning.css';

export default function DayPlanning({ user }) {
    const { t, i18n } = useTranslation();
    const selfId = String(user.id);
    const views = [['mine', 'myPlan'], ['team', 'teamPlan'], ['planner', 'findTime']];
    const [zone, setZone] = useState(browserTimeZone);
    const [date, setDate] = useState(() => dateInZone(new Date(), browserTimeZone()));
    const [view, setView] = useState('mine');
    const [period, setPeriod] = useState('day');
    const [calendarView, setCalendarView] = useState('list');
    const [teams, setTeams] = useState([]);
    const [teamId, setTeamId] = useState('');
    const [members, setMembers] = useState([]);
    const [selected, setSelected] = useState([selfId]);
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
    const reload = () => setRefresh((value) => value + 1);
    const isWeek = view !== 'planner' && (calendarView === 'week' || view === 'mine' && period === 'week');
    const fromDate = isWeek ? weekStart(date) : date;
    const toDate = addDays(fromDate, isWeek ? 7 : 1);
    const people = useMemo(() => [{ ...user, id: selfId, name: `${personName(user)} · ${t('dayPlanning.you')}` }, ...members.filter((person) => person.id !== selfId)], [user, selfId, members, t]);
    const shownPeople = view === 'mine' ? people.filter((person) => person.id === selfId) : people.filter((person) => selected.includes(person.id));
    const team = teams.find((item) => item.id === teamId);
    useEffect(() => {
        let active = true;
        teamService.getTeams().then((data) => { if (active) { setTeams(data.teams || []); setTeamError(''); } }).catch(() => { if (active) setTeamError(t('dayPlanning.teamError')); });
        return () => { active = false; };
    }, [teamsVersion, t]);
    useEffect(() => {
        let active = true;
        setMembers([]);
        setSelected([selfId]);
        setTags([]);
        setTagIds([]);
        setCatalogError('');
        Promise.all([service.tags(teamId), teamId ? teamService.getTeamMembers(teamId) : Promise.resolve({ members: [] })]).then(([tagData, memberData]) => {
            if (!active) return;
            const nextMembers = peopleOf(memberData);
            setTags(tagData.tags || []);
            setMembers(nextMembers);
            setSelected([...new Set([selfId, ...nextMembers.map((person) => person.id)])]);
        }).catch(() => { if (active) setCatalogError(t('dayPlanning.catalogError')); });
        return () => { active = false; };
    }, [teamId, selfId, teamsVersion, t]);
    const reloadTags = useCallback(async () => { const data = await service.tags(teamId); setTags(data.tags || []); setTagIds((current) => current.filter((id) => data.tags.some((tag) => tag.id === id))); reload(); }, [teamId]);
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
    }, [fromDate, toDate, view, teamId, selected, tagIds, zone, refresh, t]);
    useEffect(() => {
        setSuggestions(null);
        setPlanningError('');
        setPlanning(false);
        planningGeneration.current++;
    }, [date, teamId, selected, planner, zone]);
    useEffect(() => {
        let active = true;
        const invalidate = () => {
            detailGeneration.current++;
            planningGeneration.current++;
            setDetail(null);
            setCalendar({ events: [], busy: [] });
            setSuggestions(null);
            setPlanning(false);
            setActionBusy(false);
            reload();
            if (document.hidden) return;
            teamService.getTeams().then((data) => {
                if (!active) return;
                setTeams(data.teams || []);
                if (teamId && !data.teams.some((item) => item.id === teamId)) setTeamId('');
            }).catch(() => {});
            if (teamId) teamService.getTeamMembers(teamId).then((data) => {
                if (!active) return;
                const current = peopleOf(data);
                setMembers(current);
                setSelected((ids) => ids.filter((id) => id === selfId || current.some((person) => person.id === id)));
            }).catch(() => { if (active) { setMembers([]); setSelected([selfId]); } });
        };
        window.addEventListener('focus', invalidate);
        window.addEventListener('day-planning:changed', invalidate);
        document.addEventListener('visibilitychange', invalidate);
        return () => {
            active = false;
            window.removeEventListener('focus', invalidate);
            window.removeEventListener('day-planning:changed', invalidate);
            document.removeEventListener('visibilitychange', invalidate);
        };
    }, [teamId, selfId]);
    const chooseView = (next) => { setView(next); if (next !== 'mine' && !teamId && teams.length) setTeamId(teams[0].id); };
    const togglePerson = (id) => { if (view === 'planner' && id === selfId) return; setSelected((current) => current.includes(id) ? current.filter((value) => value !== id) : [...current, id]); };
    const startCreate = (slot) => { setNotice(''); setEditing({ seed: { date, teamId: view !== 'mine' ? teamId : '', personIds: slot ? [...new Set([selfId, ...selected])] : [selfId], ...slot } }); };
    const showDetail = async (item) => {
        const generation = ++detailGeneration.current;
        setDetail(null);
        setDetailError('');
        setParticipationConfirmation(null);
        setActionBusy(true);
        try { const data = await service.occurrence(item.id, item.occurrenceKey); if (generation === detailGeneration.current) { setDetail(data); setDetailScope('occurrence'); } }
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
    const detailStartDate = detail ? dateInZone(detail.start, detail.allDay ? detail.timeZone : zone) : null;
    const detailEndDate = detail ? detail.allDay ? addDays(dateInZone(detail.end, detail.timeZone), -1) : dateInZone(detail.end, zone) : null;
    const scopeSelect = detail?.recurring && <label className="day-field">{t('dayPlanning.changeScope')}<select aria-label={t('dayPlanning.changeScope')} value={detailScope} onChange={(e) => { setDetailScope(e.target.value); setParticipationConfirmation(null); }}><option value="occurrence">{t('dayPlanning.thisOccurrence')}</option><option value="series">{t('dayPlanning.wholeSeries')}</option></select></label>;
    if (editing) return <div className="day-planning"><DayEventEditor key={`${editing.event?.id || 'new'}-${editing.occurrenceKey || 'series'}-${editing.event?.version || 0}`} {...editing} zone={zone} user={user} teams={teams} onTagCreated={(tag) => { if (tag.scope === 'PERSONAL' || tag.teamId === teamId) setTags((current) => [...current.filter((item) => item.id !== tag.id), tag]); }} onClose={() => { setEditing(null); reloadTags().catch(() => setCatalogError(t('dayPlanning.catalogError'))); }} onReload={async () => {
        try { const current = editing.occurrenceKey ? await service.occurrence(editing.event.id, editing.occurrenceKey) : await service.definition(editing.event.id); setEditing({ ...editing, event: current }); }
        catch { setEditing(null); setError(t('dayPlanning.noAccess')); reload(); }
    }} onSaved={() => { setEditing(null); setNotice(t('dayPlanning.saved')); reload(); }} /></div>;
    return <section className="day-planning">
        <header className="day-head"><div><p className="day-eyebrow">{t('dayPlanning.subtitle')}</p><h2>{t('dayPlanning.title')}</h2><p className="day-hint">{t('dayPlanning.intro')}</p></div><Button type="button" icon="add" onClick={() => startCreate()}>{t('dayPlanning.newEvent')}</Button></header>
        <div className="day-tabs" role="tablist" aria-label={t('dayPlanning.views')}>{views.map(([value, label], index) => <button key={value} type="button" role="tab" id={`day-tab-${value}`} aria-selected={view === value} aria-controls="day-content" tabIndex={view === value ? 0 : -1} onKeyDown={(event) => {
            const next = event.key === 'ArrowRight' ? (index + 1) % views.length : event.key === 'ArrowLeft' ? (index + views.length - 1) % views.length : event.key === 'Home' ? 0 : event.key === 'End' ? views.length - 1 : null;
            if (next !== null) { event.preventDefault(); chooseView(views[next][0]); document.getElementById(`day-tab-${views[next][0]}`)?.focus(); }
        }} onClick={() => chooseView(value)}>{t(`dayPlanning.${label}`)}</button>)}</div>
        {notice && <div role="status" className="day-success">{notice}</div>}
        {teamError && <div role="alert" className="day-alert">{teamError}<Button type="button" variant="text" onClick={() => setTeamsVersion((value) => value + 1)}>{t('dayPlanning.retry')}</Button></div>}
        <div className="day-toolbar">
            <div className="day-date-controls"><IconButton icon="chevronLeft" label={t('dayPlanning.previous')} onClick={() => setDate(addDays(date, isWeek ? -7 : -1))} /><label className="day-date-input"><span className="sr-only">{t('dayPlanning.date')}</span><input aria-label={t('dayPlanning.date')} type="date" required value={date} onChange={(e) => { if (e.target.value) setDate(e.target.value); }} /></label><IconButton icon="chevronRight" label={t('dayPlanning.next')} onClick={() => setDate(addDays(date, isWeek ? 7 : 1))} /><Button type="button" variant="text" onClick={() => setDate(dateInZone(new Date(), zone))}>{t('dayPlanning.today')}</Button></div>
            <div className="day-toolbar-selects">{view === 'mine' && calendarView === 'list' && <label className="day-compact-field">{t('dayPlanning.period')}<select aria-label={t('dayPlanning.period')} value={period} onChange={(e) => setPeriod(e.target.value)}><option value="day">{t('dayPlanning.day')}</option><option value="week">{t('dayPlanning.week')}</option></select></label>}{<label className="day-compact-field">{t(view === 'mine' ? 'dayPlanning.tagCatalog' : 'dayPlanning.team')}<select aria-label={t(view === 'mine' ? 'dayPlanning.tagCatalog' : 'dayPlanning.team')} value={teamId} onChange={(e) => setTeamId(e.target.value)}><option value="">{t(view === 'mine' ? 'dayPlanning.personalCatalog' : 'dayPlanning.personal')}</option>{teams.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</select></label>}<label className="day-compact-field">{t('dayPlanning.displayZone')}<select aria-label={t('dayPlanning.displayZone')} value={zone} onChange={(e) => setZone(e.target.value)}>{[...new Set([browserTimeZone(), 'Europe/Warsaw', 'Europe/London', 'America/New_York', 'UTC'])].map((value) => <option key={value}>{value}</option>)}</select></label></div>
        </div>
        {view !== 'planner' && <div className="day-display-switch" role="group" aria-label={t('dayPlanning.calendarView')}>{['list', 'week'].map((value) => <button key={value} type="button" aria-pressed={calendarView === value} onClick={() => setCalendarView(value)}>{t(value === 'list' ? 'dayPlanning.listView' : 'dayPlanning.weekView')}</button>)}</div>}
        {view !== 'mine' && <fieldset className="day-choice-group day-people"><legend>{t('dayPlanning.participants')}</legend><div className="day-chips">{people.map((person) => <label className="day-choice" key={person.id}><input type="checkbox" checked={view === 'planner' && person.id === selfId || selected.includes(person.id)} disabled={view === 'planner' && person.id === selfId} onChange={() => togglePerson(person.id)} />{personName(person)}</label>)}</div>{view === 'planner' && <p className="day-hint">{t('dayPlanning.authorRequired')}</p>}</fieldset>}
        {catalogError && <div role="alert" className="day-alert">{catalogError}<Button type="button" variant="text" onClick={() => setTeamsVersion((value) => value + 1)}>{t('dayPlanning.retry')}</Button></div>}
        {view !== 'planner' && <div className="day-filter"><span className="day-filter-label">{t('dayPlanning.tags')}</span><div className="day-chips"><button type="button" className={`day-chip${!tagIds.length ? ' is-selected' : ''}`} aria-pressed={!tagIds.length} onClick={() => setTagIds([])}>{t('dayPlanning.allTags')}</button>{tags.map((tag) => <button key={tag.id} type="button" aria-pressed={tagIds.includes(tag.id)} className={`day-chip${tagIds.includes(tag.id) ? ' is-selected' : ''}`} onClick={() => setTagIds((current) => current.includes(tag.id) ? current.filter((id) => id !== tag.id) : [...current, tag.id])}><span className="day-dot" style={{ background: tag.color }} />{tag.name}</button>)}</div><Button type="button" variant="text" onClick={() => setShowTags(true)}>{t('dayPlanning.manageTags')}</Button></div>}
        {!!tagIds.length && view !== 'planner' && <p className="day-hint"><Icon name="lock" size={16} /> {t('dayPlanning.filterHint')}</p>}
        <div id="day-content" role="tabpanel" aria-labelledby={`day-tab-${view}`}>
            {view !== 'planner' ? <>
                <h3 className="day-range-title">{displayDate(fromDate, i18n.language, { weekday: 'long' })}{isWeek ? ` – ${displayDate(addDays(toDate, -1), i18n.language)}` : ''}</h3>
                {loading || actionBusy && !detail ? <p role="status" className="day-loading">{t('common.loading')}</p> : error ? <div role="alert" className="day-alert">{error}<Button type="button" variant="text" onClick={reload}>{t('dayPlanning.retry')}</Button></div> : view === 'team' && !teamId ? <p className="day-empty">{t('dayPlanning.chooseTeam')}</p> : calendarView === 'week' ? <WeekCalendar events={calendar.events || []} busy={calendar.busy || []} date={fromDate} people={shownPeople} zone={zone} onOpen={showDetail} /> : <DayCalendar events={calendar.events || []} busy={calendar.busy || []} date={fromDate} mode={view === 'mine' ? period : 'team'} people={shownPeople} zone={zone} onOpen={showDetail} />}
                {calendar.coverage?.complete === false && <p role="alert" className="day-alert">{t('dayPlanning.incomplete')}</p>}
                <p className="day-privacy-note"><Icon name="lock" size={16} />{t('dayPlanning.privacyNote')}</p>
            </> : <div className="day-planner day-panel">
                <div className="day-planner-intro"><Icon name="calendar" size={28} /><div><h3>{t('dayPlanning.findTime')}</h3><p className="day-hint">{t('dayPlanning.plannerHint')}</p></div></div>
                <form onSubmit={findSlots}><fieldset disabled={planning} className="day-fields"><div className="day-row"><label className="day-field">{t('dayPlanning.searchDays')}<select aria-label={t('dayPlanning.searchDays')} value={planner.days} onChange={(e) => setPlanner({ ...planner, days: Number(e.target.value) })}>{[1, 3, 7, 14, 30].map((days) => <option key={days} value={days}>{t('dayPlanning.daysCount', { count: days })}</option>)}</select></label><label className="day-field">{t('dayPlanning.duration')}<input type="number" required min={15} max={720} step={15} value={planner.duration} onChange={(e) => setPlanner({ ...planner, duration: e.target.value })} /></label><label className="day-field">{t('dayPlanning.windowStart')}<input type="time" required value={planner.windowStart} onChange={(e) => setPlanner({ ...planner, windowStart: e.target.value })} /></label><label className="day-field">{t('dayPlanning.windowEnd')}<input type="time" required value={planner.windowEnd} onChange={(e) => setPlanner({ ...planner, windowEnd: e.target.value })} /></label></div><div><Button type="submit" disabled={!!catalogError || planner.windowEnd <= planner.windowStart}>{t(planning ? 'dayPlanning.searching' : 'dayPlanning.search')}</Button></div></fieldset></form>
                {planningError && <p role="alert" className="day-alert">{planningError}</p>}
                {suggestions && <div className="day-suggestions" aria-live="polite"><h3>{t('dayPlanning.suggestions')}</h3>{suggestions.coverage?.complete === false ? <p role="alert">{t('dayPlanning.incomplete')}</p> : suggestions.slots.length ? suggestions.slots.map((slot) => <button className="day-slot" key={slot.start} onClick={() => startCreate(slot)}><span><strong>{displayDate(dateInZone(slot.start, zone), i18n.language, { weekday: 'short' })}</strong><span>{displayTime(slot.start, i18n.language, zone)}–{displayTime(slot.end, i18n.language, zone)}</span></span><span>{t('dayPlanning.useSlot')} <Icon name="chevronRight" size={18} /></span></button>) : <p>{t('dayPlanning.noSlots')}</p>}</div>}
            </div>}
        </div>
        {detail && <Dialog open onClose={actionBusy ? undefined : () => setDetail(null)} headline={detail.title} actions={<Button type="button" variant="text" disabled={actionBusy} onClick={() => setDetail(null)}>{t('dayPlanning.close')}</Button>}><div className="day-planning day-detail"><p>{detail.allDay ? t('dayPlanning.allDay') : `${displayTime(detail.start, i18n.language, zone)}–${displayTime(detail.end, i18n.language, zone)}`} · {displayDate(detailStartDate, i18n.language)}{detailEndDate !== detailStartDate ? ` – ${displayDate(detailEndDate, i18n.language)}` : ''}</p><p className="day-hint">{detail.timeZone} · {t(detail.visibility === 'PRIVATE' ? 'dayPlanning.private' : 'dayPlanning.teamVisible')}{detail.recurring ? ` · ${t('dayPlanning.repeats')}` : ''}</p>{detail.description && <p className="day-description">{detail.description}</p>}{detail.location && <p>{t('dayPlanning.location')}: {detail.location}</p>}<div className="day-chips">{detail.tags?.map((tag) => <span key={tag.id} className="day-chip"><span className="day-dot" style={{ background: tag.color }} />{tag.name}</span>)}</div><h3>{t('dayPlanning.participants')}</h3><ul>{detail.participants?.map((person) => <li key={person.personId}>{personName(people.find((item) => item.id === person.personId)) || t('dayPlanning.teamMember')}{person.status === 'DECLINED' ? ` · ${t('dayPlanning.declined')}` : ''}</li>)}</ul>{(detail.canEdit || detail.canChangeParticipation) && scopeSelect}<div className="day-actions">{detail.canEdit && <><Button type="button" disabled={actionBusy} onClick={() => edit(detail, detailScope)}>{t('dayPlanning.edit')}</Button><Button type="button" variant="text" disabled={actionBusy} onClick={deleteEvent}>{t('common.delete')}</Button></>}{detail.canChangeParticipation && <Button type="button" variant="outlined" disabled={actionBusy} onClick={() => participate()}>{t(detail.participation === 'DECLINED' ? 'dayPlanning.rejoin' : 'dayPlanning.decline')}</Button>}</div>{participationConfirmation && <div role="alert" className="day-confirmation"><h3>{t('dayPlanning.conflicts')}</h3><p>{t('dayPlanning.conflictsHint')}</p><Button type="button" disabled={actionBusy} onClick={() => participate(participationConfirmation)}>{t('dayPlanning.confirmRejoin')}</Button><Button type="button" variant="text" disabled={actionBusy} onClick={() => setParticipationConfirmation(null)}>{t('common.cancel')}</Button></div>}{detailError && <div role="alert" className="day-alert">{detailError}<Button type="button" variant="text" disabled={actionBusy} onClick={() => showDetail(detail)}>{t('dayPlanning.reload')}</Button></div>}</div></Dialog>}
        {showTags && <DayTagManager tags={tags} team={team} onChanged={reloadTags} onClose={() => setShowTags(false)} />}
    </section>;
}

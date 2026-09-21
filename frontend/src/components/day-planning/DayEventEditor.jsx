import React, { useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '../md3';
import TagColorPalette from './TagColorPalette';
import DateTimePickerField from './DateTimePickerField';
import service from '../../services/dayPlanningService';
import teamService from '../../services/teamService';
import { addDays, dateInZone, localDateTime, peopleOf, personName, zonedIso } from '../../services/dayPlanningTime';

const initialDraft = (event, seed = {}, zone, userId) => {
    const schedule = event?.schedule;
    const timeZone = schedule?.timeZone || event?.timeZone || zone;
    const start = schedule?.localStart || (event?.start ? localDateTime(event.start, timeZone) : seed?.start ? localDateTime(seed.start, timeZone) : `${seed.date || dateInZone(new Date(), zone)}T09:00`);
    const duration = schedule?.durationMinutes || 60;
    const end = event?.end ? localDateTime(event.end, timeZone) : seed?.end ? localDateTime(seed.end, timeZone) : localDateTime(new Date(new Date(schedule?.utcOffset ? `${start}${schedule.utcOffset}` : zonedIso(start, timeZone)).getTime() + duration * 60000), timeZone);
    return {
        title: event?.title || '', description: event?.description || '', location: event?.location || '',
        teamId: event?.teamId || seed?.teamId || '', visibility: event?.visibility || 'PRIVATE', timeZone,
        allDay: schedule?.kind === 'ALL_DAY' || !!event?.allDay,
        start: schedule?.startDate ? `${schedule.startDate}T00:00` : start,
        end: schedule?.endDate ? `${schedule.endDate}T00:00` : end,
        participantIds: (event?.participantIds || seed?.personIds || [userId]).map(String),
        tagIds: event?.tagIds || event?.tags?.map((tag) => tag.id) || [],
        blocksTime: event?.blocksTime !== false,
        frequency: event?.recurrence?.frequency || '', interval: event?.recurrence?.interval || 1,
        byDay: event?.recurrence?.byDay || [new Date(`${start.slice(0, 10)}T12:00:00Z`).getUTCDay() || 7],
        ending: event?.recurrence?.until ? 'until' : event?.recurrence?.count ? 'count' : '',
        until: event?.recurrence?.until || addDays(start.slice(0, 10), 90), count: event?.recurrence?.count || 10,
    };
};

export default function DayEventEditor({ event, occurrenceKey, seed, zone, user, teams, onSaved, onClose, onReload, onTagCreated }) {
    const { t, i18n } = useTranslation();
    const [draft, setDraft] = useState(() => initialDraft(event, seed, zone, String(user.id)));
    const initial = useRef(draft);
    const [members, setMembers] = useState([]);
    const [tags, setTags] = useState([]);
    const [loading, setLoading] = useState(false);
    const [catalogError, setCatalogError] = useState(false);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState('');
    const [stale, setStale] = useState(false);
    const [confirmation, setConfirmation] = useState(null);
    const attempt = useRef(null);
    const heading = useRef(null);
    const [catalogVersion, setCatalogVersion] = useState(0);
    const [tagDraft, setTagDraft] = useState(null);
    const [tagSaving, setTagSaving] = useState(false);
    const [tagError, setTagError] = useState('');
    const tagPending = useRef(false);
    const active = useRef(true);
    const canCreateTeamTag = teams.some((team) => team.id === draft.teamId && team.role === 'admin');
    const canCreateTag = draft.visibility === 'PRIVATE' || canCreateTeamTag;
    const tagScope = draft.visibility === 'TEAM' || tagDraft?.scope === 'TEAM' && canCreateTeamTag ? 'TEAM' : 'PERSONAL';
    const tagContext = `${draft.teamId}:${draft.visibility}:${canCreateTeamTag}`;
    const currentTagContext = useRef(tagContext);
    currentTagContext.current = tagContext;
    useEffect(() => { active.current = true; return () => { active.current = false; }; }, []);
    const createTag = async () => {
        if (tagPending.current || !tagDraft?.name.trim() || !canCreateTag || loading || catalogError) return;
        const context = currentTagContext.current;
        tagPending.current = true;
        setTagSaving(true);
        setTagError('');
        try {
            const tag = await service.saveTag({ name: tagDraft.name.trim(), color: tagDraft.color, scope: tagScope, teamId: tagScope === 'TEAM' ? draft.teamId : null });
            if (!active.current) return;
            onTagCreated?.(tag);
            if (currentTagContext.current !== context) { setTagError(t('dayPlanning.tagContextChanged')); return; }
            setTags((current) => [...current.filter((item) => item.id !== tag.id), tag]);
            setDraft((current) => ({ ...current, tagIds: [...new Set([...current.tagIds, tag.id])] }));
            setTagDraft(null);
            setConfirmation(null);
            setError('');
        } catch (failure) { if (active.current) setTagError(failure.response?.data?.message || t('dayPlanning.tagError')); }
        finally { tagPending.current = false; if (active.current) setTagSaving(false); }
    };
    useEffect(() => { heading.current?.focus(); }, []);
    useEffect(() => {
        let active = true;
        setLoading(true);
        setCatalogError(false);
        setMembers([]);
        setTags([]);
        Promise.all([service.tags(draft.teamId), draft.teamId ? teamService.getTeamMembers(draft.teamId) : Promise.resolve({ members: [] })])
            .then(([tagData, memberData]) => { if (active) { setTags(tagData.tags); setMembers(peopleOf(memberData)); } })
            .catch(() => { if (active) setCatalogError(true); })
            .finally(() => { if (active) setLoading(false); });
        return () => { active = false; };
    }, [draft.teamId, catalogVersion]);
    const update = (changes) => { setDraft((current) => ({ ...current, ...changes })); setConfirmation(null); setError(''); };
    const toggle = (key, value) => update({ [key]: draft[key].includes(value) ? draft[key].filter((item) => item !== value) : [...draft[key], value] });
    const allowedTags = tags.filter((tag) => draft.visibility === 'PRIVATE' || tag.scope === 'TEAM');
    const retainedTags = draft.teamId === initial.current.teamId && draft.visibility === initial.current.visibility
        ? initial.current.tagIds.filter((id) => !tags.some((tag) => tag.id === id)) : [];
    const invalidTags = draft.tagIds.some((id) => !allowedTags.some((tag) => tag.id === id) && !retainedTags.includes(id));
    const makePayload = (value) => {
        new Intl.DateTimeFormat('en', { timeZone: value.timeZone }).format(new Date());
        const start = value.allDay ? `${value.start.slice(0, 10)}T00:00` : value.start;
        const end = value.allDay ? `${value.end.slice(0, 10)}T00:00` : value.end;
        const unchangedTime = (event?.schedule?.kind === 'TIMED' || event?.start && !event.allDay) && !value.allDay && value.start === initial.current.start && value.end === initial.current.end && value.timeZone === initial.current.timeZone;
        const durationMinutes = unchangedTime ? event.schedule?.durationMinutes ?? Math.round((new Date(event.end) - new Date(event.start)) / 60000) : Math.round((new Date(zonedIso(end, value.timeZone)) - new Date(zonedIso(start, value.timeZone))) / 60000);
        if (durationMinutes <= 0) throw new Error('invalid_range');
        return {
            title: value.title.trim(), description: value.description, location: value.location,
            teamId: value.teamId || null, visibility: value.visibility,
            schedule: value.allDay ? { kind: 'ALL_DAY', startDate: start.slice(0, 10), endDate: end.slice(0, 10), timeZone: value.timeZone }
                : { kind: 'TIMED', localStart: start, durationMinutes, timeZone: value.timeZone, ...(unchangedTime && event.schedule?.utcOffset ? { utcOffset: event.schedule.utcOffset } : {}) },
            participantIds: value.participantIds, tagIds: value.tagIds, blocksTime: value.blocksTime,
            recurrence: value.frequency ? { frequency: value.frequency, interval: Number(value.interval), ...(value.frequency === 'WEEKLY' ? { byDay: value.byDay } : {}), until: value.ending === 'until' ? value.until : null, count: value.ending === 'count' ? Number(value.count) : null } : null,
        };
    };
    const save = async (payload, extra = {}) => {
        if (tagPending.current || tagDraft) return;
        setSaving(true);
        setError('');
        try {
            let saved;
            if (occurrenceKey) {
                const original = makePayload(initial.current);
                const changes = Object.fromEntries(Object.entries(payload).filter(([key, value]) => !['teamId', 'recurrence'].includes(key) && JSON.stringify(original[key]) !== JSON.stringify(value)));
                if (Object.keys(changes).length === 0) { onSaved(event); return; }
                saved = await service.exception(event.id, occurrenceKey, { changes, ...extra }, event.version);
            } else if (event?.id) saved = await service.update(event.id, { ...payload, ...extra }, event.version);
            else {
                const signature = JSON.stringify(payload);
                if (attempt.current?.signature !== signature) attempt.current = { signature, key: crypto.randomUUID() };
                saved = await service.create({ ...payload, ...extra }, attempt.current.key);
            }
            onSaved(saved);
        } catch (failure) {
            const data = failure.response?.data;
            if (data?.code === 'planning_conflict') setConfirmation({ type: 'conflict', payload, extra, data });
            else if (data?.code === 'exceptions_reset_required') setConfirmation({ type: 'exceptions', payload, extra, data });
            else if (failure.response?.status === 412) { setStale(true); setError(t('dayPlanning.stale')); }
            else setError(data?.message || t('dayPlanning.saveError'));
        } finally { setSaving(false); }
    };
    const restore = async (key, token) => {
        if (!token && !window.confirm(t('dayPlanning.confirmRestore'))) return;
        setSaving(true);
        setError('');
        try { onSaved(await service.restore(event.id, key, event.version, token ? { conflictConfirmation: token } : {})); }
        catch (failure) {
            const data = failure.response?.data;
            if (data?.code === 'planning_conflict') setConfirmation({ type: 'conflict', restoreKey: key, data });
            else if (failure.response?.status === 412) { setStale(true); setError(t('dayPlanning.stale')); }
            else setError(data?.message || t('dayPlanning.saveError'));
        } finally { setSaving(false); }
    };
    const submit = (e) => {
        e.preventDefault();
        if (tagPending.current || tagDraft) { setError(t('dayPlanning.finishTagDraft')); return; }
        try { save(makePayload(draft)); }
        catch (failure) { setError(t(failure.message === 'invalid_range' ? 'dayPlanning.invalidRange' : 'dayPlanning.invalidTime')); }
    };
    const field = (key, label, type = 'text', extra = {}) => type === 'date' ? <DateTimePickerField label={t(`dayPlanning.${label}`)} value={draft[key]} onChange={(value) => update({ [key]: value })} timeZone={draft.timeZone} {...extra} /> : <label className="day-field">{t(`dayPlanning.${label}`)}<input type={type} value={draft[key]} onChange={(e) => update({ [key]: e.target.value })} {...extra} /></label>;
    const select = (key, label, options, extra = {}) => <label className="day-field">{t(`dayPlanning.${label}`)}<select aria-label={t(`dayPlanning.${label}`)} value={draft[key]} onChange={(e) => update({ [key]: e.target.value })} {...extra}>{options.map(([value, text]) => <option key={value} value={value}>{text}</option>)}</select></label>;
    const weekdays = Array.from({ length: 7 }, (_, index) => ({ value: index + 1, label: new Intl.DateTimeFormat(i18n.language, { weekday: 'short', timeZone: 'UTC' }).format(new Date(`2026-09-${21 + index}T12:00:00Z`)) }));
    return <section className="day-editor day-panel">
        <div className="day-head"><div><p className="day-eyebrow">{t(occurrenceKey ? 'dayPlanning.thisOccurrence' : 'dayPlanning.title')}</p><h2 ref={heading} tabIndex={-1}>{t(event ? 'dayPlanning.editEvent' : 'dayPlanning.newEvent')}</h2></div><Button type="button" variant="text" disabled={saving || tagSaving} onClick={onClose}>{t('common.cancel')}</Button></div>
        <form onSubmit={submit}>
            <fieldset disabled={saving || tagSaving} className="day-fields">
                {field('title', 'eventTitle', 'text', { required: true, maxLength: 200, autoComplete: 'off' })}
                <div className="day-row">
                    <label className="day-field">{t('dayPlanning.eventTeam')}<select aria-label={t('dayPlanning.eventTeam')} value={draft.teamId} disabled={!!occurrenceKey} onChange={(e) => update({ teamId: e.target.value, participantIds: [String(user.id)], tagIds: [], visibility: e.target.value ? draft.visibility : 'PRIVATE' })}><option value="">{t('dayPlanning.personal')}</option>{teams.map((team) => <option value={team.id} key={team.id}>{team.name}</option>)}</select></label>
                    {field('timeZone', 'timeZone', 'text', { required: true, list: 'day-timezones' })}
                    <datalist id="day-timezones">{[...new Set([zone, 'Europe/Warsaw', 'Europe/London', 'America/New_York', 'UTC'])].map((value) => <option key={value} value={value} />)}</datalist>
                </div>
                <label className="day-check"><input type="checkbox" checked={draft.allDay} onChange={(e) => update({ allDay: e.target.checked, end: e.target.checked && draft.start.slice(0, 10) === draft.end.slice(0, 10) ? `${addDays(draft.start.slice(0, 10), 1)}T00:00` : draft.end })} />{t('dayPlanning.allDay')}</label>
                <div className="day-row">{['start', 'end'].map((key) => <DateTimePickerField key={key} label={t(`dayPlanning.${draft.allDay && key === 'end' ? 'exclusiveEnd' : key}`)} type={draft.allDay ? 'date' : 'datetime-local'} required value={draft.allDay ? draft[key].slice(0, 10) : draft[key]} onChange={(value) => update({ [key]: draft.allDay ? `${value}T00:00` : value })} timeZone={draft.timeZone} />)}</div>
                {!occurrenceKey && <div className="day-recurrence">
                    <div className="day-row">{select('frequency', 'repeat', [['', t('dayPlanning.once')], ['DAILY', t('dayPlanning.daily')], ['WEEKLY', t('dayPlanning.weekly')]])}{draft.frequency && field('interval', draft.frequency === 'DAILY' ? 'everyDays' : 'everyWeeks', 'number', { min: 1, max: 99, required: true })}</div>
                    {draft.frequency === 'WEEKLY' && <fieldset className="day-choice-group"><legend>{t('dayPlanning.weekdays')}</legend><div className="day-chips">{weekdays.map((day) => <label key={day.value} className="day-choice"><input type="checkbox" checked={draft.byDay.includes(day.value)} onChange={() => toggle('byDay', day.value)} />{day.label}</label>)}</div></fieldset>}
                    {draft.frequency && <div className="day-row">{select('ending', 'repeatEnd', [['', t('dayPlanning.never')], ['until', t('dayPlanning.until')], ['count', t('dayPlanning.afterCount')]])}{draft.ending === 'until' && field('until', 'untilDate', 'date', { required: true, min: draft.start.slice(0, 10) })}{draft.ending === 'count' && field('count', 'occurrenceCount', 'number', { required: true, min: 1, max: 10000 })}</div>}
                    {draft.frequency && <p className="day-hint">{t('dayPlanning.conflictHorizon')}</p>}
                </div>}
                <fieldset className="day-choice-group"><legend>{t('dayPlanning.visibility')}</legend><div className="day-visibility">{['PRIVATE', 'TEAM'].map((visibility) => <label key={visibility} className={`day-visibility-card${draft.visibility === visibility ? ' is-selected' : ''}`}><input type="radio" name="dayVisibility" value={visibility} checked={draft.visibility === visibility} disabled={visibility === 'TEAM' && !draft.teamId} onChange={() => update({ visibility })} /><span><strong>{t(`dayPlanning.${visibility === 'PRIVATE' ? 'private' : 'teamVisible'}`)}</strong><small>{t(`dayPlanning.${visibility === 'PRIVATE' ? 'privateExplanation' : 'teamExplanation'}`, { team: teams.find((team) => team.id === draft.teamId)?.name || '' })}</small></span></label>)}</div></fieldset>
                {catalogError && <div role="alert" className="day-alert">{t('dayPlanning.catalogError')}<Button type="button" variant="text" onClick={() => setCatalogVersion((value) => value + 1)}>{t('dayPlanning.retry')}</Button></div>}
                {loading && <p role="status">{t('common.loading')}</p>}
                <fieldset className="day-choice-group"><legend>{t('dayPlanning.participants')}</legend><div className="day-chips">{[user, ...members.filter((person) => person.id !== String(user.id))].map((person) => <label className="day-choice" key={person.id}><input type="checkbox" checked={draft.participantIds.includes(String(person.id))} disabled={String(person.id) === String(user.id)} onChange={() => toggle('participantIds', String(person.id))} />{String(person.id) === String(user.id) ? t('dayPlanning.you') : personName(person)}</label>)}</div><p className="day-hint">{t('dayPlanning.participationHint')}</p></fieldset>
                <fieldset className="day-choice-group"><legend>{t('dayPlanning.tags')}</legend><div className="day-chips">{[...allowedTags, ...retainedTags.map((id) => ({ id, name: event?.tags?.find((tag) => tag.id === id)?.name || t('dayPlanning.archivedTag'), color: '#808080' }))].map((tag) => <label className="day-choice" key={tag.id}><input type="checkbox" checked={draft.tagIds.includes(tag.id)} onChange={() => toggle('tagIds', tag.id)} /><span className="day-dot" style={{ background: tag.color }} />{tag.name}</label>)}</div>{!loading && !allowedTags.length && <p className="day-hint">{t('dayPlanning.noTags')}</p>}{!loading && invalidTags && <div role="alert" className="day-alert">{t('dayPlanning.replaceTags')}<Button type="button" variant="text" onClick={() => update({ tagIds: draft.tagIds.filter((id) => allowedTags.some((tag) => tag.id === id) || retainedTags.includes(id)) })}>{t('dayPlanning.removeUnavailableTags')}</Button></div>}
                    {!tagDraft && canCreateTag && <Button type="button" variant="text" disabled={loading || catalogError} onClick={() => { setTagDraft({ name: '', color: '#226a4c', scope: draft.visibility === 'TEAM' ? 'TEAM' : 'PERSONAL' }); setTagError(''); setConfirmation(null); }}>{t('dayPlanning.newTag')}</Button>}
                    {!canCreateTag && <p className="day-hint">{t('dayPlanning.teamTagAdminOnly')}</p>}
                    {tagDraft && <section className="day-inline-tag" aria-label={t('dayPlanning.newTag')}>
                        <h3>{t('dayPlanning.newTag')}</h3>
                        <label className="day-field">{t('dayPlanning.tagName')}<input maxLength={60} autoComplete="off" value={tagDraft.name} onChange={(e) => setTagDraft((current) => ({ ...current, name: e.target.value }))} onKeyDown={(e) => { if (e.key === 'Enter') { e.preventDefault(); createTag(); } }} /></label>
                        <TagColorPalette value={tagDraft.color} onChange={(color) => setTagDraft((current) => ({ ...current, color }))} disabled={tagSaving} />
                        {canCreateTag && <label className="day-field">{t('dayPlanning.tagScope')}<select aria-label={t('dayPlanning.tagScope')} value={tagScope} onChange={(e) => setTagDraft((current) => ({ ...current, scope: e.target.value }))}>
                            {draft.visibility === 'PRIVATE' && <option value="PERSONAL">{t('dayPlanning.personalTag')}</option>}
                            {canCreateTeamTag && <option value="TEAM">{t('dayPlanning.teamTag')}</option>}
                        </select></label>}
                        {tagError && <p role="alert" className="day-alert">{tagError}</p>}
                        <p className="day-hint">{t('dayPlanning.finishTagDraft')}</p>
                        <div className="day-actions"><Button type="button" disabled={tagSaving || !tagDraft.name.trim() || !canCreateTag || loading || catalogError} onClick={createTag}>{t(tagSaving ? 'dayPlanning.savingTag' : 'dayPlanning.createTag')}</Button><Button type="button" variant="text" disabled={tagSaving} onClick={() => { setTagDraft(null); setTagError(''); }}>{t('dayPlanning.cancelTag')}</Button></div>
                    </section>}
                </fieldset>
                <label className="day-field">{t('dayPlanning.description')}<textarea rows={3} maxLength={5000} value={draft.description} onChange={(e) => update({ description: e.target.value })} /></label>
                {field('location', 'location', 'text', { maxLength: 500 })}
                <label className="day-check"><input type="checkbox" checked={draft.blocksTime} onChange={(e) => update({ blocksTime: e.target.checked })} />{t('dayPlanning.blocksTime')}</label>
            </fieldset>
            {error && <div role="alert" className="day-alert">{error}{stale && <Button type="button" variant="text" onClick={() => { if (window.confirm(t('dayPlanning.reloadDraft'))) onReload(); }}>{t('dayPlanning.reload')}</Button>}</div>}
            {confirmation && <div role="alert" className="day-confirmation"><h3>{t(confirmation.type === 'conflict' ? 'dayPlanning.conflicts' : 'dayPlanning.resetExceptions')}</h3><p>{t(confirmation.type === 'conflict' ? 'dayPlanning.conflictsHint' : 'dayPlanning.resetExceptionsHint')}</p>{confirmation.data.conflicts?.map((conflict, index) => <p key={index}>{personName([user, ...members].find((person) => String(person.id) === conflict.personId))} · {new Date(conflict.start).toLocaleString(i18n.language, { timeZone: draft.timeZone })}–{new Date(conflict.end).toLocaleTimeString(i18n.language, { timeZone: draft.timeZone, hour: '2-digit', minute: '2-digit' })}</p>)}<Button type="button" disabled={saving || tagSaving || !!tagDraft} onClick={() => confirmation.restoreKey ? restore(confirmation.restoreKey, confirmation.data.confirmationToken) : save(confirmation.payload, { ...confirmation.extra, ...(confirmation.type === 'conflict' ? { conflictConfirmation: confirmation.data.confirmationToken } : { resetExceptions: true }) })}>{t('dayPlanning.confirmSave')}</Button><Button type="button" variant="text" disabled={saving} onClick={() => setConfirmation(null)}>{t('common.cancel')}</Button></div>}
            <div className="day-actions"><Button type="submit" disabled={saving || tagSaving || !!tagDraft || loading || catalogError || invalidTags || stale || (draft.frequency === 'WEEKLY' && !draft.byDay.length)}>{t(saving ? 'dayPlanning.saving' : 'common.save')}</Button><Button type="button" variant="text" disabled={saving || tagSaving} onClick={onClose}>{t('common.cancel')}</Button></div>
        </form>
        {!occurrenceKey && Object.keys(event?.exceptions || {}).length > 0 && <section className="day-exceptions"><h3>{t('dayPlanning.exceptions')}</h3><p className="day-hint">{t('dayPlanning.exceptionsHint')}</p><ul className="day-tag-list">{Object.entries(event.exceptions).sort(([a], [b]) => a.localeCompare(b)).map(([key, exception]) => <li key={key}><span>{key.replace('T', ' ')}<small>{t(exception.cancelled ? 'dayPlanning.cancelledOccurrence' : 'dayPlanning.changedOccurrence')}</small></span><Button type="button" variant="text" disabled={saving || tagSaving || !!tagDraft || stale} onClick={() => restore(key)} aria-label={t('dayPlanning.restoreOccurrence', { key: key.replace('T', ' ') })}>{t('dayPlanning.restore')}</Button></li>)}</ul></section>}
    </section>;
}

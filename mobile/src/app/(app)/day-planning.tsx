import { subscribeCalendarChanges } from '@/day-planning/changes';
import PlanningCheckbox from '@/components/day-planning/checkbox';
import { useFocusEffect } from 'expo-router';
import { useCallback, useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { AppState, KeyboardAvoidingView, Platform, RefreshControl, ScrollView, View } from 'react-native';
import { ActivityIndicator, Button, Card, Chip, Dialog, IconButton, Portal, Text, TextInput, useTheme } from 'react-native-paper';
import { SafeAreaView } from 'react-native-safe-area-context';

import { ApiError } from '@/api/client';
import { cancelOccurrence, changeOccurrence, changeParticipation, createEvent, findSuggestions, listCalendarTags, readCalendar, readEvent, readOccurrence, removeEvent, restoreOccurrence, updateEvent,
  type Calendar, type CalendarTag, type Confirmation, type EventDefinition, type EventDraft, type Occurrence, type PlanningFailure, type Suggestions } from '@/api/day-planning';
import { listMembers, listTeams, type Member, type Team } from '@/api/teams';
import { useAuth } from '@/auth/auth-context';
import ChoicePicker from '@/components/action-plans/choice-picker';
import EventEditor from '@/components/day-planning/event-editor';
import TagManager from '@/components/day-planning/tag-manager';
import WeekCalendar from '@/components/day-planning/week-calendar';
import { mondayOf } from '@/day-planning/week-layout';
import { dayString, isDay, shiftDay } from '@/dates';
import { deviceTimeZone, draftFromDefinition, draftFromOccurrence, isTime, localInstant, localParts, makeRequestKey, rangeFor } from '@/day-planning/time';
import { useScreenBackground } from '@/personalisation/use-screen-background';

type Editing = { key: string; draft: EventDraft; definition?: EventDefinition; occurrence?: Occurrence; occurrenceOnly: boolean };
type Confirm = { title: string; text: string; label?: string; action: () => void };

export default function DayPlanningScreen() {
  const { t, i18n } = useTranslation();
  const { user } = useAuth();
  const theme = useTheme();
  const ground = useScreenBackground();
  const [teams, setTeams] = useState<Team[]>([]);
  const [teamId, setTeamId] = useState<string | null>(null);
  const [members, setMembers] = useState<Member[]>([]);
  const [people, setPeople] = useState<string[]>([]);
  const [tags, setTags] = useState<CalendarTag[]>([]);
  const [tagIds, setTagIds] = useState<string[]>([]);
  const [date, setDate] = useState(dayString(new Date()));
  const [days, setDays] = useState(1);
  const [display, setDisplay] = useState<'LIST' | 'WEEK'>('LIST');
  const [view, setView] = useState('MINE');
  const [calendar, setCalendar] = useState<Calendar | null>(null);
  const [suggestions, setSuggestions] = useState<Suggestions | null>(null);
  const [planEnd, setPlanEnd] = useState(shiftDay(date, 6));
  const [windowStart, setWindowStart] = useState('08:00');
  const [windowEnd, setWindowEnd] = useState('20:00');
  const [duration, setDuration] = useState('60');
  const [zone, setZone] = useState(deviceTimeZone);
  const [error, setError] = useState('');
  const [scopeError, setScopeError] = useState('');
  const [loading, setLoading] = useState(false);
  const [saving, setSaving] = useState(false);
  const [selected, setSelected] = useState<Occurrence | null>(null);
  const [editing, setEditing] = useState<Editing | null>(null);
  const [tagManager, setTagManager] = useState(false);
  const [confirmation, setConfirmation] = useState<Confirm | null>(null);
  const [stale, setStale] = useState(false);
  const [revision, setRevision] = useState(0);
  const request = useRef(0);
  const focused = useRef(false);
  const attempt = useRef({ payload: '', key: makeRequestKey() });
  const scroll = useRef<ScrollView>(null);
  const currentUser = user?.id ?? '';
  const nameFor = (id: string) => id === currentUser ? user?.name ?? t('dayPlanning.me') : members.find((member) => member.userId === id)?.userName ?? t('dayPlanning.teamMember');
  const message = (failure: unknown) => {
    if (failure instanceof ApiError) {
      if (failure.status === 412) return t('dayPlanning.stale');
      if ([403, 404].includes(failure.status)) return t('dayPlanning.accessLost');
      const body = failure.body as PlanningFailure | null;
      if (failure.status === 422 && body?.message) return body.message;
    }
    return t('dayPlanning.requestError');
  };
  const reloadTags = useCallback(async () => { setTags(await listCalendarTags(teamId)); }, [teamId]);
  useFocusEffect(useCallback(() => {
    focused.current = true;
    setRevision((value) => value + 1);
    const unsubscribeChanges = subscribeCalendarChanges(() => {
      request.current += 1; setCalendar(null); setSuggestions(null); setSelected(null); setEditing(null); setConfirmation(null); setRevision((value) => value + 1);
    });
    const subscription = AppState.addEventListener('change', (state) => {
      if (state === 'active') { setSelected(null); setEditing(null); setRevision((value) => value + 1); }
      else { request.current += 1; setCalendar(null); setSuggestions(null); setSelected(null); setEditing(null); }
    });
    return () => {
      focused.current = false; request.current += 1; subscription.remove(); unsubscribeChanges();
      setCalendar(null); setSuggestions(null); setSelected(null); setEditing(null); setConfirmation(null);
    };
  }, []));
  useEffect(() => {
    let active = true;
    void Promise.resolve().then(() => { if (active) { setMembers([]); setTags([]); setScopeError(''); } });
    Promise.all([teamId ? listMembers(teamId) : Promise.resolve([]), listCalendarTags(teamId), listTeams()])
      .then(([nextMembers, nextTags, nextTeams]) => { if (active) { setMembers(nextMembers); setTags(nextTags); setTeams(nextTeams); } })
      .catch(() => { if (active) setScopeError(t('dayPlanning.scopeError')); });
    return () => { active = false; };
  }, [teamId, revision, t]);
  useEffect(() => {
    const sequence = ++request.current;
    void Promise.resolve().then(() => { if (sequence === request.current) { setCalendar(null); setSuggestions(null); setLoading(false); } });
    if (!focused.current || view === 'PLAN' || !isDay(date)) return;
    if (view === 'TEAM' && (!teamId || !people.length)) return;
    let range: { from: string; to: string };
    try { range = rangeFor(display === 'WEEK' ? mondayOf(date) : date, display === 'WEEK' ? 7 : days, zone); } catch { void Promise.resolve().then(() => { if (sequence === request.current) setError(t('dayPlanning.invalidTime')); }); return; }
    void Promise.resolve().then(() => { if (sequence === request.current) setLoading(true); });
    readCalendar(range.from, range.to, view === 'MINE' ? null : teamId, view === 'MINE' ? [] : people, tagIds)
      .then((next) => { if (sequence === request.current) { if (!next.coverage.complete) throw new Error('incomplete'); setCalendar(next); } })
      .catch(() => { if (sequence === request.current) setError(t('dayPlanning.loadError')); })
      .finally(() => { if (sequence === request.current) setLoading(false); });
  }, [date, days, display, view, teamId, people, tagIds, zone, revision, t]);
  useEffect(() => { scroll.current?.scrollTo({ y: 0, animated: false }); }, [selected, editing, tagManager]);
  const chooseTeam = (value: string) => { setTeamId(value || null); setPeople([currentUser]); setTagIds([]); setSuggestions(null); };
  const personToggle = (id: string) => { setPeople((current) => current.includes(id) ? current.filter((person) => person !== id) : [...current, id]); setSuggestions(null); };
  const refresh = (clearError = true) => { if (clearError) setError(''); setSelected(null); setSuggestions(null); setRevision((value) => value + 1); };
  const beginCreate = (slot?: { start: string; end: string }) => {
    try { localInstant(date, '09:00', zone); } catch { setError(t('dayPlanning.invalidTime')); return; }
    const start = slot ? localParts(slot.start, zone) : { date, time: '09:00' };
    setError(''); setStale(false); attempt.current = { payload: '', key: makeRequestKey() };
    setEditing({ key: makeRequestKey(), occurrenceOnly: false, draft: { title: '', description: '', location: '', teamId, visibility: 'PRIVATE',
      schedule: { kind: 'TIMED', localStart: `${start.date}T${start.time}`, durationMinutes: slot ? (Date.parse(slot.end) - Date.parse(slot.start)) / 60000 : 60, timeZone: zone },
      recurrence: null, participantIds: [...new Set([currentUser, ...(teamId && view === 'PLAN' ? people : [])])], tagIds: [], blocksTime: true } });
  };
  const openDetails = useCallback(async (event: Occurrence) => {
    const sequence = ++request.current;
    setSelected(null); setError(''); setSaving(true);
    try { const next = await readOccurrence(event.id, event.occurrenceKey); if (focused.current && sequence === request.current) setSelected(next); }
    catch (failure) { setError(t(failure instanceof ApiError && [403, 404].includes(failure.status) ? 'dayPlanning.accessLost' : 'dayPlanning.requestError')); setRevision((value) => value + 1); }
    finally { setSaving(false); }
  }, [t]);
  const beginEdit = async (event: Occurrence, occurrenceOnly: boolean) => {
    const sequence = ++request.current;
    setSaving(true); setError(''); setStale(false);
    try {
      const definition = await readEvent(event.id);
      if (focused.current && sequence === request.current) { setSelected(null); setEditing({ key: makeRequestKey(), draft: occurrenceOnly ? draftFromOccurrence(event) : draftFromDefinition(definition), definition, occurrence: event, occurrenceOnly }); }
    } catch (failure) { setError(message(failure)); }
    finally { setSaving(false); }
  };
  const save = async (draft: EventDraft, confirmed: Confirmation = {}) => {
    if (!editing) return;
    const payload = JSON.stringify(draft);
    if (attempt.current.payload !== payload) attempt.current = { payload, key: makeRequestKey() };
    setSaving(true); setError('');
    try {
      if (editing.definition && editing.occurrenceOnly && editing.occurrence) {
        const changes = Object.fromEntries(Object.entries(draft).filter(([key, value]) => !['teamId', 'recurrence'].includes(key) && JSON.stringify(value) !== JSON.stringify(editing.draft[key as keyof EventDraft])));
        if (Object.keys(changes).length) await changeOccurrence(editing.definition.id, editing.occurrence.occurrenceKey, editing.definition.version, changes, confirmed);
      } else if (editing.definition) await updateEvent(editing.definition.id, editing.definition.version, draft, confirmed);
      else await createEvent(draft, attempt.current.key, confirmed);
      setEditing(null); setStale(false); refresh();
    } catch (failure) {
      const body = failure instanceof ApiError ? failure.body as PlanningFailure | null : null;
      if (body?.code === 'planning_conflict' && body.confirmationToken) {
        const lines = (body.conflicts ?? []).map((conflict) => `${nameFor(conflict.personId)} · ${formatInterval(conflict.start, conflict.end)}`).join('\n');
        setConfirmation({ title: t('dayPlanning.conflictTitle'), text: `${t('dayPlanning.conflictHint')}\n${lines}`, label: t('dayPlanning.saveDespiteConflict'),
          action: () => void save(draft, { ...confirmed, conflictConfirmation: body.confirmationToken }) });
      } else if (body?.code === 'exceptions_reset_required') {
        setConfirmation({ title: t('dayPlanning.resetTitle'), text: t('dayPlanning.resetHint'), label: t('dayPlanning.confirmReset'), action: () => void save(draft, { ...confirmed, resetExceptions: true }) });
      } else { setError(message(failure)); setStale(failure instanceof ApiError && failure.status === 412); }
    } finally { setSaving(false); }
  };
  const perform = async (action: (confirmed: Confirmation) => Promise<unknown>, confirmed: Confirmation = {}) => {
    setSaving(true); setError('');
    try { await action(confirmed); refresh(); }
    catch (failure) {
      const body = failure instanceof ApiError ? failure.body as PlanningFailure | null : null;
      if (body?.code === 'planning_conflict' && body.confirmationToken) setConfirmation({ title: t('dayPlanning.conflictTitle'), text: `${t('dayPlanning.conflictHint')}\n${(body.conflicts ?? []).map((conflict) => `${nameFor(conflict.personId)} · ${formatInterval(conflict.start, conflict.end)}`).join('\n')}`, label: t('dayPlanning.saveDespiteConflict'), action: () => void perform(action, { conflictConfirmation: body.confirmationToken }) });
      else { setError(message(failure)); if (failure instanceof ApiError && [403, 404, 412].includes(failure.status)) refresh(false); }
    }
    finally { setSaving(false); }
  };
  const restore = async (key: string, confirmed: Confirmation = {}) => {
    if (!editing?.definition) return;
    const sequence = ++request.current;
    setSaving(true); setError('');
    try {
      const definition = await restoreOccurrence(editing.definition.id, key, editing.definition.version, confirmed);
      if (focused.current && sequence === request.current) setEditing({ ...editing, key: makeRequestKey(), draft: draftFromDefinition(definition), definition });
    } catch (failure) {
      const body = failure instanceof ApiError ? failure.body as PlanningFailure | null : null;
      if (body?.code === 'planning_conflict' && body.confirmationToken) setConfirmation({ title: t('dayPlanning.conflictTitle'), text: `${t('dayPlanning.conflictHint')}\n${(body.conflicts ?? []).map((conflict) => `${nameFor(conflict.personId)} · ${formatInterval(conflict.start, conflict.end)}`).join('\n')}`, label: t('dayPlanning.saveDespiteConflict'), action: () => void restore(key, { conflictConfirmation: body.confirmationToken }) });
      else { setError(message(failure)); setStale(failure instanceof ApiError && failure.status === 412); }
    } finally { setSaving(false); }
  };
  const remove = (event: Occurrence, only: boolean) => setConfirmation({ title: t('dayPlanning.deleteTitle'), text: t(only ? 'dayPlanning.deleteOccurrenceHint' : 'dayPlanning.deleteSeriesHint'),
    action: () => void perform(() => only ? cancelOccurrence(event.id, event.occurrenceKey, event.version) : removeEvent(event.id, event.version)) });
  const search = async () => {
    setSuggestions(null); setError('');
    if (!isDay(date) || !isDay(planEnd) || planEnd < date || !isTime(windowStart) || !isTime(windowEnd) || windowStart >= windowEnd || !/^\d+$/.test(duration) || Number(duration) < 1) { setError(t('dayPlanning.invalidPlanning')); return; }
    setLoading(true);
    const sequence = ++request.current;
    try {
      const next = await findSuggestions({ teamId, personIds: [...new Set([currentUser, ...people])], from: localInstant(date, '00:00', zone).toISOString(), to: localInstant(shiftDay(planEnd, 1), '00:00', zone).toISOString(), durationMinutes: Number(duration), windowStart, windowEnd, timeZone: zone });
      if (!next.coverage.complete) throw new Error('incomplete');
      if (sequence === request.current && focused.current) setSuggestions(next);
    } catch (failure) { if (sequence === request.current) setError(message(failure)); }
    finally { if (sequence === request.current) setLoading(false); }
  };
  const formatInterval = (start: string, end: string, timeZone = zone) => {
    const formatter = new Intl.DateTimeFormat(i18n.language, { timeZone, day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });
    return `${formatter.format(new Date(start))} – ${formatter.format(new Date(end))}`;
  };
  const dateTitle = (value: string) => new Intl.DateTimeFormat(i18n.language, { weekday: 'long', day: 'numeric', month: 'long' }).format(new Date(`${value}T12:00`));
  const plannerField = (key: string, value: string, change: (value: string) => void) => <TextInput mode="outlined" label={t(`dayPlanning.${key}`)} accessibilityLabel={t(`dayPlanning.${key}`)} value={value} onChangeText={(next) => { change(next); setSuggestions(null); request.current += 1; setLoading(false); }} />;
  return <SafeAreaView edges={['left', 'right']} style={{ flex: 1, backgroundColor: ground }}>
    <KeyboardAvoidingView style={{ flex: 1 }} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
      <ScrollView ref={scroll} nestedScrollEnabled keyboardShouldPersistTaps="handled" contentInsetAdjustmentBehavior="automatic" contentContainerStyle={{ padding: 16, gap: 16, paddingBottom: 36 }}
        refreshControl={<RefreshControl refreshing={loading} onRefresh={() => refresh()} />}>
        {!!error && <View><Text accessibilityRole="alert" style={{ color: theme.colors.error }}>{error}</Text>{!editing && <Button onPress={() => refresh()}>{t('common.retry')}</Button>}</View>}
        {stale && editing?.occurrence && <Button onPress={() => void beginEdit(editing.occurrence!, editing.occurrenceOnly)}>{t('dayPlanning.reloadEditor')}</Button>}
        {editing ? <EventEditor key={editing.key} exceptions={editing.occurrenceOnly ? undefined : editing.definition?.exceptions} onRestore={(key) => void restore(key)} initial={editing.draft} teams={teams} userId={currentUser} occurrenceOnly={editing.occurrenceOnly}
          saving={saving} onSave={(draft) => void save(draft)} onCancel={() => { setEditing(null); setError(''); setStale(false); }} />
          : tagManager ? <TagManager tags={tags} team={teams.find((team) => team.id === teamId)} onChanged={reloadTags} onClose={() => { setTagManager(false); refresh(); }} />
          : selected ? <Card testID="day-event-detail"><Card.Content style={{ gap: 12 }}>
            <Text variant="headlineSmall" accessibilityRole="header">{selected.title}</Text>
            <Text>{selected.allDay ? `${t('dayPlanning.allDay')} · ` : ''}{selected.allDay ? `${localParts(selected.start, selected.timeZone).date} – ${shiftDay(localParts(selected.end, selected.timeZone).date, -1)}` : formatInterval(selected.start, selected.end, selected.timeZone)}</Text>
            <Text>{selected.timeZone}</Text><Text>{t(selected.visibility === 'PRIVATE' ? 'dayPlanning.private' : 'dayPlanning.shared')}</Text>
            {selected.description ? <Text selectable>{selected.description}</Text> : null}{selected.location ? <Text selectable>{selected.location}</Text> : null}
            <Text>{selected.participants.map((person) => `${nameFor(person.personId)}${person.status === 'DECLINED' ? ` (${t('dayPlanning.declined')})` : ''}`).join(', ')}</Text>
            <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: 6 }}>{selected.tags.map((tag) => <Chip key={tag.id}>{tag.name}</Chip>)}</View>
            {!selected.blocksTime && <Text>{t('dayPlanning.doesNotBlock')}</Text>}
            {selected.canEdit && <>
              {selected.recurring && <Button disabled={saving} onPress={() => void beginEdit(selected, true)}>{t('dayPlanning.editOccurrence')}</Button>}
              <Button disabled={saving} mode="contained-tonal" onPress={() => void beginEdit(selected, false)}>{t(selected.recurring ? 'dayPlanning.editSeries' : 'common.edit')}</Button>
              {selected.recurring && <Button disabled={saving} onPress={() => remove(selected, true)}>{t('dayPlanning.deleteOccurrence')}</Button>}
              <Button disabled={saving} onPress={() => remove(selected, false)}>{t(selected.recurring ? 'dayPlanning.deleteSeries' : 'common.delete')}</Button>
            </>}
            {selected.canChangeParticipation && <>
              {selected.recurring && <Button disabled={saving} onPress={() => void perform((confirmed) => changeParticipation(selected.id, selected.participation === 'DECLINED' ? 'INCLUDED' : 'DECLINED', selected.occurrenceKey, confirmed))}>
                {t(selected.participation === 'DECLINED' ? 'dayPlanning.rejoinOccurrence' : 'dayPlanning.declineOccurrence')}</Button>}
              <Button disabled={saving} onPress={() => void perform((confirmed) => changeParticipation(selected.id, selected.participation === 'DECLINED' ? 'INCLUDED' : 'DECLINED', null, confirmed))}>
                {t(selected.participation === 'DECLINED' ? 'dayPlanning.rejoin' : 'dayPlanning.decline')}</Button>
            </>}
            <Button onPress={() => setSelected(null)}>{t('dayPlanning.backToCalendar')}</Button>
          </Card.Content></Card>
          : <>
            <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: 6 }}>
              {['MINE', 'TEAM', 'PLAN'].map((value) => <Chip key={value} selected={view === value} onPress={() => { setView(value); setPeople((current) => current.length ? current : [currentUser]); setSuggestions(null); setLoading(false); }}>{t(`dayPlanning.views.${value}`)}</Chip>)}
            </View>
            <Button accessibilityLabel={t('dayPlanning.newEvent')} mode="contained" icon="plus" disabled={saving || !isDay(date)} onPress={() => beginCreate()}>{t('dayPlanning.newEvent')}</Button>
            {!!scopeError && <><Text accessibilityRole="alert">{scopeError}</Text><Button onPress={() => setRevision((value) => value + 1)}>{t('common.retry')}</Button></>}
            <ChoicePicker label={t('dayPlanning.team')} value={teamId ?? ''} options={[{ value: '', label: t('dayPlanning.noTeam') }, ...teams.map((team) => ({ value: team.id, label: team.name }))]} onChange={chooseTeam} />
            {view !== 'MINE' && <View>
              {view === 'PLAN' && <Text>{t('dayPlanning.authorIncluded')}</Text>}
              {members.map((member) => <PlanningCheckbox key={member.userId} label={member.userName} accessibilityLabel={t('dayPlanning.showPerson', { name: member.userName })}
                status={people.includes(member.userId) || (view === 'PLAN' && member.userId === currentUser) ? 'checked' : 'unchecked'} disabled={view === 'PLAN' && member.userId === currentUser} onPress={() => personToggle(member.userId)} />)}
              {view === 'TEAM' && (!teamId || !people.length) && <Text>{t('dayPlanning.selectPeople')}</Text>}
            </View>}
            {view !== 'PLAN' && <>
              <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: 6 }}>
                {(['LIST', 'WEEK'] as const).map((value) => <Chip key={value} accessibilityLabel={t(`dayPlanning.display.${value}`)} accessibilityState={{ selected: display === value }} selected={display === value} onPress={() => setDisplay(value)}>{t(`dayPlanning.display.${value}`)}</Chip>)}
              </View>
              <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: 6 }}>
                {display === 'LIST' && <><Chip selected={days === 1} onPress={() => setDays(1)}>{t('dayPlanning.day')}</Chip><Chip selected={days === 7} onPress={() => setDays(7)}>{t('dayPlanning.week')}</Chip></>}
                <Button onPress={() => setDate(dayString(new Date()))}>{t('dayPlanning.today')}</Button>
              </View>
              <View style={{ flexDirection: 'row', alignItems: 'center' }}>
                <IconButton icon="chevron-left" accessibilityLabel={t('dayPlanning.previous')} disabled={!isDay(date)} onPress={() => setDate(shiftDay(date, display === 'WEEK' ? -7 : -days))} />
                <Text style={{ flex: 1 }} variant="titleMedium">{isDay(date) ? display === 'WEEK' ? `${dateTitle(mondayOf(date))} – ${dateTitle(shiftDay(mondayOf(date), 6))}` : dateTitle(date) : date}</Text>
                <IconButton icon="chevron-right" accessibilityLabel={t('dayPlanning.next')} disabled={!isDay(date)} onPress={() => setDate(shiftDay(date, display === 'WEEK' ? 7 : days))} />
              </View>
            </>}
            {plannerField('calendarDate', date, setDate)}
            {view === 'PLAN' ? <>
              {plannerField('planningEnd', planEnd, setPlanEnd)}{plannerField('duration', duration, setDuration)}
              {plannerField('windowStart', windowStart, setWindowStart)}{plannerField('windowEnd', windowEnd, setWindowEnd)}{plannerField('timeZone', zone, setZone)}
              <Text>{t('dayPlanning.plannerHint')}</Text>
              <Button mode="contained" onPress={() => void search()} loading={loading} disabled={loading || !!scopeError}>{t('dayPlanning.findSlots')}</Button>
              {suggestions && <View testID="day-suggestions" style={{ gap: 12 }}>
                {!suggestions.slots.length && <Text>{t('dayPlanning.noSlots')}</Text>}
                {suggestions.slots.map((slot) => <Card key={slot.start}><Card.Content><Text>{formatInterval(slot.start, slot.end)}</Text></Card.Content><Card.Actions><Button onPress={() => beginCreate(slot)}>{t('dayPlanning.chooseSlot')}</Button></Card.Actions></Card>)}
                <Text variant="titleMedium">{t('dayPlanning.availability')}</Text>
                {suggestions.personIds.map((id) => <View key={id} style={{ gap: 4 }}><Text variant="titleSmall">{nameFor(id)}</Text>
                  {suggestions.busy.filter((busy) => busy.personId === id).map((busy, index) => <Text key={index}>{t('dayPlanning.busy')} · {formatInterval(busy.start, busy.end)}</Text>)}
                  {!suggestions.busy.some((busy) => busy.personId === id) && <Text>{t('dayPlanning.noKnownBusy')}</Text>}
                </View>)}
              </View>}
            </> : <>
              <Text variant="bodySmall">{zone}</Text>
              <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: 6 }}>
                <Chip selected={!tagIds.length} onPress={() => setTagIds([])}>{t('dayPlanning.allTags')}</Chip>
                {tags.map((tag) => <Chip key={tag.id} selected={tagIds.includes(tag.id)} onPress={() => setTagIds((current) => current.includes(tag.id) ? current.filter((id) => id !== tag.id) : [...current, tag.id])}>{tag.name}</Chip>)}
              </View>
              {!!tagIds.length && <Text variant="bodySmall">{t('dayPlanning.filterHint')}</Text>}
              <Button accessibilityLabel={t('dayPlanning.manageTags')} icon="tag-outline" onPress={() => setTagManager(true)}>{t('dayPlanning.manageTags')}</Button>
              {loading && <ActivityIndicator />}
              {calendar && display === 'WEEK' && isDay(date) && <WeekCalendar calendar={calendar} date={date} zone={zone} nameFor={nameFor} onOpen={openDetails} />}
              {calendar && display === 'LIST' && Array.from({ length: days }, (_, offset) => shiftDay(date, offset)).map((day) => {
                const range = rangeFor(day, 1, zone);
                const rows = [
                  ...calendar.events.map((event) => ({ start: event.start, end: event.end, event })),
                  ...calendar.busy.map((busy) => ({ start: busy.start, end: busy.end, busy })),
                ].filter((item) => Date.parse(item.start) < Date.parse(range.to) && Date.parse(item.end) > Date.parse(range.from)).sort((a, b) => Date.parse(a.start) - Date.parse(b.start));
                return <View key={day} style={{ gap: 10 }} testID={`day-agenda-${day}`}>
                  {days > 1 && <Text variant="titleLarge">{dateTitle(day)}</Text>}
                  {!rows.length && <Text>{t('dayPlanning.empty')}</Text>}
                  {rows.map((row, index) => 'event' in row ? <AgendaEvent key={`${row.event.id}-${row.event.occurrenceKey}`} event={row.event} onOpen={openDetails} formatInterval={formatInterval} /> : <Card key={`busy-${row.busy.personId}-${index}`} style={{ backgroundColor: theme.colors.surfaceVariant }} testID="day-busy">
                    <Card.Content style={{ gap: 6 }}><Text variant="titleMedium">{t('dayPlanning.busy')} · {nameFor(row.busy.personId)}</Text><Text>{formatInterval(row.start, row.end)}</Text><Text variant="bodySmall">{t('dayPlanning.busyHint')}</Text></Card.Content>
                  </Card>)}
                </View>;
              })}
            </>}
          </>}
      </ScrollView>
    </KeyboardAvoidingView>
    <Portal><Dialog visible={!!confirmation} onDismiss={() => setConfirmation(null)}>
      <Dialog.Title>{confirmation?.title}</Dialog.Title><Dialog.ScrollArea><ScrollView style={{ maxHeight: 300 }}><Text style={{ paddingVertical: 16 }}>{confirmation?.text}</Text></ScrollView></Dialog.ScrollArea>
      <Dialog.Actions style={{ flexWrap: 'wrap' }}><Button onPress={() => setConfirmation(null)}>{t('common.cancel')}</Button><Button onPress={() => { const action = confirmation?.action; setConfirmation(null); action?.(); }}>{confirmation?.label ?? t('common.confirm')}</Button></Dialog.Actions>
    </Dialog></Portal>
  </SafeAreaView>;
}

function AgendaEvent({ event, onOpen, formatInterval }: { event: Occurrence; onOpen: (event: Occurrence) => void; formatInterval: (start: string, end: string) => string }) {
  const { t } = useTranslation();
  return <Card onPress={() => onOpen(event)} testID={`day-event-${event.id}`}>
    <Card.Content style={{ gap: 6 }}>
      <Text variant="titleMedium">{event.title}</Text>
      <Text>{event.allDay ? t('dayPlanning.allDay') : formatInterval(event.start, event.end)}</Text>
      <Text variant="bodySmall">{t(event.visibility === 'PRIVATE' ? 'dayPlanning.private' : 'dayPlanning.shared')}{event.recurring ? ` · ${t('dayPlanning.recurring')}` : ''}</Text>
      {event.participation === 'DECLINED' && <Text>{t('dayPlanning.declined')}</Text>}
      <Text>{event.tags.map((tag) => tag.name).join(' · ')}</Text>
    </Card.Content>
  </Card>;
}

import { subscribeCalendarChanges } from '@/day-planning/changes';
import { useFocusEffect } from 'expo-router';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { AppState, KeyboardAvoidingView, Platform, RefreshControl, ScrollView, View } from 'react-native';
import { ActivityIndicator, Button, Card, Chip, Dialog, FAB, Icon, IconButton, Portal, SegmentedButtons, Text, TextInput, useTheme } from 'react-native-paper';
import { SafeAreaView } from 'react-native-safe-area-context';

import { ApiError } from '@/api/client';
import { cancelOccurrence, changeOccurrence, changeParticipation, createEvent, findSuggestions, listCalendarTags, readCalendar, readEvent, readOccurrence, removeEvent, restoreOccurrence, updateEvent,
  type Busy, type Calendar, type CalendarTag, type Confirmation, type EventDefinition, type EventDraft, type Occurrence, type PlanningFailure, type Suggestions } from '@/api/day-planning';
import { listMembers, listTeams, type Member, type Team } from '@/api/teams';
import { useAuth } from '@/auth/auth-context';
import ChoicePicker from '@/components/action-plans/choice-picker';
import ChoiceChip from '@/components/day-planning/choice-chip';
import EventEditor from '@/components/day-planning/event-editor';
import PlanningDateTimeField from '@/components/day-planning/date-time-field';
import TagManager from '@/components/day-planning/tag-manager';
import { TagColorDot } from '@/components/day-planning/tag-colors';
import WeekCalendar from '@/components/day-planning/week-calendar';
import { mondayOf } from '@/day-planning/week-layout';
import { dayString, isDay, shiftDay } from '@/dates';
import { deviceTimeZone, draftFromDefinition, draftFromOccurrence, isTime, localInstant, localParts, makeRequestKey, rangeFor } from '@/day-planning/time';
import { useScreenBackground } from '@/personalisation/use-screen-background';

type PlanView = 'MINE' | 'TEAM' | 'PLAN';
type Editing = { key: string; draft: EventDraft; definition?: EventDefinition; occurrence?: Occurrence; occurrenceOnly: boolean };
type Confirm = { title: string; text: string; label?: string; action: () => void };
const loadTags = async (teams: Team[]) => {
  const catalogs = await Promise.all([listCalendarTags(null), ...teams.map((team) => listCalendarTags(team.id))]);
  return [...new Map(catalogs.flat().map((tag) => [tag.id, tag])).values()].sort((a, b) => a.name.localeCompare(b.name));
};
const capitalize = (text: string) => text.charAt(0).toUpperCase() + text.slice(1);

export default function DayPlanningScreen() {
  const { t, i18n } = useTranslation();
  const { user } = useAuth();
  const theme = useTheme();
  const ground = useScreenBackground();
  const [teams, setTeams] = useState<Team[]>([]);
  const [chosenTeam, setChosenTeam] = useState<string | null>(null);
  const [rosters, setRosters] = useState<Record<string, Member[]>>({});
  const [picked, setPicked] = useState<{ teamId: string | null; ids: string[] } | null>(null);
  const [tags, setTags] = useState<CalendarTag[]>([]);
  const [tagIds, setTagIds] = useState<string[]>([]);
  const [date, setDate] = useState(dayString(new Date()));
  const [display, setDisplay] = useState<'DAY' | 'WEEK'>('DAY');
  const [view, setView] = useState<PlanView>('MINE');
  const [calendar, setCalendar] = useState<Calendar | null>(null);
  const [suggestions, setSuggestions] = useState<Suggestions | null>(null);
  const [showAvailability, setShowAvailability] = useState(false);
  const [planEnd, setPlanEnd] = useState(shiftDay(date, 6));
  const [windowStart, setWindowStart] = useState('08:00');
  const [windowEnd, setWindowEnd] = useState('20:00');
  const [duration, setDuration] = useState('60');
  const [zone, setZone] = useState(deviceTimeZone);
  const [zoneMenu, setZoneMenu] = useState(false);
  const [error, setError] = useState('');
  const [scopeError, setScopeError] = useState('');
  const [loading, setLoading] = useState(false);
  const [saving, setSaving] = useState(false);
  const [selected, setSelected] = useState<Occurrence | null>(null);
  const [scope, setScope] = useState<'OCCURRENCE' | 'SERIES'>('OCCURRENCE');
  const [organizer, setOrganizer] = useState<{ personId: string; name: string } | null>(null);
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
  const teamId = teams.some((team) => team.id === chosenTeam) ? chosenTeam : (teams.find((team) => (rosters[team.id] ?? []).some((member) => member.userId !== currentUser)) ?? teams[0])?.id ?? null;
  const members = teamId ? rosters[teamId] ?? [] : [];
  const directory = useMemo(() => Object.values(rosters).flat(), [rosters]);
  const people = (picked?.teamId === teamId ? picked.ids : members.map((member) => member.userId)).filter((id) => members.some((member) => member.userId === id));
  const peopleKey = people.join(',');
  const teamKey = view === 'MINE' ? '' : `${teamId ?? ''}|${peopleKey}`;
  const nameFor = (id: string) => id === currentUser ? user?.name ?? t('dayPlanning.me') : directory.find((member) => member.userId === id)?.userName ?? t('dayPlanning.teamMember');
  const message = (failure: unknown) => {
    if (failure instanceof ApiError) {
      if (failure.status === 412) return t('dayPlanning.stale');
      if ([403, 404].includes(failure.status)) return t('dayPlanning.accessLost');
      const body = failure.body as PlanningFailure | null;
      if (failure.status === 422 && body?.message) return body.message;
    }
    return t('dayPlanning.requestError');
  };
  const reloadTags = useCallback(async () => {
    const next = await loadTags(teams);
    setTags(next);
    setTagIds((current) => current.filter((id) => next.some((tag) => tag.id === id)));
  }, [teams]);
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
    void Promise.resolve().then(() => { if (active) setScopeError(''); });
    listTeams().then(async (nextTeams) => {
      const [nextTags, nextRosters] = await Promise.all([loadTags(nextTeams), Promise.all(nextTeams.map((team) => listMembers(team.id)))]);
      if (!active) return;
      setTeams(nextTeams);
      setTags(nextTags);
      setTagIds((current) => current.filter((id) => nextTags.some((tag) => tag.id === id)));
      setRosters(Object.fromEntries(nextTeams.map((team, index) => [team.id, nextRosters[index]])));
      if (!nextTeams.length) setView('MINE');
    }).catch(() => { if (active) setScopeError(t('dayPlanning.scopeError')); });
    return () => { active = false; };
  }, [revision, t]);
  useEffect(() => {
    const sequence = ++request.current;
    void Promise.resolve().then(() => { if (sequence === request.current) { setCalendar(null); setSuggestions(null); setLoading(false); } });
    if (!focused.current || view === 'PLAN' || !isDay(date)) return;
    const [queryTeam, queryPeople] = teamKey.split('|');
    const personIds = queryPeople ? queryPeople.split(',') : [];
    if (view === 'TEAM' && (!queryTeam || !personIds.length)) return;
    let range: { from: string; to: string };
    try { range = rangeFor(display === 'WEEK' ? mondayOf(date) : date, display === 'WEEK' ? 7 : 1, zone); } catch { void Promise.resolve().then(() => { if (sequence === request.current) setError(t('dayPlanning.invalidTime')); }); return; }
    void Promise.resolve().then(() => { if (sequence === request.current) setLoading(true); });
    readCalendar(range.from, range.to, view === 'MINE' ? null : queryTeam, view === 'MINE' ? [] : personIds, tagIds)
      .then((next) => { if (sequence === request.current) { if (!next.coverage.complete) throw new Error('incomplete'); setCalendar(next); } })
      .catch(() => { if (sequence === request.current) setError(t('dayPlanning.loadError')); })
      .finally(() => { if (sequence === request.current) setLoading(false); });
  }, [date, display, view, teamKey, tagIds, zone, revision, t]);
  useEffect(() => { scroll.current?.scrollTo({ y: 0, animated: false }); }, [selected, editing, tagManager]);
  const chooseView = (next: PlanView) => { setView(next); setSuggestions(null); setLoading(false); };
  const chooseTeam = (value: string) => { setChosenTeam(value || null); setSuggestions(null); };
  const personToggle = (id: string) => { setPicked({ teamId, ids: people.includes(id) ? people.filter((person) => person !== id) : [...people, id] }); setSuggestions(null); };
  const refresh = (clearError = true) => { if (clearError) setError(''); setSelected(null); setSuggestions(null); setRevision((value) => value + 1); };
  const beginCreate = (slot?: { start: string; end: string }) => {
    try { localInstant(date, '09:00', zone); } catch { setError(t('dayPlanning.invalidTime')); return; }
    const start = slot ? localParts(slot.start, zone) : { date, time: '09:00' };
    const eventTeam = view === 'MINE' ? null : teamId;
    setError(''); setStale(false); attempt.current = { payload: '', key: makeRequestKey() };
    setEditing({ key: makeRequestKey(), occurrenceOnly: false, draft: { title: '', description: '', location: '', teamId: eventTeam, visibility: 'PRIVATE',
      schedule: { kind: 'TIMED', localStart: `${start.date}T${start.time}`, durationMinutes: slot ? (Date.parse(slot.end) - Date.parse(slot.start)) / 60000 : 60, timeZone: zone },
      recurrence: null, participantIds: [...new Set([currentUser, ...(eventTeam && view === 'PLAN' ? people : [])])], tagIds: [], blocksTime: true, ownerParticipates: true } });
  };
  useEffect(() => {
    const event = selected && !selected.participantIds.includes(selected.ownerId) ? selected : null;
    if (!event?.teamId || directory.some((member) => member.userId === event.ownerId)) return undefined;
    let active = true;
    listMembers(event.teamId)
      .then((roster) => { if (active) setOrganizer({ personId: event.ownerId, name: roster.find((member) => member.userId === event.ownerId)?.userName ?? '' }); })
      .catch(() => {});
    return () => { active = false; };
  }, [selected, directory]);
  const openDetails = useCallback(async (event: Occurrence) => {
    const sequence = ++request.current;
    setSelected(null); setError(''); setSaving(true); setScope('OCCURRENCE');
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
        const changes = Object.fromEntries(Object.entries(draft).filter(([key, value]) => !['teamId', 'recurrence', 'ownerParticipates'].includes(key) && JSON.stringify(value) !== JSON.stringify(editing.draft[key as keyof EventDraft])));
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
  const formatHours = (start: string, end: string, timeZone = zone) => {
    if (localParts(start, timeZone).date !== localParts(end, timeZone).date) return formatInterval(start, end, timeZone);
    const formatter = new Intl.DateTimeFormat(i18n.language, { timeZone, hour: '2-digit', minute: '2-digit' });
    return `${formatter.format(new Date(start))}–${formatter.format(new Date(end))}`;
  };
  const dateTitle = (value: string) => new Intl.DateTimeFormat(i18n.language, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }).format(new Date(`${value}T12:00`));
  const when = (event: Occurrence) => {
    const start = localParts(event.start, event.timeZone).date;
    const end = event.allDay ? shiftDay(localParts(event.end, event.timeZone).date, -1) : localParts(event.end, event.timeZone).date;
    if (event.allDay) return `${capitalize(dateTitle(start))}${end !== start ? ` – ${dateTitle(end)}` : ''} · ${t('dayPlanning.allDay')}`;
    return start === end ? `${capitalize(dateTitle(start))} · ${formatHours(event.start, event.end, event.timeZone)}` : formatInterval(event.start, event.end, event.timeZone);
  };
  const rangeTitle = () => {
    if (!isDay(date)) return date;
    if (display === 'DAY') return capitalize(dateTitle(date));
    const format = new Intl.DateTimeFormat(i18n.language, { day: 'numeric', month: 'long', year: 'numeric', timeZone: 'UTC' });
    const [first, last] = [mondayOf(date), shiftDay(mondayOf(date), 6)].map((value) => new Date(`${value}T12:00:00Z`));
    return typeof format.formatRange === 'function' ? format.formatRange(first, last) : `${format.format(first)} – ${format.format(last)}`;
  };
  const changePlanner = (change: (value: string) => void) => (next: string) => { change(next); setSuggestions(null); request.current += 1; setLoading(false); };
  const plannerPicker = (key: string, value: string, change: (value: string) => void, mode: 'date' | 'time', minimumDate?: string) =>
    <PlanningDateTimeField label={t(`dayPlanning.${key}`)} mode={mode} value={value} onChange={changePlanner(change)} minimumDate={minimumDate} disabled={saving || (view === 'PLAN' && loading)} />;
  const zones = [...new Set([deviceTimeZone(), 'Europe/Warsaw', 'Europe/London', 'America/New_York', 'UTC'])];
  const browsing = !editing && !tagManager && !selected && view !== 'PLAN';
  const only = !!selected?.recurring && scope === 'OCCURRENCE';
  const agenda = () => {
    const range = rangeFor(date, 1, zone);
    const rows: ({ start: string; end: string; event: Occurrence } | { start: string; end: string; busy: Busy })[] = [
      ...(calendar?.events ?? []).map((event) => ({ start: event.start, end: event.end, event })),
      ...(calendar?.busy ?? []).map((busy) => ({ start: busy.start, end: busy.end, busy })),
    ].filter((item) => Date.parse(item.start) < Date.parse(range.to) && Date.parse(item.end) > Date.parse(range.from)).sort((a, b) => Date.parse(a.start) - Date.parse(b.start));
    return <View style={{ gap: 8 }} testID={`day-agenda-${date}`}>
      {!rows.length && <Text>{t(tagIds.length ? 'dayPlanning.emptyFiltered' : 'dayPlanning.empty')}</Text>}
      {rows.map((row, index) => 'event' in row
        ? <AgendaEvent key={`${row.event.id}-${row.event.occurrenceKey}`} event={row.event} hours={row.event.allDay ? t('dayPlanning.allDay') : formatHours(row.start, row.end)} names={view === 'TEAM' ? row.event.participantIds.map(nameFor).join(', ') : ''} onOpen={openDetails} />
        : <Card key={`busy-${row.busy.personId}-${index}`} mode="contained" style={{ backgroundColor: theme.colors.surfaceVariant }} testID="day-busy">
          <Card.Content style={{ gap: 2, paddingVertical: 10 }}>
            <Text variant="labelMedium" style={{ color: theme.colors.onSurfaceVariant }}>{formatHours(row.start, row.end)}</Text>
            <View style={{ flexDirection: 'row', alignItems: 'center', gap: 6 }}><Icon source="lock-outline" size={16} color={theme.colors.onSurfaceVariant} /><Text variant="titleMedium" style={{ flexShrink: 1 }}>{t('dayPlanning.busy')} · {nameFor(row.busy.personId)}</Text></View>
          </Card.Content>
        </Card>)}
    </View>;
  };
  return <SafeAreaView edges={['left', 'right']} style={{ flex: 1, backgroundColor: ground }}>
    <KeyboardAvoidingView style={{ flex: 1 }} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
      <ScrollView ref={scroll} nestedScrollEnabled keyboardShouldPersistTaps="handled" contentInsetAdjustmentBehavior="automatic" contentContainerStyle={{ padding: 16, gap: 12, paddingBottom: browsing && display === 'DAY' ? 88 : 36 }}
        refreshControl={<RefreshControl refreshing={loading} onRefresh={() => refresh()} />}>
        {!!error && <View><Text accessibilityRole="alert" style={{ color: theme.colors.error }}>{error}</Text>{!editing && <Button onPress={() => refresh()}>{t('common.retry')}</Button>}</View>}
        {stale && editing?.occurrence && <Button onPress={() => void beginEdit(editing.occurrence!, editing.occurrenceOnly)}>{t('dayPlanning.reloadEditor')}</Button>}
        {editing ? <EventEditor key={editing.key} exceptions={editing.occurrenceOnly ? undefined : editing.definition?.exceptions} onRestore={(key) => void restore(key)} initial={editing.draft} teams={teams} userId={currentUser} displayZone={zone} occurrenceOnly={editing.occurrenceOnly}
          saving={saving} onTagsChanged={() => setRevision((value) => value + 1)} onSave={(draft) => void save(draft)} onCancel={() => { setEditing(null); setError(''); setStale(false); }} />
          : tagManager ? <TagManager tags={tags} teams={teams} onChanged={reloadTags} onClose={() => { setTagManager(false); refresh(); }} />
          : selected ? <Card testID="day-event-detail"><Card.Content style={{ gap: 12 }}>
            <Text variant="headlineSmall" accessibilityRole="header">{selected.title}</Text>
            <Text variant="titleMedium">{when(selected)}</Text>
            <View style={{ flexDirection: 'row', flexWrap: 'wrap', columnGap: 16, rowGap: 6 }}>
              <Detail icon={selected.visibility === 'PRIVATE' ? 'lock-outline' : 'account-group-outline'} text={t(selected.visibility === 'PRIVATE' ? 'dayPlanning.private' : 'dayPlanning.shared')} />
              {selected.recurring && <Detail icon="repeat" text={t('dayPlanning.recurring')} />}
              {!!selected.location && <Detail icon="map-marker-outline" text={selected.location} />}
              {selected.timeZone !== zone && <Detail icon="earth" text={selected.timeZone} />}
            </View>
            {selected.description ? <Text selectable>{selected.description}</Text> : null}
            {!!selected.tags.length && <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: 6 }}>{selected.tags.map((tag) => <Chip key={tag.id} icon={() => <TagColorDot color={tag.color} />}>{tag.name}</Chip>)}</View>}
            <Text><Text style={{ fontWeight: '600' }}>{t('dayPlanning.participants')}: </Text>{selected.participants.map((person) => `${nameFor(person.personId)}${person.status === 'DECLINED' ? ` (${t('dayPlanning.declined')})` : ''}`).join(', ')}</Text>
            {!selected.participantIds.includes(selected.ownerId) && <Text>{t('dayPlanning.scheduledBy', { name: (organizer?.personId === selected.ownerId ? organizer.name : '') || nameFor(selected.ownerId) })}</Text>}
            {!selected.blocksTime && <Text>{t('dayPlanning.doesNotBlock')}</Text>}
            {selected.recurring && (selected.canEdit || selected.canChangeParticipation) && <View style={{ gap: 6 }}>
              <Text variant="labelLarge">{t('dayPlanning.scope')}</Text>
              <SegmentedButtons value={scope} onValueChange={(value) => setScope(value as 'OCCURRENCE' | 'SERIES')} buttons={(['OCCURRENCE', 'SERIES'] as const).map((value) => ({ value, label: t(`dayPlanning.scopes.${value}`), disabled: saving }))} />
            </View>}
            <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: 8 }}>
              {selected.canEdit && <><Button mode="contained" icon="pencil-outline" accessibilityLabel={t('common.edit')} disabled={saving} onPress={() => void beginEdit(selected, only)}>{t('common.edit')}</Button>
                <Button mode="outlined" disabled={saving} onPress={() => remove(selected, only)}>{t('common.delete')}</Button></>}
              {selected.canChangeParticipation && <Button mode="outlined" disabled={saving} onPress={() => void perform((confirmed) => changeParticipation(selected.id, selected.participation === 'DECLINED' ? 'INCLUDED' : 'DECLINED', only ? selected.occurrenceKey : null, confirmed))}>
                {t(selected.participation === 'DECLINED' ? 'dayPlanning.rejoin' : 'dayPlanning.decline')}</Button>}
            </View>
            <Button onPress={() => setSelected(null)}>{t('dayPlanning.backToCalendar')}</Button>
          </Card.Content></Card>
          : <>
            {!!teams.length && <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: 6 }}>
              {(['MINE', 'TEAM', 'PLAN'] as const).map((value) => <Chip key={value} selected={view === value} showSelectedCheck={false} onPress={() => chooseView(value)}>{t(`dayPlanning.views.${value}`)}</Chip>)}
            </View>}
            {!!scopeError && <View><Text accessibilityRole="alert">{scopeError}</Text><Button onPress={() => setRevision((value) => value + 1)}>{t('common.retry')}</Button></View>}
            {view !== 'PLAN' && <View style={{ gap: 4 }}>
              <PlanningDateTimeField label={t('dayPlanning.calendarDate')} mode="date" value={date} onChange={setDate} title={rangeTitle()} />
              <View style={{ flexDirection: 'row', alignItems: 'center', flexWrap: 'wrap', gap: 4 }}>
                <Button mode="outlined" compact onPress={() => setDate(dayString(new Date()))}>{t('dayPlanning.today')}</Button>
                <IconButton icon="chevron-left" accessibilityLabel={t('dayPlanning.previous')} disabled={!isDay(date)} onPress={() => setDate(shiftDay(date, display === 'WEEK' ? -7 : -1))} />
                <IconButton icon="chevron-right" accessibilityLabel={t('dayPlanning.next')} disabled={!isDay(date)} onPress={() => setDate(shiftDay(date, display === 'WEEK' ? 7 : 1))} />
                <SegmentedButtons density="small" style={{ marginLeft: 'auto', minWidth: 168 }} value={display} onValueChange={(value) => setDisplay(value as 'DAY' | 'WEEK')}
                  buttons={(['DAY', 'WEEK'] as const).map((value) => ({ value, label: t(value === 'DAY' ? 'dayPlanning.day' : 'dayPlanning.week') }))} />
              </View>
            </View>}
            {view !== 'MINE' && <View style={{ gap: 8 }}>
              {teams.length > 1 && <ChoicePicker label={t('dayPlanning.team')} value={teamId ?? ''} options={teams.map((team) => ({ value: team.id, label: team.name }))} onChange={chooseTeam} />}
              <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: 6 }}>
                {members.map((member) => <ChoiceChip key={member.userId} label={member.userName} accessibilityLabel={t('dayPlanning.showPerson', { name: member.userName })}
                  checked={people.includes(member.userId) || (view === 'PLAN' && member.userId === currentUser)} disabled={view === 'PLAN' && member.userId === currentUser} onPress={() => personToggle(member.userId)} />)}
              </View>
              {view === 'PLAN' && <Text variant="bodySmall">{t('dayPlanning.authorIncluded')}</Text>}
              {view === 'TEAM' && !!members.length && !people.length && <Text>{t('dayPlanning.selectPeople')}</Text>}
            </View>}
            {view === 'PLAN' ? <>
              {plannerPicker('searchFrom', date, setDate, 'date')}
              {plannerPicker('planningEnd', planEnd, setPlanEnd, 'date', date)}
              <TextInput mode="outlined" label={t('dayPlanning.duration')} accessibilityLabel={t('dayPlanning.duration')} keyboardType="number-pad" value={duration} onChangeText={changePlanner(setDuration)} disabled={saving || loading} />
              <View style={{ flexDirection: 'row', gap: 12 }}>
                <View style={{ flex: 1 }}>{plannerPicker('windowStart', windowStart, setWindowStart, 'time')}</View>
                <View style={{ flex: 1 }}>{plannerPicker('windowEnd', windowEnd, setWindowEnd, 'time')}</View>
              </View>
              <Button mode="contained" onPress={() => void search()} loading={loading} disabled={loading || !!scopeError}>{t('dayPlanning.findSlots')}</Button>
              {suggestions && <View testID="day-suggestions" style={{ gap: 12 }}>
                {!suggestions.slots.length && <Text>{t('dayPlanning.noSlots')}</Text>}
                {suggestions.slots.map((slot) => <Card key={slot.start} mode="outlined"><Card.Content><Text variant="titleMedium">{formatInterval(slot.start, slot.end)}</Text></Card.Content><Card.Actions><Button onPress={() => beginCreate(slot)}>{t('dayPlanning.chooseSlot')}</Button></Card.Actions></Card>)}
                <Button icon={showAvailability ? 'chevron-up' : 'chevron-down'} contentStyle={{ flexDirection: 'row-reverse' }} style={{ alignSelf: 'flex-start' }} accessibilityLabel={t('dayPlanning.availability')} accessibilityState={{ expanded: showAvailability }} onPress={() => setShowAvailability(!showAvailability)}>{t('dayPlanning.availability')}</Button>
                {showAvailability && suggestions.personIds.map((id) => <View key={id} style={{ gap: 4 }}><Text variant="titleSmall">{nameFor(id)}</Text>
                  {suggestions.busy.filter((busy) => busy.personId === id).map((busy, index) => <Text key={index}>{t('dayPlanning.busy')} · {formatInterval(busy.start, busy.end)}</Text>)}
                  {!suggestions.busy.some((busy) => busy.personId === id) && <Text>{t('dayPlanning.noKnownBusy')}</Text>}
                </View>)}
              </View>}
            </> : <>
              {!!tags.length && <View style={{ flexDirection: 'row', flexWrap: 'wrap', alignItems: 'center', gap: 6 }}>
                {tags.map((tag) => <Chip key={tag.id} accessibilityLabel={tag.name} icon={() => <TagColorDot color={tag.color} selected={tagIds.includes(tag.id)} />} showSelectedOverlay showSelectedCheck={false} selected={tagIds.includes(tag.id)} onPress={() => setTagIds((current) => current.includes(tag.id) ? current.filter((id) => id !== tag.id) : [...current, tag.id])}>{tag.name}</Chip>)}
                {!!tagIds.length && <Button compact onPress={() => setTagIds([])}>{t('dayPlanning.clearFilter')}</Button>}
                <IconButton icon="pencil-outline" size={20} accessibilityLabel={t('dayPlanning.manageTags')} onPress={() => setTagManager(true)} />
              </View>}
              {!!tagIds.length && <Text variant="bodySmall">{t('dayPlanning.filterHint')}</Text>}
              {loading && <ActivityIndicator />}
              {calendar && display === 'WEEK' && isDay(date) && <WeekCalendar calendar={calendar} date={date} zone={zone} nameFor={nameFor} onOpen={openDetails} />}
              {calendar && display === 'DAY' && agenda()}
            </>}
            <View style={{ gap: 4, borderTopWidth: 1, borderColor: theme.colors.outlineVariant, paddingTop: 8 }}>
              <Button compact icon="earth" style={{ alignSelf: 'flex-start' }} accessibilityLabel={`${t('dayPlanning.displayZone')}: ${zone}`} accessibilityState={{ expanded: zoneMenu }} onPress={() => setZoneMenu(!zoneMenu)}>{zone}</Button>
              {zoneMenu && <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: 6 }}>
                {zones.map((value) => <Chip key={value} selected={value === zone} showSelectedCheck={false} onPress={() => { setZone(value); setZoneMenu(false); }}>{value}</Chip>)}
              </View>}
              {view === 'TEAM' && <View style={{ flexDirection: 'row', alignItems: 'center', gap: 6 }}><Icon source="lock-outline" size={16} color={theme.colors.onSurfaceVariant} /><Text variant="bodySmall" style={{ flex: 1, color: theme.colors.onSurfaceVariant }}>{t('dayPlanning.privacyNote')}</Text></View>}
            </View>
          </>}
      </ScrollView>
    </KeyboardAvoidingView>
    {browsing && <FAB icon="plus" accessibilityLabel={t('dayPlanning.newEvent')} disabled={saving || !isDay(date)} onPress={() => beginCreate()} style={{ position: 'absolute', right: 16, bottom: 16 }} />}
    <Portal><Dialog visible={!!confirmation} onDismiss={() => setConfirmation(null)}>
      <Dialog.Title>{confirmation?.title}</Dialog.Title><Dialog.ScrollArea><ScrollView style={{ maxHeight: 300 }}><Text style={{ paddingVertical: 16 }}>{confirmation?.text}</Text></ScrollView></Dialog.ScrollArea>
      <Dialog.Actions style={{ flexWrap: 'wrap' }}><Button onPress={() => setConfirmation(null)}>{t('common.cancel')}</Button><Button onPress={() => { const action = confirmation?.action; setConfirmation(null); action?.(); }}>{confirmation?.label ?? t('common.confirm')}</Button></Dialog.Actions>
    </Dialog></Portal>
  </SafeAreaView>;
}

function Detail({ icon, text }: { icon: string; text: string }) {
  const theme = useTheme();
  return <View style={{ flexDirection: 'row', alignItems: 'center', gap: 6 }}><Icon source={icon} size={18} color={theme.colors.onSurfaceVariant} /><Text style={{ color: theme.colors.onSurfaceVariant }}>{text}</Text></View>;
}

function AgendaEvent({ event, hours, names, onOpen }: { event: Occurrence; hours: string; names: string; onOpen: (event: Occurrence) => void }) {
  const { t } = useTranslation();
  const theme = useTheme();
  const color = event.tags[0]?.color && /^#[a-f\d]{6}$/i.test(event.tags[0].color) ? event.tags[0].color : theme.colors.primary;
  const details = [...event.tags.map((tag) => tag.name), event.recurring ? t('dayPlanning.recurring') : '', names].filter(Boolean).join(' · ');
  return <Card mode="outlined" onPress={() => onOpen(event)} testID={`day-event-${event.id}`} style={{ borderLeftWidth: 4, borderLeftColor: color, opacity: event.participation === 'DECLINED' ? 0.7 : 1 }}>
    <Card.Content style={{ gap: 2, paddingVertical: 10 }}>
      <Text variant="labelMedium" style={{ color: theme.colors.onSurfaceVariant }}>{hours}</Text>
      <View style={{ flexDirection: 'row', alignItems: 'center', gap: 6 }}>{event.visibility === 'PRIVATE' && <Icon source="lock-outline" size={16} color={theme.colors.onSurfaceVariant} />}<Text variant="titleMedium" style={{ flexShrink: 1 }}>{event.title}</Text></View>
      {!!details && <Text variant="bodySmall" style={{ color: theme.colors.onSurfaceVariant }}>{details}</Text>}
      {event.participation === 'DECLINED' && <Text variant="bodySmall">{t('dayPlanning.declined')}</Text>}
    </Card.Content>
  </Card>;
}

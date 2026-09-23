import ChoiceChip from '@/components/day-planning/choice-chip';
import PlanningCheckbox from '@/components/day-planning/checkbox';
import PlanningDateTimeField from '@/components/day-planning/date-time-field';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { View } from 'react-native';
import { Button, Chip, HelperText, SegmentedButtons, Text, TextInput } from 'react-native-paper';

import { listCalendarTags, type CalendarTag, type EventDefinition, type EventDraft, type Schedule } from '@/api/day-planning';
import { listMembers, type Member, type Team } from '@/api/teams';
import ChoicePicker from '@/components/action-plans/choice-picker';
import TagCreator from '@/components/day-planning/tag-creator';
import { TagColorDot } from '@/components/day-planning/tag-colors';
import { isDay, shiftDay } from '@/dates';
import { localInstant, localInstants, localParts, utcOffsetFor } from '@/day-planning/time';

export default function EventEditor({ initial, teams, userId, displayZone, occurrenceOnly, saving, exceptions, onRestore, onSave, onCancel, onTagsChanged }: {
  initial: EventDraft; teams: Team[]; userId: string; displayZone: string; occurrenceOnly: boolean; saving: boolean;
  exceptions?: EventDefinition['exceptions']; onRestore: (key: string) => void;
  onSave: (draft: EventDraft) => void; onCancel: () => void; onTagsChanged: () => void;
}) {
  const { t } = useTranslation();
  const [draft, setDraft] = useState(initial);
  const schedule = initial.schedule;
  const initialStart = schedule.kind === 'TIMED' ? schedule.localStart.split('T') : [schedule.startDate, '09:00'];
  const initialEndInstant = schedule.kind === 'TIMED' ? new Date(localInstant(initialStart[0], initialStart[1], schedule.timeZone, schedule.utcOffset).getTime() + schedule.durationMinutes * 60000).toISOString() : null;
  const initialEnd = initialEndInstant ? localParts(initialEndInstant, schedule.timeZone)
    : { date: schedule.kind === 'ALL_DAY' ? shiftDay(schedule.endDate, -1) : initialStart[0], time: '10:00' };
  const [startDate, setStartDate] = useState(initialStart[0]);
  const [startTime, setStartTime] = useState(initialStart[1]);
  const [endDate, setEndDate] = useState(initialEnd.date);
  const [endTime, setEndTime] = useState(initialEnd.time);
  const [allDay, setAllDay] = useState(schedule.kind === 'ALL_DAY');
  const [zone, setZone] = useState(schedule.timeZone);
  const [utcOffset, setUtcOffset] = useState(schedule.kind === 'TIMED' ? schedule.utcOffset ?? '' : '');
  const [endUtcOffset, setEndUtcOffset] = useState(initialEndInstant ? utcOffsetFor(initialEndInstant, schedule.timeZone) : '');
  let endOffsets: string[] = [];
  try { endOffsets = localInstants(endDate, endTime, zone).map((instant) => utcOffsetFor(instant.toISOString(), zone)); } catch {}
  const selectedEndOffset = endOffsets.includes(endUtcOffset) ? endUtcOffset : endOffsets[0];
  let offsets: string[] = [];
  try { offsets = localInstants(startDate, startTime, zone).map((instant) => utcOffsetFor(instant.toISOString(), zone)); } catch {}
  const selectedOffset = offsets.includes(utcOffset) ? utcOffset : offsets[0];
  const [frequency, setFrequency] = useState(initial.recurrence?.frequency ?? 'ONCE');
  const [interval, setInterval] = useState(String(initial.recurrence?.interval ?? 1));
  const [byDay, setByDay] = useState(initial.recurrence?.byDay ?? [((new Date(`${initialStart[0]}T12:00`).getDay() + 6) % 7) + 1]);
  const [ending, setEnding] = useState(initial.recurrence?.until ? 'UNTIL' : initial.recurrence?.count ? 'COUNT' : 'NEVER');
  const [until, setUntil] = useState(initial.recurrence?.until ?? '');
  const [count, setCount] = useState(String(initial.recurrence?.count ?? 10));
  const [members, setMembers] = useState<Member[]>([]);
  const [tags, setTags] = useState<CalendarTag[]>([]);
  const [error, setError] = useState('');
  const [loadingScope, setLoadingScope] = useState(false);
  const [scopeError, setScopeError] = useState(false);
  const [revision, setRevision] = useState(0);
  const [creatingTag, setCreatingTag] = useState(false);
  const [moreOpen, setMoreOpen] = useState(schedule.timeZone !== displayZone || !initial.blocksTime);
  const isTeamAdmin = teams.some((team) => team.id === draft.teamId && team.role === 'admin');
  const tagScopes: CalendarTag['scope'][] = draft.visibility === 'TEAM' ? (isTeamAdmin ? ['TEAM'] : []) : ['PERSONAL', ...(isTeamAdmin ? ['TEAM' as const] : [])];
  useEffect(() => {
    let active = true;
    void Promise.resolve().then(() => { if (active) { setLoadingScope(true); setScopeError(false); setMembers([]); setTags([]); } });
    Promise.all([draft.teamId ? listMembers(draft.teamId) : Promise.resolve([]), listCalendarTags(draft.teamId)])
      .then(([people, nextTags]) => { if (active) { setMembers(people); setTags(nextTags); } })
      .catch(() => { if (active) setScopeError(true); })
      .finally(() => { if (active) setLoadingScope(false); });
    return () => { active = false; };
  }, [draft.teamId, revision]);
  const change = <K extends keyof EventDraft>(key: K, value: EventDraft[K]) => setDraft((current) => ({ ...current, [key]: value }));
  const changeTeam = (teamId: string) => { setCreatingTag(false); setDraft((current) => ({ ...current, teamId: teamId || null,
    visibility: teamId ? current.visibility : 'PRIVATE', participantIds: [userId], ownerParticipates: true, tagIds: [] })); };
  const changeVisibility = (value: string) => { setCreatingTag(false); setDraft((current) => ({ ...current, visibility: value as EventDraft['visibility'], tagIds: value === 'TEAM' ? current.tagIds.filter((id) => tags.some((tag) => tag.id === id && tag.scope === 'TEAM')) : current.tagIds })); };
  const toggleSelf = () => setDraft((current) => current.ownerParticipates
    ? { ...current, ownerParticipates: false, participantIds: current.participantIds.filter((id) => id !== userId) }
    : { ...current, ownerParticipates: true, participantIds: [...new Set([userId, ...current.participantIds])] });
  const toggle = (key: 'tagIds' | 'participantIds', value: string) => change(key, draft[key].includes(value) ? draft[key].filter((id) => id !== value) : [...draft[key], value]);
  const invalidTags = draft.visibility === 'TEAM' && tags.some((tag) => draft.tagIds.includes(tag.id) && tag.scope === 'PERSONAL');
  const save = () => {
    if (creatingTag || saving || loadingScope || scopeError) return;
    setError('');
    try {
      if (!draft.title.trim()) throw new Error('titleRequired');
      if (!draft.ownerParticipates && !draft.participantIds.length) throw new Error('nobodyAttends');
      if (!isDay(startDate) || !isDay(endDate) || endDate < startDate) throw new Error('invalidTime');
      new Intl.DateTimeFormat('en', { timeZone: zone }).format();
      let nextSchedule: Schedule;
      if (allDay) nextSchedule = { kind: 'ALL_DAY', startDate, endDate: shiftDay(endDate, 1), timeZone: zone };
      else {
        const start = localInstant(startDate, startTime, zone, selectedOffset);
        const end = localInstant(endDate, endTime, zone, selectedEndOffset);
        const durationMinutes = (end.getTime() - start.getTime()) / 60000;
        if (durationMinutes <= 0) throw new Error('invalidTime');
        nextSchedule = { kind: 'TIMED', localStart: `${startDate}T${startTime}`, durationMinutes, timeZone: zone, ...(offsets.length > 1 ? { utcOffset: selectedOffset } : {}) };
      }
      if (invalidTags) throw new Error('personalTagsWarning');
      if (frequency !== 'ONCE' && (!/^\d+$/.test(interval) || Number(interval) < 1 || (frequency === 'WEEKLY' && !byDay.length))) throw new Error('invalidRecurrence');
      if (frequency !== 'ONCE' && ending === 'UNTIL' && (!isDay(until) || until < startDate)) throw new Error('invalidRecurrence');
      if (frequency !== 'ONCE' && ending === 'COUNT' && (!/^\d+$/.test(count) || Number(count) < 1)) throw new Error('invalidRecurrence');
      onSave({ ...draft, title: draft.title.trim(), schedule: nextSchedule,
        recurrence: frequency === 'ONCE' || occurrenceOnly ? null : {
          frequency: frequency as 'DAILY' | 'WEEKLY', interval: Number(interval), byDay: frequency === 'WEEKLY' ? byDay : [],
          until: ending === 'UNTIL' ? until : null, count: ending === 'COUNT' ? Number(count) : null,
        } });
    } catch (failure) {
      const key = failure instanceof RangeError ? 'invalidZone' : failure instanceof Error ? failure.message : 'invalidTime';
      if (key === 'invalidZone') setMoreOpen(true);
      setError(t(`dayPlanning.${key}`));
    }
  };
  const field = (label: string, value: string, set: (value: string) => void, extra = {}) =>
    <TextInput mode="outlined" label={t(`dayPlanning.${label}`)} accessibilityLabel={t(`dayPlanning.${label}`)} value={value} onChangeText={set} disabled={saving} {...extra} />;
  return <View testID="day-event-editor" style={{ gap: 12 }}>
    <Text variant="headlineSmall" accessibilityRole="header">{t(occurrenceOnly ? 'dayPlanning.editOccurrence' : 'dayPlanning.eventEditor')}</Text>
    {error ? <Text accessibilityRole="alert">{error}</Text> : null}
    {field('eventTitle', draft.title, (value) => change('title', value), { maxLength: 255 })}
    {!!teams.length && <ChoicePicker label={t('dayPlanning.eventTeam')} value={draft.teamId ?? ''} disabled={saving || occurrenceOnly}
      options={[{ value: '', label: t('dayPlanning.noTeam') }, ...teams.map((team) => ({ value: team.id, label: team.name }))]} onChange={changeTeam} />}
    <PlanningCheckbox label={t('dayPlanning.allDay')} accessibilityLabel={t('dayPlanning.allDay')} status={allDay ? 'checked' : 'unchecked'} onPress={() => setAllDay(!allDay)} disabled={saving} />
    <PlanningDateTimeField label={t('dayPlanning.startDate')} mode="date" value={startDate} disabled={saving} onChange={(value) => { setStartDate(value); if (endDate === startDate) setEndDate(value); }} />
    {!allDay && <PlanningDateTimeField label={t('dayPlanning.startTime')} mode="time" value={startTime} disabled={saving} onChange={setStartTime} />}
    <PlanningDateTimeField label={t('dayPlanning.endDate')} mode="date" value={endDate} minimumDate={startDate} disabled={saving} onChange={setEndDate} />
    {!allDay && <PlanningDateTimeField label={t('dayPlanning.endTime')} mode="time" value={endTime} disabled={saving} onChange={setEndTime} />}
    {allDay && <Text variant="bodySmall">{t('dayPlanning.inclusiveEnd')}</Text>}
    {!allDay && offsets.length > 1 && <ChoicePicker label={t('dayPlanning.clockChoice')} value={selectedOffset} disabled={saving} options={offsets.map((value, index) => ({ value, label: `${t(index === 0 ? 'dayPlanning.firstClockTime' : 'dayPlanning.secondClockTime')} (UTC${value})` }))} onChange={setUtcOffset} />}
    {!allDay && endOffsets.length > 1 && <ChoicePicker label={t('dayPlanning.endClockChoice')} value={selectedEndOffset} disabled={saving} options={endOffsets.map((value, index) => ({ value, label: `${t(index === 0 ? 'dayPlanning.firstClockTime' : 'dayPlanning.secondClockTime')} (UTC${value})` }))} onChange={setEndUtcOffset} />}
    {!occurrenceOnly && <>
      <ChoicePicker label={t('dayPlanning.recurrence')} value={frequency} disabled={saving}
        options={['ONCE', 'DAILY', 'WEEKLY'].map((value) => ({ value, label: t(`dayPlanning.frequency.${value}`) }))} onChange={setFrequency} />
      {frequency !== 'ONCE' && <>
        {field('interval', interval, setInterval, { keyboardType: 'number-pad' })}
        {frequency === 'WEEKLY' && <View style={{ flexDirection: 'row', gap: 6, flexWrap: 'wrap' }}>
          {[1, 2, 3, 4, 5, 6, 7].map((day) => <Chip key={day} mode={byDay.includes(day) ? 'flat' : 'outlined'} selected={byDay.includes(day)} accessibilityLabel={t(`dayPlanning.weekdays.${day}`)}
            onPress={() => setByDay(byDay.includes(day) ? byDay.filter((value) => value !== day) : [...byDay, day])}>{t(`dayPlanning.weekdays.${day}`)}</Chip>)}
        </View>}
        <ChoicePicker label={t('dayPlanning.repeatEnds')} value={ending} disabled={saving}
          options={['NEVER', 'UNTIL', 'COUNT'].map((value) => ({ value, label: t(`dayPlanning.ending.${value}`) }))} onChange={setEnding} />
        {ending === 'UNTIL' && <PlanningDateTimeField label={t('dayPlanning.until')} mode="date" value={until} minimumDate={startDate} disabled={saving} onChange={setUntil} />}
        {ending === 'COUNT' && field('count', count, setCount, { keyboardType: 'number-pad' })}
      </>}
    </>}
    {!!draft.teamId && <View style={{ gap: 8 }}>
      <Text variant="labelLarge">{t('dayPlanning.visibility')}</Text>
      <SegmentedButtons value={draft.visibility} onValueChange={changeVisibility}
        buttons={[{ value: 'PRIVATE', label: t('dayPlanning.private'), disabled: saving }, { value: 'TEAM', label: t('dayPlanning.shared'), disabled: saving }]} />
      <Text variant="bodySmall">{t(draft.visibility === 'PRIVATE' ? 'dayPlanning.privateHint' : 'dayPlanning.sharedHint')}</Text>
    </View>}
    {!!draft.teamId && <View style={{ gap: 8 }}>
      <Text variant="labelLarge">{t('dayPlanning.participants')}</Text>
      <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: 6 }}>
        {isTeamAdmin && !occurrenceOnly && <ChoiceChip label={t('dayPlanning.attendMyself')} accessibilityLabel={t('dayPlanning.attendMyself')} disabled={saving} checked={draft.ownerParticipates} onPress={toggleSelf} />}
        {members.filter((member) => member.userId !== userId).map((member) => <ChoiceChip key={member.userId}
          label={member.userName} accessibilityLabel={t('dayPlanning.invitePerson', { name: member.userName })} disabled={saving}
          checked={draft.participantIds.includes(member.userId)} onPress={() => toggle('participantIds', member.userId)} />)}
      </View>
      <Text variant="bodySmall">{t(isTeamAdmin && !occurrenceOnly ? 'dayPlanning.adminParticipationHint' : 'dayPlanning.participantsHint')}</Text>
    </View>}
    <Text variant="labelLarge">{t('dayPlanning.tags')}</Text>
    {scopeError && <><Text accessibilityRole="alert">{t('dayPlanning.scopeError')}</Text><Button onPress={() => setRevision((value) => value + 1)}>{t('common.retry')}</Button></>}
    <View style={{ flexDirection: 'row', gap: 6, flexWrap: 'wrap', alignItems: 'center' }}>
      {tags.filter((tag) => draft.visibility !== 'TEAM' || tag.scope === 'TEAM').map((tag) => <Chip key={tag.id} mode={draft.tagIds.includes(tag.id) ? 'flat' : 'outlined'} selected={draft.tagIds.includes(tag.id)} accessibilityLabel={tag.name} showSelectedCheck={false} icon={() => <TagColorDot color={tag.color} selected={draft.tagIds.includes(tag.id)} />} disabled={saving}
        onPress={() => toggle('tagIds', tag.id)}>{tag.name}</Chip>)}
      {!creatingTag && !!tagScopes.length && <Button icon="plus" compact accessibilityLabel={t('dayPlanning.newTag')} disabled={saving || loadingScope || scopeError} onPress={() => setCreatingTag(true)}>{t('dayPlanning.newTag')}</Button>}
    </View>
    {creatingTag && !!tagScopes.length && <TagCreator key={`${draft.teamId ?? ''}-${draft.visibility}`} teamId={draft.teamId} scopes={tagScopes} onCatalogChanged={onTagsChanged}
      onCreated={(tag) => { setTags((current) => [...current.filter((item) => item.id !== tag.id), tag]); setDraft((current) => ({ ...current, tagIds: [...new Set([...current.tagIds, tag.id])] })); setCreatingTag(false); }}
      onCancel={() => setCreatingTag(false)} />}
    {!tagScopes.length && <Text variant="bodySmall">{t('dayPlanning.teamTagAdminHint')}</Text>}
    {invalidTags && <HelperText type="error">{t('dayPlanning.personalTagsWarning')}</HelperText>}
    {field('description', draft.description, (value) => change('description', value), { multiline: true })}
    {field('location', draft.location, (value) => change('location', value))}
    <Button icon={moreOpen ? 'chevron-up' : 'chevron-down'} contentStyle={{ flexDirection: 'row-reverse' }} style={{ alignSelf: 'flex-start' }} accessibilityLabel={t('dayPlanning.moreOptions')} accessibilityState={{ expanded: moreOpen }} onPress={() => setMoreOpen(!moreOpen)}>{t('dayPlanning.moreOptions')}</Button>
    {moreOpen && <View style={{ gap: 12 }}>
      {field('timeZone', zone, setZone, { autoCapitalize: 'none', autoCorrect: false })}
      <Text variant="bodySmall">{t('dayPlanning.timeHint')}</Text>
      <PlanningCheckbox label={t('dayPlanning.blocksTime')} accessibilityLabel={t('dayPlanning.blocksTime')}
        status={draft.blocksTime ? 'checked' : 'unchecked'} disabled={saving} onPress={() => change('blocksTime', !draft.blocksTime)} />
    </View>}
    {!occurrenceOnly && !!Object.keys(exceptions ?? {}).length && <View style={{ gap: 6 }}><Text variant="titleMedium">{t('dayPlanning.exceptions')}</Text><Text>{t('dayPlanning.restoreHint')}</Text>{Object.entries(exceptions ?? {}).map(([key, exception]) => <View key={key}><Text>{key.replace('T', ' ')} · {t(exception.cancelled ? 'dayPlanning.cancelledException' : 'dayPlanning.changedException')}</Text><Button disabled={saving} onPress={() => onRestore(key)}>{t('dayPlanning.restoreOccurrence')}</Button></View>)}</View>}
    <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: 8 }}>
      <Button mode="contained" onPress={save} disabled={saving || loadingScope || scopeError || creatingTag} loading={saving}>{t('common.save')}</Button>
      <Button onPress={onCancel} disabled={saving}>{t('common.cancel')}</Button>
    </View>
  </View>;
}

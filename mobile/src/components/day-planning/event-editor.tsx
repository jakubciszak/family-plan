import PlanningCheckbox from '@/components/day-planning/checkbox';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { View } from 'react-native';
import { Button, Chip, HelperText, Text, TextInput } from 'react-native-paper';

import { listCalendarTags, type CalendarTag, type EventDefinition, type EventDraft, type Schedule } from '@/api/day-planning';
import { listMembers, type Member, type Team } from '@/api/teams';
import ChoicePicker from '@/components/action-plans/choice-picker';
import { isDay, shiftDay } from '@/dates';
import { localInstant, localInstants, localParts, utcOffsetFor } from '@/day-planning/time';

export default function EventEditor({ initial, teams, userId, occurrenceOnly, saving, exceptions, onRestore, onSave, onCancel }: {
  initial: EventDraft; teams: Team[]; userId: string; occurrenceOnly: boolean; saving: boolean;
  exceptions?: EventDefinition['exceptions']; onRestore: (key: string) => void;
  onSave: (draft: EventDraft) => void; onCancel: () => void;
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
  const changeTeam = (teamId: string) => setDraft((current) => ({ ...current, teamId: teamId || null,
    visibility: teamId ? current.visibility : 'PRIVATE', participantIds: [userId], tagIds: [] }));
  const toggle = (key: 'tagIds' | 'participantIds', value: string) => change(key, draft[key].includes(value) ? draft[key].filter((id) => id !== value) : [...draft[key], value]);
  const invalidTags = draft.visibility === 'TEAM' && tags.some((tag) => draft.tagIds.includes(tag.id) && tag.scope === 'PERSONAL');
  const save = () => {
    setError('');
    try {
      if (!draft.title.trim()) throw new Error('titleRequired');
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
      setError(t(`dayPlanning.${key}`));
    }
  };
  const field = (label: string, value: string, set: (value: string) => void, extra = {}) =>
    <TextInput mode="outlined" label={t(`dayPlanning.${label}`)} accessibilityLabel={t(`dayPlanning.${label}`)} value={value} onChangeText={set} disabled={saving} {...extra} />;
  return <View testID="day-event-editor" style={{ gap: 12 }}>
    <Text variant="headlineSmall" accessibilityRole="header">{t(occurrenceOnly ? 'dayPlanning.editOccurrence' : 'dayPlanning.eventEditor')}</Text>
    {error ? <Text accessibilityRole="alert">{error}</Text> : null}
    {field('eventTitle', draft.title, (value) => change('title', value), { maxLength: 255 })}
    {field('description', draft.description, (value) => change('description', value), { multiline: true })}
    {field('location', draft.location, (value) => change('location', value))}
    <ChoicePicker label={t('dayPlanning.eventTeam')} value={draft.teamId ?? ''} disabled={saving || occurrenceOnly}
      options={[{ value: '', label: t('dayPlanning.noTeam') }, ...teams.map((team) => ({ value: team.id, label: team.name }))]} onChange={changeTeam} />
    <PlanningCheckbox label={t('dayPlanning.allDay')} accessibilityLabel={t('dayPlanning.allDay')} status={allDay ? 'checked' : 'unchecked'} onPress={() => setAllDay(!allDay)} disabled={saving} />
    {field('startDate', startDate, (value) => { setStartDate(value); if (endDate === startDate) setEndDate(value); }, { placeholder: 'YYYY-MM-DD', autoCapitalize: 'none' })}
    {!allDay && field('startTime', startTime, setStartTime, { placeholder: 'HH:MM', keyboardType: 'numbers-and-punctuation' })}
    {field('endDate', endDate, setEndDate, { placeholder: 'YYYY-MM-DD', autoCapitalize: 'none' })}
    {!allDay && field('endTime', endTime, setEndTime, { placeholder: 'HH:MM', keyboardType: 'numbers-and-punctuation' })}
    {allDay && <Text variant="bodySmall">{t('dayPlanning.inclusiveEnd')}</Text>}
    {field('timeZone', zone, setZone, { autoCapitalize: 'none', autoCorrect: false })}
    {!allDay && offsets.length > 1 && <ChoicePicker label={t('dayPlanning.clockChoice')} value={selectedOffset} disabled={saving} options={offsets.map((value, index) => ({ value, label: `${t(index === 0 ? 'dayPlanning.firstClockTime' : 'dayPlanning.secondClockTime')} (UTC${value})` }))} onChange={setUtcOffset} />}
    {!allDay && endOffsets.length > 1 && <ChoicePicker label={t('dayPlanning.endClockChoice')} value={selectedEndOffset} disabled={saving} options={endOffsets.map((value, index) => ({ value, label: `${t(index === 0 ? 'dayPlanning.firstClockTime' : 'dayPlanning.secondClockTime')} (UTC${value})` }))} onChange={setEndUtcOffset} />}
    <Text variant="bodySmall">{t('dayPlanning.timeHint')}</Text>
    {!occurrenceOnly && <>
      {!!Object.keys(exceptions ?? {}).length && <View style={{ gap: 6 }}><Text variant="titleMedium">{t('dayPlanning.exceptions')}</Text><Text>{t('dayPlanning.restoreHint')}</Text>{Object.entries(exceptions ?? {}).map(([key, exception]) => <View key={key}><Text>{key.replace('T', ' ')} · {t(exception.cancelled ? 'dayPlanning.cancelledException' : 'dayPlanning.changedException')}</Text><Button disabled={saving} onPress={() => onRestore(key)}>{t('dayPlanning.restoreOccurrence')}</Button></View>)}</View>}
      <ChoicePicker label={t('dayPlanning.recurrence')} value={frequency} disabled={saving}
        options={['ONCE', 'DAILY', 'WEEKLY'].map((value) => ({ value, label: t(`dayPlanning.frequency.${value}`) }))} onChange={setFrequency} />
      {frequency !== 'ONCE' && <>
        {field('interval', interval, setInterval, { keyboardType: 'number-pad' })}
        {frequency === 'WEEKLY' && <View style={{ flexDirection: 'row', gap: 6, flexWrap: 'wrap' }}>
          {[1, 2, 3, 4, 5, 6, 7].map((day) => <Chip key={day} selected={byDay.includes(day)} accessibilityLabel={t(`dayPlanning.weekdays.${day}`)}
            onPress={() => setByDay(byDay.includes(day) ? byDay.filter((value) => value !== day) : [...byDay, day])}>{t(`dayPlanning.weekdays.${day}`)}</Chip>)}
        </View>}
        <ChoicePicker label={t('dayPlanning.repeatEnds')} value={ending} disabled={saving}
          options={['NEVER', 'UNTIL', 'COUNT'].map((value) => ({ value, label: t(`dayPlanning.ending.${value}`) }))} onChange={setEnding} />
        {ending === 'UNTIL' && field('until', until, setUntil, { placeholder: 'YYYY-MM-DD' })}
        {ending === 'COUNT' && field('count', count, setCount, { keyboardType: 'number-pad' })}
        <Text variant="bodySmall">{t('dayPlanning.recurrenceHint')}</Text>
      </>}
    </>}
    <ChoicePicker label={t('dayPlanning.visibility')} value={draft.visibility} disabled={saving}
      options={[{ value: 'PRIVATE', label: t('dayPlanning.private') }, ...(draft.teamId ? [{ value: 'TEAM', label: t('dayPlanning.shared') }] : [])]}
      onChange={(value) => change('visibility', value as EventDraft['visibility'])} />
    <Text>{t(draft.visibility === 'PRIVATE' ? 'dayPlanning.privateHint' : 'dayPlanning.sharedHint')}</Text>
    <Text variant="titleMedium">{t('dayPlanning.participants')}</Text>
    <Text variant="bodySmall">{t('dayPlanning.participantsHint')}</Text>
    {members.filter((member) => member.userId !== userId).map((member) => <PlanningCheckbox key={member.userId}
      label={member.userName} accessibilityLabel={t('dayPlanning.invitePerson', { name: member.userName })} disabled={saving}
      status={draft.participantIds.includes(member.userId) ? 'checked' : 'unchecked'} onPress={() => toggle('participantIds', member.userId)} />)}
    {!draft.teamId && <Text>{t('dayPlanning.chooseTeamToInvite')}</Text>}
    <Text variant="titleMedium">{t('dayPlanning.tags')}</Text>
    {scopeError && <><Text accessibilityRole="alert">{t('dayPlanning.scopeError')}</Text><Button onPress={() => setRevision((value) => value + 1)}>{t('common.retry')}</Button></>}
    <View style={{ flexDirection: 'row', gap: 6, flexWrap: 'wrap' }}>
      {tags.map((tag) => <Chip key={tag.id} selected={draft.tagIds.includes(tag.id)} disabled={saving}
        onPress={() => toggle('tagIds', tag.id)}>{tag.name}</Chip>)}
    </View>
    {!tags.length && !loadingScope && <Text>{t('dayPlanning.noTags')}</Text>}
    {invalidTags && <HelperText type="error">{t('dayPlanning.personalTagsWarning')}</HelperText>}
    <PlanningCheckbox label={t('dayPlanning.blocksTime')} accessibilityLabel={t('dayPlanning.blocksTime')}
      status={draft.blocksTime ? 'checked' : 'unchecked'} disabled={saving} onPress={() => change('blocksTime', !draft.blocksTime)} />
    <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: 8 }}>
      <Button mode="contained" onPress={save} disabled={saving || loadingScope || scopeError} loading={saving}>{t('common.save')}</Button>
      <Button onPress={onCancel} disabled={saving}>{t('common.cancel')}</Button>
    </View>
  </View>;
}

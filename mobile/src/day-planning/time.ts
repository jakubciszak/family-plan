import type { EventDefinition, EventDraft, Occurrence, Schedule } from '@/api/day-planning';
import { isDay, shiftDay } from '@/dates';

export const deviceTimeZone = () => Intl.DateTimeFormat().resolvedOptions().timeZone || 'Europe/Warsaw';
export const localParts = (iso: string, timeZone: string) => {
  const parts = new Intl.DateTimeFormat('en-CA', {
    timeZone, year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', hourCycle: 'h23',
  }).formatToParts(new Date(iso));
  const value = (type: string) => parts.find((part) => part.type === type)?.value ?? '';
  return { date: `${value('year')}-${value('month')}-${value('day')}`, time: `${value('hour')}:${value('minute')}` };
};
export const isTime = (value: string) => /^([01]\d|2[0-3]):[0-5]\d$/.test(value);
export const localInstants = (date: string, time: string, timeZone: string): Date[] => {
  if (!isDay(date) || !isTime(time)) throw new Error('invalidTime');
  const local = `${date}T${time}`;
  const target = Date.parse(`${local}:00Z`);
  const candidates: number[] = [];
  for (const offset of [-36, -12, 0, 12, 36]) {
    const probe = target + offset * 3600000;
    const parts = localParts(new Date(probe).toISOString(), timeZone);
    const zoneOffset = Date.parse(`${parts.date}T${parts.time}:00Z`) - probe;
    const candidate = target - zoneOffset;
    const resolved = localParts(new Date(candidate).toISOString(), timeZone);
    if (`${resolved.date}T${resolved.time}` === local) candidates.push(candidate);
  }
  if (!candidates.length) throw new Error('invalidTime');
  return [...new Set(candidates)].sort((a, b) => a - b).map((value) => new Date(value));
};
export const utcOffsetFor = (instant: string, timeZone: string): string => {
  const local = localParts(instant, timeZone);
  const minutes = Math.round((Date.parse(`${local.date}T${local.time}:00Z`) - Date.parse(instant)) / 60000);
  return `${minutes < 0 ? '-' : '+'}${String(Math.floor(Math.abs(minutes) / 60)).padStart(2, '0')}:${String(Math.abs(minutes) % 60).padStart(2, '0')}`;
};
export const localInstant = (date: string, time: string, timeZone: string, utcOffset?: string): Date => {
  const options = localInstants(date, time, timeZone);
  const instant = utcOffset ? options.find((value) => utcOffsetFor(value.toISOString(), timeZone) === utcOffset) : options[0];
  if (!instant) throw new Error('invalidTime');
  return instant;
};
export const rangeFor = (date: string, days: number, timeZone: string) => ({
  from: localInstant(date, '00:00', timeZone).toISOString(),
  to: localInstant(shiftDay(date, days), '00:00', timeZone).toISOString(),
});
export const scheduleFromOccurrence = (event: Occurrence): Schedule => {
  const start = localParts(event.start, event.timeZone);
  const end = localParts(event.end, event.timeZone);
  return event.allDay ? { kind: 'ALL_DAY', startDate: start.date, endDate: end.date, timeZone: event.timeZone }
    : { kind: 'TIMED', localStart: `${start.date}T${start.time}`, durationMinutes: (Date.parse(event.end) - Date.parse(event.start)) / 60000, timeZone: event.timeZone, ...(localInstants(start.date, start.time, event.timeZone).length > 1 ? { utcOffset: utcOffsetFor(event.start, event.timeZone) } : {}) };
};
export const draftFromOccurrence = (event: Occurrence): EventDraft => ({
  title: event.title, description: event.description, location: event.location, teamId: event.teamId,
  visibility: event.visibility, schedule: scheduleFromOccurrence(event), recurrence: null,
  participantIds: event.participantIds, tagIds: event.tags.map((tag) => tag.id), blocksTime: event.blocksTime,
});
export const makeRequestKey = () => 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (letter) => {
  const random = Math.floor(Math.random() * 16);
  return (letter === 'x' ? random : (random & 3) | 8).toString(16);
});

export const draftFromDefinition = (event: EventDefinition): EventDraft => ({
  title: event.title, description: event.description, location: event.location, teamId: event.teamId,
  visibility: event.visibility, schedule: event.schedule, recurrence: event.recurrence,
  participantIds: event.participantIds, tagIds: event.tagIds, blocksTime: event.blocksTime,
});

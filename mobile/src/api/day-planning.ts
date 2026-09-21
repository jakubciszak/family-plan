import apiClient from './client';

export type Schedule = { kind: 'TIMED'; localStart: string; durationMinutes: number; timeZone: string; utcOffset?: string }
  | { kind: 'ALL_DAY'; startDate: string; endDate: string; timeZone: string };
export type Recurrence = { frequency: 'DAILY' | 'WEEKLY'; interval: number; byDay: number[]; until: string | null; count: number | null };
export type Participation = 'INCLUDED' | 'DECLINED';
export type CalendarTag = { id: string; name: string; color: string; scope: 'PERSONAL' | 'TEAM'; teamId: string | null; canEdit: boolean };
export type EventDraft = {
  title: string; description: string; location: string; teamId: string | null; visibility: 'PRIVATE' | 'TEAM';
  schedule: Schedule; recurrence: Recurrence | null; participantIds: string[]; tagIds: string[]; blocksTime: boolean;
};
export type EventDefinition = EventDraft & {
  exceptions?: Record<string, { cancelled?: boolean; changes?: Partial<EventDraft> }>;
  id: string; ownerId: string; version: number; participants: { personId: string; status: Participation }[];
};
export type Occurrence = {
  id: string; occurrenceKey: string; title: string; description: string; location: string; ownerId: string;
  teamId: string | null; visibility: 'PRIVATE' | 'TEAM'; start: string; end: string; allDay: boolean; timeZone: string;
  participantIds: string[]; participants: { personId: string; status: Participation }[];
  tags: Pick<CalendarTag, 'id' | 'name' | 'color'>[]; blocksTime: boolean; recurring: boolean;
  canEdit: boolean; canChangeParticipation: boolean; participation: Participation | null; version: number;
};
export type Busy = { kind: 'busy'; personId: string; start: string; end: string };
export type Coverage = { from: string; to: string; complete: boolean };
export type Calendar = { events: Occurrence[]; busy: Busy[]; coverage: Coverage };
export type PlanningQuery = { teamId: string | null; personIds: string[]; from: string; to: string; durationMinutes: number; windowStart: string; windowEnd: string; timeZone: string };
export type Suggestions = { slots: { start: string; end: string }[]; personIds: string[]; busy: Busy[]; coverage: Coverage };
export type Confirmation = { conflictConfirmation?: string; resetExceptions?: boolean };
export type PlanningFailure = { code?: string; message?: string; confirmationToken?: string; conflicts?: Busy[] };
const base = '/api/day-planning';
const match = (version: number) => ({ 'If-Match': `"${version}"` });
const occurrencePath = (id: string, key: string) => `${base}/events/${id}/exceptions/${encodeURIComponent(key)}`;

export const readCalendar = (from: string, to: string, teamId: string | null, personIds: string[], tagIds: string[]) => {
  const query = new URLSearchParams({ from, to });
  if (teamId) query.set('teamId', teamId);
  personIds.forEach((id) => query.append('personIds[]', id));
  tagIds.forEach((id) => query.append('tagIds[]', id));
  return apiClient.get<Calendar>(`${base}/calendar?${query}`);
};
export const readEvent = (id: string) => apiClient.get<EventDefinition>(`${base}/events/${id}`);
export const readOccurrence = (id: string, key: string) => apiClient.get<Occurrence>(`${base}/events/${id}/occurrences/${encodeURIComponent(key)}`);
export const createEvent = (draft: EventDraft, key: string, confirmation: Confirmation = {}) =>
  apiClient.post<EventDefinition>(`${base}/events`, { ...draft, ...confirmation }, { 'Idempotency-Key': key });
export const updateEvent = (id: string, version: number, draft: EventDraft, confirmation: Confirmation = {}) =>
  apiClient.patch<EventDefinition>(`${base}/events/${id}`, { ...draft, ...confirmation }, match(version));
export const removeEvent = (id: string, version: number) => apiClient.delete(`${base}/events/${id}`, match(version));
export const changeOccurrence = (id: string, key: string, version: number, changes: Partial<Omit<EventDraft, 'teamId' | 'recurrence'>>, confirmation: Confirmation = {}) =>
  apiClient.put<EventDefinition>(occurrencePath(id, key), { changes, ...confirmation }, match(version));
export const cancelOccurrence = (id: string, key: string, version: number) =>
  apiClient.put<EventDefinition>(occurrencePath(id, key), { cancelled: true }, match(version));
export const restoreOccurrence = (id: string, key: string, version: number, confirmation: Confirmation = {}) =>
  apiClient.delete<EventDefinition>(occurrencePath(id, key), match(version), confirmation);
export const changeParticipation = (id: string, status: Participation, occurrenceKey: string | null, confirmation: Confirmation = {}) =>
  apiClient.put(`${base}/events/${id}/participation/me`, { status, occurrenceKey, ...confirmation });
export const findSuggestions = (query: PlanningQuery) => apiClient.post<Suggestions>(`${base}/planning/suggestions`, query);
export const listCalendarTags = async (teamId: string | null) =>
  (await apiClient.get<{ tags: CalendarTag[] }>(`${base}/tags${teamId ? `?teamId=${encodeURIComponent(teamId)}` : ''}`)).tags;
export const saveCalendarTag = (tag: Pick<CalendarTag, 'name' | 'color' | 'scope' | 'teamId'>, id?: string) =>
  id ? apiClient.patch<CalendarTag>(`${base}/tags/${id}`, tag) : apiClient.post<CalendarTag>(`${base}/tags`, tag);
export const archiveCalendarTag = (id: string) => apiClient.delete(`${base}/tags/${id}`);

export type CalendarInterval = { key: string; start: string; end: string; allDay?: boolean; timeZone?: string };
export type WeekSegment<T extends CalendarInterval> = {
  item: T;
  day: string;
  startMinute: number;
  endMinute: number;
  displayEndMinute: number;
  continuesBefore: boolean;
  continuesAfter: boolean;
  column: number;
  columns: number;
  overlapCount: number;
};
export type WeekDay<T extends CalendarInterval> = { date: string; timed: WeekSegment<T>[]; allDay: WeekSegment<T>[] };

const shiftDate = (date: string, amount: number): string => {
  const value = new Date(`${date}T12:00:00Z`);
  value.setUTCDate(value.getUTCDate() + amount);
  return value.toISOString().slice(0, 10);
};

export const mondayOf = (date: string): string => shiftDate(date, -((new Date(`${date}T12:00:00Z`).getUTCDay() + 6) % 7));
export const weekDates = (date: string): string[] => Array.from({ length: 7 }, (_, offset) => shiftDate(mondayOf(date), offset));

const formatParts = (instant: number, formatter: Intl.DateTimeFormat): { date: string; minute: number } => {
  const parts = formatter.formatToParts(new Date(instant));
  const part = (type: string) => parts.find((item) => item.type === type)?.value ?? '0';
  return { date: `${part('year')}-${part('month')}-${part('day')}`, minute: Number(part('hour')) % 24 * 60 + Number(part('minute')) + Number(part('second')) / 60 };
};

const placeColumns = <T extends CalendarInterval>(segments: WeekSegment<T>[]): WeekSegment<T>[] => {
  const sorted = [...segments].sort((a, b) => a.startMinute - b.startMinute || b.endMinute - a.endMinute || a.item.key.localeCompare(b.item.key));
  const groups: WeekSegment<T>[][] = [];
  let groupEnd = -1;
  for (const segment of sorted) {
    if (!groups.length || segment.startMinute >= groupEnd) {
      groups.push([]);
      groupEnd = -1;
    }
    groups[groups.length - 1].push(segment);
    groupEnd = Math.max(groupEnd, segment.displayEndMinute);
  }
  for (const group of groups) {
    const occupiedUntil: number[] = [];
    for (const segment of group) {
      let column = occupiedUntil.findIndex((end) => end <= segment.startMinute);
      if (column < 0) column = occupiedUntil.length;
      occupiedUntil[column] = segment.displayEndMinute;
      segment.column = column;
    }
    const boundaries = group.flatMap((segment) => [{ minute: segment.startMinute, starts: true, segment }, { minute: segment.endMinute, starts: false, segment }])
      .sort((a, b) => a.minute - b.minute || Number(a.starts) - Number(b.starts));
    const active = new Set<WeekSegment<T>>();
    for (const boundary of boundaries) {
      if (boundary.starts) {
        active.add(boundary.segment);
        for (const segment of active) segment.overlapCount = Math.max(segment.overlapCount, active.size - 1);
      } else active.delete(boundary.segment);
    }
    for (const segment of group) segment.columns = occupiedUntil.length;
  }
  return sorted;
};

export const layoutWeek = <T extends CalendarInterval>(items: T[], startDate: string, zone: string): WeekDay<T>[] => {
  const days: WeekDay<T>[] = weekDates(startDate).map((date) => ({ date, timed: [], allDay: [] }));
  const formatters = new Map<string, Intl.DateTimeFormat>();
  const formatterFor = (timeZone: string): Intl.DateTimeFormat => {
    let formatter = formatters.get(timeZone);
    if (!formatter) {
      formatter = new Intl.DateTimeFormat('en-CA', { timeZone, year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', second: '2-digit', hourCycle: 'h23' });
      formatters.set(timeZone, formatter);
    }
    return formatter;
  };
  for (const item of items) {
    const start = Date.parse(item.start);
    const end = Date.parse(item.end);
    if (!Number.isFinite(start) || !Number.isFinite(end) || end <= start) continue;
    const formatter = formatterFor(item.allDay ? item.timeZone || zone : zone);
    const first = formatParts(start, formatter);
    const last = formatParts(end, formatter);
    for (const day of days) {
      if (day.date < first.date || day.date > last.date || (day.date === last.date && (item.allDay || last.minute === 0))) continue;
      const startMinute = item.allDay || day.date > first.date ? 0 : first.minute;
      const localEnd = item.allDay || day.date < last.date ? 1440 : last.minute;
      const endMinute = localEnd > startMinute ? localEnd : Math.min(1440, startMinute + (end - start) / 60000);
      const segment: WeekSegment<T> = {
        item, day: day.date, startMinute, endMinute,
        displayEndMinute: Math.min(1440, Math.max(endMinute, startMinute + 20)),
        continuesBefore: day.date > first.date,
        continuesAfter: day.date < last.date && (shiftDate(day.date, 1) < last.date || last.minute > 0),
        column: 0, columns: 1, overlapCount: 0,
      };
      (item.allDay ? day.allDay : day.timed).push(segment);
    }
  }
  return days.map((day) => ({ ...day, timed: placeColumns(day.timed), allDay: day.allDay.sort((a, b) => a.item.start.localeCompare(b.item.start) || a.item.key.localeCompare(b.item.key)) }));
};

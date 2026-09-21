const { test, expect } = require('@playwright/test');
const mobile = require('../src/day-planning/week-layout');
const web = require('../../frontend/src/services/weekCalendarLayout');

const interval = (key, start, end, extra = {}) => ({ key, start, end, ...extra });
const monday = '2026-09-21';
const at = (time) => `${monday}T${time}:00+02:00`;

for (const [platform, { mondayOf, weekDates, layoutWeek }] of Object.entries({ mobile, web })) {
  test.describe(`${platform} weekly layout`, () => {
    test('anchors Sunday to Monday across year and leap-day boundaries', () => {
      expect(mondayOf('2027-01-03')).toBe('2026-12-28');
      expect(weekDates('2024-03-03')).toEqual(['2024-02-26', '2024-02-27', '2024-02-28', '2024-02-29', '2024-03-01', '2024-03-02', '2024-03-03']);
      expect(layoutWeek([], monday, 'Europe/Warsaw')).toHaveLength(7);
    });

    test('separates nested and chained overlaps while reusing free columns', () => {
      const entries = [interval('work', at('08:00'), at('16:00')), interval('lesson', at('09:00'), at('11:00')), interval('call', at('10:00'), at('12:00')), interval('later', at('12:00'), at('13:00'))];
      const timed = layoutWeek(entries, monday, 'Europe/Warsaw')[0].timed;
      expect(timed.map(({ item, column, columns, overlapCount }) => [item.key, column, columns, overlapCount])).toEqual([
        ['work', 0, 3, 2], ['lesson', 1, 3, 2], ['call', 2, 3, 2], ['later', 1, 3, 1],
      ]);
      expect(entries[0]).not.toHaveProperty('column');
    });

    test('reports peak simultaneous events instead of summing separate collisions', () => {
      const timed = layoutWeek([interval('work', at('08:00'), at('16:00')), interval('first', at('09:00'), at('10:00')), interval('second', at('11:00'), at('12:00'))], monday, 'Europe/Warsaw')[0].timed;
      expect(timed.map((item) => item.overlapCount)).toEqual([1, 1, 1]);
    });

    test('keeps touching events full width and does not call them overlapping', () => {
      const timed = layoutWeek([interval('a', at('08:00'), at('09:00')), interval('b', at('09:00'), at('10:00'))], monday, 'Europe/Warsaw')[0].timed;
      expect(timed.map(({ column, columns, overlapCount }) => [column, columns, overlapCount])).toEqual([[0, 1, 0], [0, 1, 0]]);
    });

    test('keeps minimum-size short blocks from covering a following block', () => {
      const timed = layoutWeek([interval('a', at('08:00'), at('08:05')), interval('b', at('08:05'), at('08:10'))], monday, 'Europe/Warsaw')[0].timed;
      expect(timed[0]).toMatchObject({ startMinute: 480, endMinute: 485, displayEndMinute: 500, column: 0, columns: 2, overlapCount: 0 });
      expect(timed[1]).toMatchObject({ startMinute: 485, endMinute: 490, column: 1, overlapCount: 0 });
    });

    test('clips overnight events at midnight without adding a phantom next day', () => {
      const days = layoutWeek([interval('night', at('23:00'), '2026-09-23T00:00:00+02:00')], monday, 'Europe/Warsaw');
      expect(days[0].timed[0]).toMatchObject({ startMinute: 1380, endMinute: 1440, continuesBefore: false, continuesAfter: true });
      expect(days[1].timed[0]).toMatchObject({ startMinute: 0, endMinute: 1440, continuesBefore: true, continuesAfter: false });
      expect(days[2].timed).toEqual([]);
    });

    test('keeps all-day date spans separate and their end exclusive in another display zone', () => {
      const days = layoutWeek([interval('trip', '2026-09-21T00:00:00+02:00', '2026-09-23T00:00:00+02:00', { allDay: true, timeZone: 'Europe/Warsaw' })], monday, 'America/New_York');
      expect(days.map((day) => day.allDay.length)).toEqual([1, 1, 0, 0, 0, 0, 0]);
      expect(days.every((day) => day.timed.length === 0)).toBe(true);
      expect(days[0].allDay[0].continuesAfter).toBe(true);
      expect(days[1].allDay[0].continuesAfter).toBe(false);
    });

    test('uses the selected timezone and projects DST transitions to positive blocks', () => {
      const shifted = layoutWeek([interval('early', '2026-09-21T22:30:00Z', '2026-09-21T23:30:00Z')], monday, 'Europe/Warsaw');
      expect(shifted[0].timed).toEqual([]);
      expect(shifted[1].timed[0]).toMatchObject({ startMinute: 30, endMinute: 90 });
      const spring = layoutWeek([interval('gap', '2026-03-29T01:30:00+01:00', '2026-03-29T03:30:00+02:00')], '2026-03-29', 'Europe/Warsaw')[6].timed[0];
      expect(spring).toMatchObject({ startMinute: 90, endMinute: 210 });
      const autumn = layoutWeek([interval('fold', '2026-10-25T02:45:00+02:00', '2026-10-25T02:15:00+01:00')], '2026-10-25', 'Europe/Warsaw')[6].timed[0];
      expect(autumn.endMinute).toBeGreaterThan(autumn.startMinute);
    });

    test('places anonymous busy intervals beside visible events without inventing details', () => {
      const hidden = interval('busy-1', at('08:30'), at('09:30'), { personId: 'person-2', kind: 'busy' });
      const timed = layoutWeek([interval('event', at('08:00'), at('09:00'), { title: 'Lesson' }), hidden], monday, 'Europe/Warsaw')[0].timed;
      expect(timed[1]).toMatchObject({ column: 1, columns: 2, overlapCount: 1 });
      expect(timed[1].item).toEqual(hidden);
      expect(timed[1].item).not.toHaveProperty('title');
    });

    test('ignores invalid intervals and clips blocks inside the last hour', () => {
      const timed = layoutWeek([interval('bad', 'invalid', at('12:00')), interval('reverse', at('12:00'), at('11:00')), interval('zero', at('12:00'), at('12:00')), interval('late', at('23:50'), at('23:55'))], monday, 'Europe/Warsaw')[0].timed;
      expect(timed).toHaveLength(1);
      expect(timed[0]).toMatchObject({ startMinute: 1430, endMinute: 1435, displayEndMinute: 1440 });
    });
  });
}

test('web and mobile agree for crowded weeks and never put overlapping blocks in the same column', () => {
  let seed = 1701;
  const random = (limit) => { seed = (seed * 1664525 + 1013904223) >>> 0; return seed % limit; };
  for (let run = 0; run < 25; run += 1) {
    const entries = Array.from({ length: 25 }, (_, index) => {
      const start = Date.parse('2026-09-21T00:00:00Z') + random(7 * 24 * 60) * 60000;
      return interval(`event-${index}`, new Date(start).toISOString(), new Date(start + (1 + random(360)) * 60000).toISOString());
    });
    const days = mobile.layoutWeek(entries, monday, 'Europe/Warsaw');
    expect(days).toEqual(web.layoutWeek(entries, monday, 'Europe/Warsaw'));
    for (const { timed } of days) {
      for (let first = 0; first < timed.length; first += 1) {
        expect(timed[first].column).toBeLessThan(timed[first].columns);
        for (let second = first + 1; second < timed.length; second += 1) {
          if (timed[first].startMinute < timed[second].displayEndMinute && timed[second].startMinute < timed[first].displayEndMinute) expect(timed[first].column).not.toBe(timed[second].column);
        }
      }
    }
  }
});

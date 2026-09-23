export const browserTimeZone = () => Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC';
export const localDateTime = (value, timeZone) => {
    const parts = new Intl.DateTimeFormat('en-CA', { timeZone, year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', hourCycle: 'h23' }).formatToParts(new Date(value));
    const pick = (type) => parts.find((part) => part.type === type)?.value;
    return `${pick('year')}-${pick('month')}-${pick('day')}T${pick('hour')}:${pick('minute')}`;
};
export const dateInZone = (value, zone) => localDateTime(value, zone).slice(0, 10);
export const addDays = (value, days) => {
    const date = new Date(`${value}T12:00:00Z`);
    date.setUTCDate(date.getUTCDate() + days);
    return date.toISOString().slice(0, 10);
};
export const weekStart = (value) => addDays(value, -((new Date(`${value}T12:00:00Z`).getUTCDay() + 6) % 7));
export const zonedIso = (local, timeZone) => {
    const wall = new Date(`${local}:00Z`).getTime();
    let instant = wall;
    for (let attempt = 0; attempt < 4; attempt++) {
        const represented = new Date(`${localDateTime(instant, timeZone)}:00Z`).getTime();
        const difference = wall - represented;
        if (difference === 0) {
            let earliest = instant;
            for (let minutes = 30; minutes <= 180; minutes += 30) {
                const candidate = instant - minutes * 60000;
                if (localDateTime(candidate, timeZone) === local) earliest = candidate;
            }
            return new Date(earliest).toISOString();
        }
        instant += difference;
    }
    throw new Error('invalid_local_time');
};
export const displayDate = (value, locale, options = {}) => new Intl.DateTimeFormat(locale, { day: 'numeric', month: 'long', ...options, timeZone: 'UTC' }).format(new Date(`${value}T12:00:00Z`));
export const displayTime = (value, locale, timeZone) => new Intl.DateTimeFormat(locale, { hour: '2-digit', minute: '2-digit', timeZone }).format(new Date(value));
export const eventOnDate = (event, date, zone) => new Date(event.start) < new Date(zonedIso(`${addDays(date, 1)}T00:00`, zone)) && new Date(event.end) > new Date(zonedIso(`${date}T00:00`, zone));
export const minutesOnDate = (value, date, zone) => {
    const local = localDateTime(value, zone);
    if (local.slice(0, 10) < date) return 0;
    if (local.slice(0, 10) > date) return 1440;
    return Number(local.slice(11, 13)) * 60 + Number(local.slice(14, 16));
};
export const capitalize = (text) => text.charAt(0).toUpperCase() + text.slice(1);
export const personName = (person) => person?.name || person?.nickname || person?.email || person?.id || '';
export const peopleOf = (data) => (data.members || []).map((member) => ({ ...member, id: String(member.userId || member.id), name: member.name || member.userName || member.email }));

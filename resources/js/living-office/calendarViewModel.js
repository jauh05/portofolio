export const CALENDAR_VIEWS = ['day', 'week', 'month'];
const TZ = 'Asia/Jakarta';
const pad = value => String(value).padStart(2, '0');
const parts = key => key.split('-').map(Number);
const asUtc = key => { const [year, month, day] = parts(key); return new Date(Date.UTC(year, month - 1, day)); };
const fromUtc = date => `${date.getUTCFullYear()}-${pad(date.getUTCMonth() + 1)}-${pad(date.getUTCDate())}`;

export function todayKey(now = new Date()) {
    return new Intl.DateTimeFormat('en-CA', { timeZone: TZ, year: 'numeric', month: '2-digit', day: '2-digit' }).format(now);
}

export function occurrenceDateKey(value) {
    const parsed = new Date(value);
    return Number.isNaN(parsed.getTime()) ? null : todayKey(parsed);
}

export function addCalendarDays(key, amount) {
    const date = asUtc(key);
    date.setUTCDate(date.getUTCDate() + amount);
    return fromUtc(date);
}

export function calendarRange(view, key) {
    if (view === 'day') return { start: key, end: key };
    if (view === 'week') {
        const monday = addCalendarDays(key, -((asUtc(key).getUTCDay() + 6) % 7));
        return { start: monday, end: addCalendarDays(monday, 6) };
    }
    const [year, month] = parts(key);
    return { start: `${year}-${pad(month)}-01`, end: fromUtc(new Date(Date.UTC(year, month, 0))) };
}

export function shiftCalendarDate(view, key, direction) {
    if (view === 'day') return addCalendarDays(key, direction);
    if (view === 'week') return addCalendarDays(key, direction * 7);
    const [year, month, day] = parts(key);
    const target = new Date(Date.UTC(year, month - 1 + direction, 1));
    const last = new Date(Date.UTC(target.getUTCFullYear(), target.getUTCMonth() + 1, 0)).getUTCDate();
    target.setUTCDate(Math.min(day, last));
    return fromUtc(target);
}

export function datesInRange(start, end) {
    const result = [];
    for (let date = start; date <= end; date = addCalendarDays(date, 1)) result.push(date);
    return result;
}

export function groupCalendarOccurrences(occurrences) {
    const result = {};
    for (const item of occurrences) {
        const key = occurrenceDateKey(item.scheduledAt);
        if (key) (result[key] ||= []).push(item);
    }
    for (const entries of Object.values(result)) entries.sort((a, b) => Date.parse(a.scheduledAt) - Date.parse(b.scheduledAt) || String(a.id).localeCompare(String(b.id)));
    return result;
}

export function calendarTime(value) {
    return new Intl.DateTimeFormat('id-ID', { timeZone: TZ, hour: '2-digit', minute: '2-digit', hourCycle: 'h23' }).format(new Date(value));
}

export function calendarTitle(view, key) {
    const format = (date, options) => new Intl.DateTimeFormat('id-ID', { timeZone: 'UTC', ...options }).format(asUtc(date));
    if (view === 'day') return format(key, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
    if (view === 'month') return format(key, { month: 'long', year: 'numeric' });
    const { start, end } = calendarRange('week', key);
    const startMonth = format(start, { month: 'long' });
    const endMonth = format(end, { month: 'long', year: 'numeric' });
    const startDay = Number(start.slice(8, 10));
    const endDay = Number(end.slice(8, 10));
    return startMonth === format(end, { month: 'long' })
        ? `${startDay}–${endDay} ${endMonth}`
        : `${startDay} ${format(start, { month: 'long', year: 'numeric' })}–${endDay} ${endMonth}`;
}

export function readCalendarView(storage) {
    try { const view = (storage ?? globalThis.localStorage)?.getItem('officePlannerCalendarView'); return CALENDAR_VIEWS.includes(view) ? view : 'week'; }
    catch { return 'week'; }
}

export function writeCalendarView(view, storage) {
    const selected = CALENDAR_VIEWS.includes(view) ? view : 'week';
    try { (storage ?? globalThis.localStorage)?.setItem('officePlannerCalendarView', selected); } catch { /* Storage can be unavailable. */ }
    return selected;
}

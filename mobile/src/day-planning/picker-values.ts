export type PickerMode = 'date' | 'time';
export type PickerPlatform = 'android' | 'ios';

export function isPickerValue(value: string, mode: PickerMode): boolean {
  if (mode === 'time') return /^([01]\d|2[0-3]):[0-5]\d$/.test(value);
  if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) return false;
  const date = new Date(`${value}T00:00:00Z`);
  return Number.isFinite(date.getTime()) && date.toISOString().slice(0, 10) === value;
}

export function pickerDate(value: string, mode: PickerMode, platform: PickerPlatform): Date {
  if (mode === 'date') return new Date(`${value}T00:00:00Z`);
  const [hour, minute] = value.split(':').map(Number);
  return platform === 'android' ? new Date(2000, 0, 15, hour, minute) : new Date(Date.UTC(2000, 0, 15, hour, minute));
}

export function pickerValue(date: Date, mode: PickerMode, platform: PickerPlatform): string {
  if (mode === 'date') return date.toISOString().slice(0, 10);
  const hour = platform === 'android' ? date.getHours() : date.getUTCHours();
  const minute = platform === 'android' ? date.getMinutes() : date.getUTCMinutes();
  return `${String(hour).padStart(2, '0')}:${String(minute).padStart(2, '0')}`;
}

export function pickerMinimumDate(value: string, platform: PickerPlatform): Date | undefined {
  if (!isPickerValue(value, 'date')) return undefined;
  const date = pickerDate(value, 'date', platform);
  return platform === 'android' ? new Date(date.getUTCFullYear(), date.getUTCMonth(), date.getUTCDate(), 12) : date;
}

export function formatPickerValue(value: string, mode: PickerMode, locale: string): string {
  if (!isPickerValue(value, mode)) return '';
  return new Intl.DateTimeFormat(locale, mode === 'date'
    ? { timeZone: 'UTC', day: 'numeric', month: 'long', year: 'numeric' }
    : { timeZone: 'UTC', hour: '2-digit', minute: '2-digit', hourCycle: 'h23' }).format(pickerDate(value, mode, 'ios'));
}

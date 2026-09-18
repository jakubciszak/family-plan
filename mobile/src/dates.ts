export const dayString = (day: Date): string => day.toLocaleDateString('sv');

export const shiftDay = (day: string, offset: number): string => {
  const changed = new Date(`${day}T12:00:00`);
  changed.setDate(changed.getDate() + offset);
  return dayString(changed);
};

export const currentMonday = (): string => {
  const today = new Date();
  return shiftDay(dayString(today), -((today.getDay() + 6) % 7));
};

export const isDay = (value: string): boolean => /^\d{4}-\d{2}-\d{2}$/.test(value) && dayString(new Date(`${value}T12:00:00`)) === value;

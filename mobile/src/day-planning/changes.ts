const listeners = new Set<() => void>();

export const notifyCalendarChanged = () => listeners.forEach((listener) => listener());
export const subscribeCalendarChanges = (listener: () => void) => {
  listeners.add(listener);
  return () => { listeners.delete(listener); };
};

import AsyncStorage from '@react-native-async-storage/async-storage';
import { createContext, useCallback, useContext, useEffect, useRef, useState, type ReactNode } from 'react';

import type { TaskExecution } from '@/api/tasks';
import { restoreRun, shouldKeepRun, storageKey, type PlanRun } from './run';

type PlanContext = {
  run: PlanRun | null;
  changeRun: (run: PlanRun | null) => void;
  ready: boolean;
  storageFailed: boolean;
  focused: boolean;
  setFocused: (value: boolean) => void;
  task: TaskExecution | null;
  setTask: (task: TaskExecution | null) => void;
};
const Context = createContext<PlanContext | null>(null);
const writes = new Map<string, Promise<void>>();

export function ActionPlanProvider({ userId, children }: { userId: string; children: ReactNode }) {
  const [run, setRun] = useState<PlanRun | null>(null);
  const [ready, setReady] = useState(false);
  const [storageFailed, setStorageFailed] = useState(false);
  const [focused, setFocused] = useState(false);
  const [task, setTask] = useState<TaskExecution | null>(null);
  const alive = useRef(true);
  const key = storageKey(userId);

  const changeRun = useCallback((next: PlanRun | null) => {
    setRun(next);
    const value = shouldKeepRun(next) ? JSON.stringify(next) : null;
    const write = (writes.get(key) ?? Promise.resolve()).then(() => value === null
      ? AsyncStorage.removeItem(key) : AsyncStorage.setItem(key, value)).catch(() => {
      if (alive.current) setStorageFailed(true);
    });
    writes.set(key, write);
    void write.finally(() => { if (writes.get(key) === write) writes.delete(key); });
  }, [key]);

  useEffect(() => {
    alive.current = true;
    void (async () => {
      try {
        await writes.get(key);
        const raw = await AsyncStorage.getItem(key);
        if (alive.current) changeRun(restoreRun(raw, Date.now()));
      } catch {
        if (alive.current) setStorageFailed(true);
      } finally {
        if (alive.current) setReady(true);
      }
    })();
    return () => { alive.current = false; };
  }, [key, changeRun]);

  return <Context.Provider value={{ run, changeRun, ready, storageFailed, focused, setFocused, task, setTask }}>{children}</Context.Provider>;
}

export function useActionPlans() {
  const context = useContext(Context);
  if (!context) throw new Error('ActionPlanProvider missing');
  return context;
}

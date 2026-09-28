import { createContext, useContext, useState, type ReactNode } from 'react';

type FullScreenContext = { fullScreen: boolean; setFullScreen: (value: boolean) => void };
const Context = createContext<FullScreenContext | null>(null);

export function FullScreenProvider({ children }: { children: ReactNode }) {
  const [fullScreen, setFullScreen] = useState(false);
  return <Context.Provider value={{ fullScreen, setFullScreen }}>{children}</Context.Provider>;
}

export function useFullScreen() {
  const context = useContext(Context);
  if (!context) throw new Error('FullScreenProvider missing');
  return context;
}

import * as SecureStore from 'expo-secure-store';
import { Platform } from 'react-native';

const ACCESS_KEY = 'familyplan.accessToken';
const REFRESH_KEY = 'familyplan.refreshToken';

export type Tokens = {
  accessToken: string;
  refreshToken: string;
};

type Vault = {
  read: (key: string) => Promise<string | null>;
  write: (key: string, value: string) => Promise<void>;
  forget: (key: string) => Promise<void>;
};

const keychain: Vault = {
  read: (key) => SecureStore.getItemAsync(key),
  write: (key, value) => SecureStore.setItemAsync(key, value),
  forget: (key) => SecureStore.deleteItemAsync(key),
};

/**
 * expo-secure-store ships no web implementation, so the browser build needs its
 * own. A browser has no secure enclave: these tokens are only as safe as the
 * origin they are stored under.
 */
const browserStorage: Vault = {
  read: async (key) => {
    try {
      return globalThis.localStorage?.getItem(key) ?? null;
    } catch {
      return null;
    }
  },
  write: async (key, value) => {
    try {
      globalThis.localStorage?.setItem(key, value);
    } catch {
      // storage unavailable; the session lives in memory for this run
    }
  },
  forget: async (key) => {
    try {
      globalThis.localStorage?.removeItem(key);
    } catch {
      // nothing to forget
    }
  },
};

const vault: Vault = Platform.OS === 'web' ? browserStorage : keychain;

let inHand: Tokens | null = null;
let loaded = false;
let version = 0;
let session = 0;
let persistentSession = true;

export const sessionVersion = (): number => session;
let storageWork: Promise<void> = Promise.resolve();

const persist = (work: () => Promise<unknown>): Promise<void> => {
  const pending = storageWork.then(work).then(() => undefined);
  storageWork = pending.catch(() => undefined);
  return pending;
};

export const accessTokenInHand = (): string | null => inHand?.accessToken ?? null;

export const readTokens = async (): Promise<Tokens | null> => {
  if (loaded) {
    return inHand;
  }

  const startedAt = version;
  const [accessToken, refreshToken] = await Promise.all([
    vault.read(ACCESS_KEY),
    vault.read(REFRESH_KEY),
  ]);

  if (version === startedAt) {
    inHand = accessToken && refreshToken ? { accessToken, refreshToken } : null;
    loaded = true;
  }

  return inHand;
};

export const writeTokens = async (tokens: Tokens, rotation = false, remember = true): Promise<void> => {
  if (!rotation) { session += 1; persistentSession = remember; }
  version += 1;
  loaded = true;
  inHand = tokens;

  const keep = persistentSession;
  await persist(() => keep ? Promise.all([
    vault.write(ACCESS_KEY, tokens.accessToken),
    vault.write(REFRESH_KEY, tokens.refreshToken),
  ]) : Promise.all([vault.forget(ACCESS_KEY), vault.forget(REFRESH_KEY)]));
};

export const clearTokens = async (): Promise<void> => {
  session += 1;
  version += 1;
  loaded = true;
  inHand = null;

  await persist(() => Promise.all([vault.forget(ACCESS_KEY), vault.forget(REFRESH_KEY)]));
};

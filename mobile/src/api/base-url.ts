import Constants from 'expo-constants';

const CONFIGURED = process.env.EXPO_PUBLIC_API_URL ?? '';

const portOf = (url: string): string => /:(\d+)(?:\/|$)/.exec(url)?.[1] ?? '8080';

/**
 * In development the phone reaches this machine at whatever address it already
 * uses for Metro, so the API lives on that same host. A hardcoded 10.0.2.2 only
 * ever works in the Android emulator.
 */
const whereMetroIs = (): string | null => {
  const hostUri = Constants.expoConfig?.hostUri ?? Constants.expoGoConfig?.debuggerHost;
  const host = hostUri?.split(':')[0];

  return host ? `http://${host}:${portOf(CONFIGURED)}` : null;
};

export const BASE_URL = (CONFIGURED || (__DEV__ ? whereMetroIs() ?? '' : '')).replace(/\/$/, '');

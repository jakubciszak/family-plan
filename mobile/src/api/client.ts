import { BASE_URL } from './base-url';
import { clearTokens, readTokens, sessionVersion, writeTokens, type Tokens } from './tokenStore';

export class ApiError extends Error {
  constructor(
    readonly status: number,
    readonly body: unknown
  ) {
    super(`HTTP ${status}`);
    this.name = 'ApiError';
  }
}

export class OutOfReachError extends Error {
  constructor(readonly where: string) {
    super(`cannot reach ${where}`);
    this.name = 'OutOfReachError';
  }
}

type SessionLostHandler = () => void;

let onSessionLost: SessionLostHandler = () => {};
const refreshInFlight = new Map<string, Promise<Tokens | null>>();

export const setSessionLostHandler = (handler: SessionLostHandler): void => {
  onSessionLost = handler;
};

const readBody = async (response: Response): Promise<unknown> => {
  if (response.status === 204) {
    return null;
  }

  const text = await response.text();

  if (!text) {
    return null;
  }

  try {
    return JSON.parse(text);
  } catch {
    return null;
  }
};

const send = async (path: string, init: RequestInit, accessToken?: string): Promise<Response> => {
  const url = path.startsWith('http') ? path : `${BASE_URL}${path}`;

  try {
    return await fetch(url, {
      ...init,
      headers: {
        Accept: 'application/json',
        ...(init.body ? { 'Content-Type': 'application/json' } : {}),
        ...(accessToken ? { Authorization: `Bearer ${accessToken}` } : {}),
        ...init.headers,
      },
    });
  } catch {
    throw new OutOfReachError(BASE_URL || url);
  }
};

const refreshTokens = async (refreshToken: string): Promise<Tokens | null> => {
  const response = await send('/api/auth/token/refresh', {
    method: 'POST',
    body: JSON.stringify({ refresh_token: refreshToken }),
  });

  if (response.status === 401) {
    return null;
  }
  if (!response.ok) {
    throw new ApiError(response.status, await readBody(response));
  }

  const body = (await readBody(response)) as { token?: string; refresh_token?: string } | null;

  if (!body?.token || !body.refresh_token) {
    throw new ApiError(502, body);
  }

  if ((await readTokens())?.refreshToken !== refreshToken) {
    return null;
  }

  const tokens = { accessToken: body.token, refreshToken: body.refresh_token };
  await writeTokens(tokens, true);

  return tokens;
};

const refreshOnce = (refreshToken: string): Promise<Tokens | null> => {
  let pending = refreshInFlight.get(refreshToken);
  if (!pending) {
    pending = refreshTokens(refreshToken).finally(() => refreshInFlight.delete(refreshToken));
    refreshInFlight.set(refreshToken, pending);
  }
  return pending;
};

const request = async <T>(path: string, init: RequestInit): Promise<T> => {
  const stored = await readTokens();
  const session = sessionVersion();
  let response = await send(path, init, stored?.accessToken);

  if (response.status === 401 && stored) {
    if (sessionVersion() !== session) throw new ApiError(401, null);
    const current = await readTokens();
    const renewed = current?.accessToken !== stored.accessToken
      ? current
      : await refreshOnce(stored.refreshToken);

    if (!renewed) {
      if ((await readTokens())?.refreshToken === stored.refreshToken) {
        await clearTokens();
        onSessionLost();
      }
      throw new ApiError(401, await readBody(response));
    }

    if (sessionVersion() !== session) throw new ApiError(401, null);
    response = await send(path, init, renewed.accessToken);
    if (response.status === 401 && (await readTokens())?.accessToken === renewed.accessToken) {
      await clearTokens();
      onSessionLost();
    }
  }

  if (!response.ok) {
    throw new ApiError(response.status, await readBody(response));
  }

  return (await readBody(response)) as T;
};

const apiClient = {
  get: <T>(path: string) => request<T>(path, { method: 'GET' }),
  post: <T>(path: string, data?: unknown, headers?: Record<string, string>) =>
    request<T>(path, { method: 'POST', body: JSON.stringify(data ?? {}), headers }),
  patch: <T>(path: string, data: unknown, headers?: Record<string, string>) =>
    request<T>(path, { method: 'PATCH', body: JSON.stringify(data), headers }),
  put: <T>(path: string, data?: unknown, headers?: Record<string, string>) =>
    request<T>(path, { method: 'PUT', body: JSON.stringify(data ?? {}), headers }),
  delete: <T>(path: string, headers?: Record<string, string>, data?: unknown) => request<T>(path, { method: 'DELETE', headers, ...(data ? { body: JSON.stringify(data) } : {}) }),
};

export const requestTokens = async (email: string, password: string) => {
  const response = await send('/api/auth/token', {
    method: 'POST',
    body: JSON.stringify({ email, password }),
  });

  const body = await readBody(response);

  if (!response.ok) {
    throw new ApiError(response.status, body);
  }

  return body as { token: string; refresh_token: string; user: AuthenticatedUser };
};

export type AuthenticatedUser = {
  id: string;
  name: string;
  email: string;
  role: string;
};

export default apiClient;

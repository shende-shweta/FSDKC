const API_BASE = import.meta.env.VITE_API_URL ?? 'http://localhost:8080/api';

export function getStreamUrl(path: string): string {
  const base = API_BASE.replace(/\/api\/?$/, '');
  return `${base}/api${path.startsWith('/') ? path : `/${path}`}`;
}

/**
 * Generic typed fetch wrapper.
 * Throws an Error on non-2xx responses, including the response body as the message.
 */
async function request<T>(path: string, options?: RequestInit): Promise<T> {
  let res: Response;

  try {
    res = await fetch(`${API_BASE}${path}`, {
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      ...options,
    });
  } catch (networkErr) {
    throw new Error(`Network error: ${(networkErr as Error).message}`);
  }

  if (!res.ok) {
    const body = await res.text().catch(() => '');
    throw new Error(body || `HTTP ${res.status}`);
  }

  return res.json() as Promise<T>;
}

export const api = {
  get:    <T>(path: string)                  => request<T>(path),
  post:   <T>(path: string, body: unknown)   => request<T>(path, { method: 'POST',  body: JSON.stringify(body) }),
  patch:  <T>(path: string, body: unknown)   => request<T>(path, { method: 'PATCH', body: JSON.stringify(body) }),
  delete: <T>(path: string)                  => request<T>(path, { method: 'DELETE' }),
};

const API_BASE = import.meta.env.VITE_API_URL ?? 'http://localhost:8080/api';

export function getStreamUrl(path: string): string {
  const base = (import.meta.env.VITE_API_URL ?? 'http://localhost:8080/api').replace(/\/$/, '');
  const token = localStorage.getItem('klearcom_token');
  const separator = path.includes('?') ? '&' : '?';
  return `${base}${path}${token ? `${separator}token=${encodeURIComponent(token)}` : ''}`;
}

async function request<T>(path: string, options?: RequestInit): Promise<T> {
  const token = localStorage.getItem('klearcom_token');
  const headers: Record<string, string> = {
    'Content-Type': 'application/json',
    Accept: 'application/json',
  };
  if (token) {
    headers['Authorization'] = `Bearer ${token}`;
  }

  const res = await fetch(`${API_BASE}${path}`, {
    headers,
    ...options,
  });

  if (res.status === 401) {
    localStorage.removeItem('klearcom_token');
    window.location.href = '/login';
    throw new Error('Unauthorized');
  }

  if (!res.ok) {
    const body = await res.text();
    throw new Error(body || `HTTP ${res.status}`);
  }

  return res.json() as Promise<T>;
}

export const api = {
  get: <T>(path: string) => request<T>(path),
  post: <T>(path: string, body: unknown) =>
    request<T>(path, { method: 'POST', body: JSON.stringify(body) }),
};

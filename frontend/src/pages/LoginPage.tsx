import { useState } from 'react';
import { useNavigate } from 'react-router-dom';

const API_BASE = import.meta.env.VITE_API_URL ?? 'http://localhost:8080/api';

export default function LoginPage() {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);
  const navigate = useNavigate();

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError(null);
    setLoading(true);

    try {
      const res = await fetch(`${API_BASE}/auth/login`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify({ email, password }),
      });

      if (!res.ok) {
        const body = await res.json().catch(() => ({}));
        throw new Error(body?.message ?? `Login failed (${res.status})`);
      }

      const data = await res.json();
      localStorage.setItem('klearcom_token', data.data.token);
      navigate('/');
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Login failed');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', minHeight: '100vh', background: 'var(--bg)' }}>
      <div className="card" style={{ width: '100%', maxWidth: '400px' }}>
        <div className="card-header">
          <strong>Klearcom — Sign In</strong>
        </div>
        <form onSubmit={handleSubmit} style={{ padding: '1.5rem', display: 'flex', flexDirection: 'column', gap: '1rem' }}>
          {error && (
            <div className="text-red-500 p-4" style={{ background: 'var(--danger-bg, #fee2e2)', borderRadius: '4px', color: 'var(--danger)', padding: '0.75rem' }}>
              {error}
            </div>
          )}
          <label style={{ display: 'flex', flexDirection: 'column', gap: '0.25rem', fontSize: '0.875rem' }}>
            Email
            <input
              type="email"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              required
              autoComplete="email"
              style={{ padding: '0.5rem', border: '1px solid var(--border)', borderRadius: '4px', background: 'var(--input-bg, #fff)', color: 'inherit' }}
            />
          </label>
          <label style={{ display: 'flex', flexDirection: 'column', gap: '0.25rem', fontSize: '0.875rem' }}>
            Password
            <input
              type="password"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              required
              autoComplete="current-password"
              style={{ padding: '0.5rem', border: '1px solid var(--border)', borderRadius: '4px', background: 'var(--input-bg, #fff)', color: 'inherit' }}
            />
          </label>
          <button
            type="submit"
            disabled={loading}
            style={{ padding: '0.625rem', background: 'var(--accent)', color: '#fff', border: 'none', borderRadius: '4px', cursor: loading ? 'not-allowed' : 'pointer', opacity: loading ? 0.7 : 1 }}
          >
            {loading ? 'Signing in…' : 'Sign In'}
          </button>
        </form>
      </div>
    </div>
  );
}

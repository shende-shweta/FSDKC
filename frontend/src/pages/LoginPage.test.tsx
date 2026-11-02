import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { MemoryRouter } from 'react-router-dom';
import LoginPage from './LoginPage';

// AC: T-04 — SPA login flow stores Bearer token, redirects on success, shows error on failure
const mockNavigate = vi.fn();
vi.mock('react-router-dom', async () => {
  const actual = await vi.importActual<typeof import('react-router-dom')>('react-router-dom');
  return { ...actual, useNavigate: () => mockNavigate };
});

function renderLogin() {
  return render(
    <MemoryRouter>
      <LoginPage />
    </MemoryRouter>
  );
}

describe('LoginPage', () => {
  beforeEach(() => {
    localStorage.clear();
    mockNavigate.mockClear();
    vi.stubGlobal('fetch', vi.fn());
  });

  afterEach(() => {
    vi.unstubAllGlobals();
    localStorage.clear();
  });

  it('renders email and password fields and a submit button', () => {
    renderLogin();
    expect(screen.getByRole('textbox', { name: /email/i })).toBeInTheDocument();
    expect(screen.getByLabelText(/password/i)).toBeInTheDocument();
    expect(screen.getByRole('button')).toBeInTheDocument();
  });

  it('stores the token in localStorage and navigates to / on successful login', async () => {
    vi.mocked(fetch).mockResolvedValue({
      ok: true,
      json: async () => ({ data: { token: 'bearer-token-abc', user: { id: 1 } } }),
    } as Response);

    renderLogin();
    fireEvent.change(screen.getByRole('textbox', { name: /email/i }), { target: { value: 'user@example.com' } });
    fireEvent.change(screen.getByLabelText(/password/i), { target: { value: 'secret123' } });
    fireEvent.click(screen.getByRole('button'));

    await waitFor(() => {
      expect(localStorage.getItem('klearcom_token')).toBe('bearer-token-abc');
      expect(mockNavigate).toHaveBeenCalledWith('/');
    });
  });

  it('shows an error message on failed login (401)', async () => {
    vi.mocked(fetch).mockResolvedValue({
      ok: false,
      status: 401,
      json: async () => ({ message: 'Invalid credentials' }),
    } as Response);

    renderLogin();
    fireEvent.change(screen.getByRole('textbox', { name: /email/i }), { target: { value: 'bad@example.com' } });
    fireEvent.change(screen.getByLabelText(/password/i), { target: { value: 'wrong' } });
    fireEvent.click(screen.getByRole('button'));

    await waitFor(() => {
      expect(screen.getByText(/invalid credentials/i)).toBeInTheDocument();
    });
    expect(localStorage.getItem('klearcom_token')).toBeNull();
  });

  it('does not navigate on failed login', async () => {
    vi.mocked(fetch).mockResolvedValue({
      ok: false,
      status: 401,
      json: async () => ({ message: 'Bad creds' }),
    } as Response);

    renderLogin();
    fireEvent.change(screen.getByRole('textbox', { name: /email/i }), { target: { value: 'x@x.com' } });
    fireEvent.change(screen.getByLabelText(/password/i), { target: { value: 'x' } });
    fireEvent.click(screen.getByRole('button'));

    await waitFor(() => screen.getByText(/bad creds/i));
    expect(mockNavigate).not.toHaveBeenCalled();
  });
});

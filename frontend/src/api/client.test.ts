import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { getStreamUrl } from './client';

// AC: T-27 — getStreamUrl correctly appends token and avoids double-slash/double-separator
describe('getStreamUrl', () => {
  beforeEach(() => {
    localStorage.clear();
  });

  afterEach(() => {
    localStorage.clear();
    vi.unstubAllEnvs();
  });

  it('appends ?token= when a token is stored', () => {
    localStorage.setItem('klearcom_token', 'mytoken123');
    const url = getStreamUrl('/discovery/jobs/1/stream?session_id=abc');
    expect(url).toContain('token=mytoken123');
  });

  it('uses & separator when path already has a query string', () => {
    localStorage.setItem('klearcom_token', 'tok');
    const url = getStreamUrl('/stream?session_id=abc');
    expect(url).toMatch(/session_id=abc&token=tok/);
  });

  it('uses ? separator when path has no query string', () => {
    localStorage.setItem('klearcom_token', 'tok');
    const url = getStreamUrl('/stream');
    expect(url).toMatch(/\/stream\?token=tok/);
  });

  it('omits token param when no token is stored', () => {
    const url = getStreamUrl('/stream?session_id=abc');
    expect(url).not.toContain('token=');
  });

  it('strips trailing slash from base URL to avoid double slash', () => {
    localStorage.setItem('klearcom_token', 'tok');
    // Default base is http://localhost:8080/api — path must not produce double slash
    const url = getStreamUrl('/stream');
    expect(url).not.toMatch(/\/\//);
  });

  it('URL-encodes the token value', () => {
    localStorage.setItem('klearcom_token', 'tok+special=value');
    const url = getStreamUrl('/stream');
    expect(url).not.toContain('tok+special=value');
    expect(url).toContain(encodeURIComponent('tok+special=value'));
  });
});

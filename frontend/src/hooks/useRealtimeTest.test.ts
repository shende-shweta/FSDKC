import { renderHook, act } from '@testing-library/react';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { useRealtimeTest } from './useRealtimeTest';

// Mock the api client
vi.mock('../api/client', () => ({
  api: {
    post: vi.fn(),
  },
  getStreamUrl: vi.fn((path: string) => `http://localhost:8080${path}`),
}));

import { api } from '../api/client';

class MockEventSource {
  static instances: MockEventSource[] = [];
  url: string;
  onmessage: ((event: MessageEvent) => void) | null = null;
  onerror: (() => void) | null = null;
  close = vi.fn();

  constructor(url: string) {
    this.url = url;
    MockEventSource.instances.push(this);
  }
}

describe('useRealtimeTest', () => {
  beforeEach(() => {
    MockEventSource.instances = [];
    vi.stubGlobal('EventSource', MockEventSource);
  });

  afterEach(() => {
    vi.unstubAllGlobals();
    vi.clearAllMocks();
  });

  it('has initial state: isRunning=false, events=[], progress=0', () => {
    const { result } = renderHook(() => useRealtimeTest('discovery'));

    expect(result.current.isRunning).toBe(false);
    expect(result.current.events).toEqual([]);
    expect(result.current.progress).toBe(0);
  });

  it('sets isRunning=true and opens EventSource when startDiscovery is called', async () => {
    vi.mocked(api.post).mockResolvedValue({ session_id: 'abc-123', message: 'started' });

    const { result } = renderHook(() => useRealtimeTest('discovery'));

    await act(async () => {
      await result.current.startDiscovery(1);
    });

    expect(result.current.isRunning).toBe(true);
    expect(MockEventSource.instances).toHaveLength(1);
  });

  it('appends event to events array when onmessage fires', async () => {
    vi.mocked(api.post).mockResolvedValue({ session_id: 'abc-123', message: 'started' });

    const { result } = renderHook(() => useRealtimeTest('discovery'));

    await act(async () => {
      await result.current.startDiscovery(1);
    });

    const source = MockEventSource.instances[0];
    const testEvent = { event: { type: 'step', progress: 50 } };

    act(() => {
      source.onmessage?.({ data: JSON.stringify(testEvent) } as MessageEvent);
    });

    expect(result.current.events).toHaveLength(1);
    expect(result.current.progress).toBe(50);
  });

  it('calls EventSource.close on cleanup', async () => {
    vi.mocked(api.post).mockResolvedValue({ session_id: 'abc-123', message: 'started' });

    const { result, unmount } = renderHook(() => useRealtimeTest('discovery'));

    await act(async () => {
      await result.current.startDiscovery(1);
    });

    const source = MockEventSource.instances[0];

    unmount();

    expect(source.close).toHaveBeenCalled();
  });
});

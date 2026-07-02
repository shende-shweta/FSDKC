import { useCallback, useEffect, useRef, useState } from 'react';
import { api, getStreamUrl } from '../api/client';
import type { TestEvent } from '../types';

interface StartResponse {
  session_id: string;
  message: string;
}

/**
 * Hook that manages a real-time test lifecycle:
 * 1. POSTs to the start endpoint to receive a session_id.
 * 2. Opens an EventSource to the matching stream endpoint.
 * 3. Accumulates events and tracks progress until the 'complete' event fires.
 * 4. Cleans up the EventSource when the component unmounts.
 */
export function useRealtimeTest(module: 'discovery' | 'connect') {
  const [events,    setEvents]    = useState<TestEvent[]>([]);
  const [isRunning, setIsRunning] = useState(false);
  const [progress,  setProgress]  = useState(0);
  const [error,     setError]     = useState<string | null>(null);
  const sourceRef = useRef<EventSource | null>(null);

  const cleanup = useCallback(() => {
    sourceRef.current?.close();
    sourceRef.current = null;
  }, []);

  // Ensure the EventSource is always torn down when the hook unmounts.
  useEffect(() => cleanup, [cleanup]);

  const connectStream = useCallback((resourceId: number, sessionId: string) => {
    cleanup();
    setEvents([]);
    setIsRunning(true);
    setProgress(0);
    setError(null);

    const path =
      module === 'discovery'
        ? `/discovery/jobs/${resourceId}/stream?session_id=${sessionId}`
        : `/connect/monitors/${resourceId}/stream?session_id=${sessionId}`;

    const source = new EventSource(getStreamUrl(path));
    sourceRef.current = source;

    source.onmessage = (msg) => {
      try {
        const doc = JSON.parse(msg.data) as TestEvent;
        setEvents((prev) => [...prev, doc]);

        const evt = doc.event;
        if (evt?.progress != null) setProgress(evt.progress);

        if (evt?.type === 'complete') {
          setIsRunning(false);
          setProgress(100);
          source.close();
        }
      } catch {
        // Ignore malformed SSE frames.
      }
    };

    source.onerror = () => {
      setIsRunning(false);
      setError('Stream connection lost.');
      cleanup();
    };
  }, [module, cleanup]);

  const startDiscovery = useCallback(async (jobId: number) => {
    const res = await api.post<StartResponse>(`/discovery/jobs/${jobId}/start`, {});
    connectStream(jobId, res.session_id);
    return res;
  }, [connectStream]);

  const startConnectCheck = useCallback(async (monitorId: number) => {
    const res = await api.post<StartResponse>(`/connect/monitors/${monitorId}/run-check`, {});
    connectStream(monitorId, res.session_id);
    return res;
  }, [connectStream]);

  const reset = useCallback(() => {
    cleanup();
    setEvents([]);
    setIsRunning(false);
    setProgress(0);
    setError(null);
  }, [cleanup]);

  return {
    events,
    isRunning,
    progress,
    error,
    startDiscovery,
    startConnectCheck,
    reset,
  };
}

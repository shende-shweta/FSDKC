import { useCallback, useEffect, useRef, useState } from 'react';
import { api, getStreamUrl } from '../api/client';
import { endpoints } from '../api/endpoints';
import type { TestEvent } from '../types';

interface StartResponse {
  session_id: string;
  message: string;
}

export function useRealtimeTest(module: 'discovery' | 'connect') {
  const [events, setEvents] = useState<TestEvent[]>([]);
  const [isRunning, setIsRunning] = useState(false);
  const [progress, setProgress] = useState(0);
  const sourceRef = useRef<EventSource | null>(null);

  const cleanup = useCallback(() => {
    sourceRef.current?.close();
    sourceRef.current = null;
  }, []);

  useEffect(() => cleanup, [cleanup]);

  const connectStream = useCallback((resourceId: number, sessionId: string) => {
    cleanup();
    setEvents([]);
    setIsRunning(true);
    setProgress(0);

    const path = module === 'discovery'
      ? endpoints.discovery.stream(resourceId, sessionId)
      : endpoints.connect.stream(resourceId, sessionId);

    const source = new EventSource(getStreamUrl(path));
    sourceRef.current = source;

    source.onmessage = (msg) => {
      const doc = JSON.parse(msg.data) as TestEvent;
      setEvents((prev) => [...prev, doc]);

      const evt = doc.event;
      if (evt?.progress != null) setProgress(evt.progress);

      if (evt?.type === 'complete') {
        setIsRunning(false);
        setProgress(100);
        source.close();
      }
    };

    source.onerror = () => {
      setIsRunning(false);
      cleanup();
    };
  }, [module, cleanup]);

  const startDiscovery = useCallback(async (jobId: number) => {
    const res = await api.post<StartResponse>(endpoints.discovery.start(jobId), {});
    connectStream(jobId, res.session_id);
    return res;
  }, [connectStream]);

  const startConnectCheck = useCallback(async (monitorId: number) => {
    const res = await api.post<StartResponse>(endpoints.connect.runCheck(monitorId), {});
    connectStream(monitorId, res.session_id);
    return res;
  }, [connectStream]);

  const reset = useCallback(() => {
    cleanup();
    setEvents([]);
    setIsRunning(false);
    setProgress(0);
  }, [cleanup]);

  return {
    events,
    isRunning,
    progress,
    startDiscovery,
    startConnectCheck,
    reset,
  };
}

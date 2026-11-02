import { render } from '@testing-library/react';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import LegacyMonitorPoller from './LegacyMonitorPoller';

vi.mock('../api/client', () => ({
  api: {
    get: vi.fn().mockResolvedValue({ computed: { reachability_pct: 95 } }),
  },
}));

describe('LegacyMonitorPoller', () => {
  beforeEach(() => {
    vi.useFakeTimers();
  });

  afterEach(() => {
    vi.useRealTimers();
    vi.clearAllMocks();
  });

  it('calls setInterval on mount', () => {
    const setIntervalSpy = vi.spyOn(globalThis, 'setInterval');
    const { unmount } = render(<LegacyMonitorPoller monitorId={1} />);

    expect(setIntervalSpy).toHaveBeenCalledWith(expect.any(Function), 3000);

    unmount();
  });

  it('calls clearInterval with the correct interval ID on unmount', () => {
    const clearIntervalSpy = vi.spyOn(globalThis, 'clearInterval');
    const { unmount } = render(<LegacyMonitorPoller monitorId={1} />);

    unmount();

    expect(clearIntervalSpy).toHaveBeenCalledWith(expect.anything());
  });
});

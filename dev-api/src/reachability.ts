/**
 * ReachabilityService — Single source of truth for reachability percentage calculation
 * AC-C03: TypeScript migration — replaces inline calculation in realtime.ts
 * 
 * Mirrors PHP backend/app/Services/ReachabilityService.php logic
 */

import type { CheckLike } from './types.js';

export class ReachabilityService {
  static readonly WINDOW = 20;
  static readonly ALERT_THRESHOLD = 90.0;

  /**
   * Computes reachability percentage from a collection of check results.
   * 
   * @param checks - Iterable or array of check-like objects with 'reachable' property
   * @returns Percentage from 0 to 100, rounded to 2 decimal places. Returns 100.0 for empty input.
   */
  computeRate(checks: CheckLike[] | Iterable<CheckLike>): number {
    const items = Array.isArray(checks) ? checks : Array.from(checks);

    if (items.length === 0) {
      return 100.0;
    }

    const successes = items.reduce((count, check) => {
      return check.reachable ? count + 1 : count;
    }, 0);

    return Math.round((successes / items.length) * 100 * 100) / 100;
  }

  /**
   * Derives monitor status from reachability rate.
   * 
   * @param rate - Reachability percentage (0-100)
   * @returns 'alert' if rate < ALERT_THRESHOLD, else 'active'
   */
  statusFromRate(rate: number): 'active' | 'alert' {
    return rate < ReachabilityService.ALERT_THRESHOLD ? 'alert' : 'active';
  }
}

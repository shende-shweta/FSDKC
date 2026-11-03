/**
 * Shared reachability helpers — mirror of backend ReachabilityService.
 * Keep in sync with backend/app/Services/ReachabilityService.php.
 */

export function calculatePct(checks) {
  if (!checks || checks.length === 0) return 100.0;
  const reachable = checks.filter((c) => c.reachable).length;
  return Math.round((reachable / checks.length) * 10000) / 100;
}

export function statusFromPct(pct) {
  return pct < 90 ? 'alert' : 'active';
}

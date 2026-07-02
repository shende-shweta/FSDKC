---
agent: task-list-agent
cli: Kiro CLI
llm: auto
run_id: 20260702T170518_dj0mn1
generated_at: 2026-07-02T14:56:39.477Z
---

# Migration Task List (continued) — Phase 3: Core Logic & Async Handling

> This file continues `03-task-list.md`. It covers tasks 3.3 (Core Logic Migration) and 3.4 (Async Handling Conversion).

---

## Phase 3 (continued)

### 3.3 Core Logic Migration

#### Task 3.3.1 — Migrate `ReachabilityService.php` → `dev-api/src/reachability.ts`

**Source (PHP):**
```php
public function computeRate(iterable $checks): float
{
    $items = is_array($checks) ? $checks : iterator_to_array($checks, false);
    $total = count($items);
    if ($total === 0) { return 100.0; }
    $successes = array_reduce($items, static function (int $carry, mixed $item): int {
        $reachable = is_array($item) ? ($item['reachable'] ?? false) : ($item->reachable ?? false);
        return $carry + ($reachable ? 1 : 0);
    }, 0);
    return round(($successes / $total) * 100, 2);
}
```

**Target (TypeScript):**
```typescript
// dev-api/src/reachability.ts
import type { CheckLike, MonitorStatus } from './types.js';

export class ReachabilityService {
  static readonly ALERT_THRESHOLD: number = 90.0;
  static readonly WINDOW: number = 20;

  computeRate(checks: CheckLike[] | Iterable<CheckLike>): number {
    const items: CheckLike[] = Array.isArray(checks)
      ? checks
      : Array.from(checks as Iterable<CheckLike>);

    const total = items.length;
    if (total === 0) return 100.0;

    const successes = items.reduce(
      (carry: number, item: CheckLike) => carry + (item.reachable ? 1 : 0),
      0
    );

    return Math.round((successes / total) * 10000) / 100; // round to 2dp
  }

  statusFromRate(rate: number): MonitorStatus {
    return rate < ReachabilityService.ALERT_THRESHOLD ? 'alert' : 'active';
  }
}
```

**Migration notes:**
- `iterator_to_array()` → `Array.from()`. Handles both arrays and any `Iterable<CheckLike>`.
- `array_reduce` with typed initial `0` → `.reduce((carry: number, item) => ..., 0)`.
- `round($n, 2)` → `Math.round(n * 10000) / 100` (equivalent: shift 4 decimal places, round, shift back 2).
- PHP accesses `$item->reachable` (object) or `$item['reachable']` (array). In TS the `CheckLike` interface normalises this — the caller is always typed.
- Edge case E-2 covered: `Array.from` preserves order.

---

#### Task 3.3.2 — Migrate `IvrTreeBuilder.php` → `dev-api/src/ivrTree.ts`

**Source (PHP):**
```php
public static function build(Collection $nodes, ?int $parentId = null): array
{
    return $nodes
        ->where('parent_id', $parentId)
        ->map(static fn (DiscoveryNode $node): array => [
            'id'          => $node->id,
            'prompt_text' => $node->prompt_text,
            'dtmf_option' => $node->dtmf_option,
            'node_type'   => $node->node_type,
            'depth'       => $node->depth,
            'children'    => self::build($nodes, $node->id),
        ])
        ->values()
        ->all();
}
```

**Target (TypeScript):**
```typescript
// dev-api/src/ivrTree.ts
import type { NodeLike, DiscoveryNodeRecord } from './types.js';

export function buildIvrTree(
  nodes: NodeLike[],
  parentId: number | null = null
): DiscoveryNodeRecord[] {
  return nodes
    .filter((n) => n.parent_id === parentId)  // strict === (Edge case E-8)
    .map((n): DiscoveryNodeRecord => ({
      id:          n.id,
      discovery_job_id: (n as DiscoveryNodeRecord).discovery_job_id,
      prompt_text: n.prompt_text,
      dtmf_option: n.dtmf_option,
      node_type:   n.node_type,
      depth:       n.depth,
      parent_id:   n.parent_id,
      children:    buildIvrTree(nodes, n.id),  // recursive
    }));
}
```

**Migration notes:**
- `Collection::where('parent_id', $parentId)` uses loose equality in Eloquent; this is replicated with **strict** `===` (Edge case E-8 — `null === null` is `true`, so root nodes still match).
- `->values()->all()` removes index keys; `.filter().map()` already produces a plain array in JS.
- Static class method → exported function (Pattern Selection 2.3).
- Recursion termination: when no `nodes` match `n.id` as a `parent_id`, the filter returns `[]` and recursion stops.

---

#### Task 3.3.3 — Migrate `LegacyDataMapper.php` → `dev-api/src/legacyMapper.ts`

**Source (PHP):**
```php
public function mapReportRow(array $row): array {
    return [
        'label'  => $row['name'] ?? 'Unknown',
        'metric' => $row['reachability_pct'] ?? 0,
        'region' => $row['country_code'] ?? 'N/A',
        'source' => 'legacy_mapper',
    ];
}
public function mapJobContext(array $context): array {
    return [
        'job_name' => $context['job_name'] ?? null,
        'phone'    => $context['phone_number'] ?? null,
        'depth'    => $context['menu_depth'] ?? 0,
    ];
}
```

**Target (TypeScript):**
```typescript
// dev-api/src/legacyMapper.ts
import type { ReportRow, JobContext } from './types.js';

export function mapReportRow(row: Record<string, unknown>): ReportRow {
  return {
    label:  typeof row['name'] === 'string'  ? row['name']  : 'Unknown',
    metric: typeof row['reachability_pct'] === 'number' ? row['reachability_pct'] : 0,
    region: typeof row['country_code'] === 'string' ? row['country_code'] : 'N/A',
    source: 'legacy_mapper',
  };
}

export function mapJobContext(context: Record<string, unknown>): JobContext {
  return {
    job_name: typeof context['job_name'] === 'string'  ? context['job_name']  : null,
    phone:    typeof context['phone_number'] === 'string' ? context['phone_number'] : null,
    depth:    typeof context['menu_depth'] === 'number'  ? context['menu_depth']  : 0,
  };
}
```

**Migration notes:**
- PHP `??` null-coalescing maps to explicit type guards in TypeScript (to satisfy `noUncheckedIndexedAccess`).
- PHP class instance → standalone exported functions (idiomatic TS).
- `Record<string, unknown>` replaces `mixed` input; no `any`.

---

#### Task 3.3.4 — Migrate `dev-api/src/store.js` → `dev-api/src/store.ts`

**Changes required:**
- [ ] Import all types from `./types.js`.
- [ ] Type `store` as `Store` (Task 3.2.5 interface).
- [ ] Type `buildTree` function signature (now delegates to `buildIvrTree` from `ivrTree.ts`).

```typescript
// dev-api/src/store.ts
import type { Store } from './types.js';
import { buildIvrTree } from './ivrTree.js';
import type { NodeLike, DiscoveryNodeRecord } from './types.js';

export const store: Store = {
  discoveryJobs: [
    // ... same seed data as store.js, now type-checked
  ],
  discoveryNodes: [ /* ... */ ],
  connectMonitors: [ /* ... */ ],
  connectChecks: [ /* ... */ ],
  nextJobId: 4,
  nextNodeId: 5,
  nextMonitorId: 4,
  nextCheckId: 4,
};

// Delegates to the centralised IvrTree builder (mirrors PHP IvrTreeBuilder usage).
export function buildTree(
  nodes: NodeLike[],
  parentId: number | null = null
): DiscoveryNodeRecord[] {
  return buildIvrTree(nodes, parentId);
}
```

**Migration notes:**
- The existing `buildTree` export in `store.js` is a duplicate of the PHP `IvrTreeBuilder::build()`. In TS it is a thin wrapper that calls `buildIvrTree`, ensuring a single implementation.
- Seed data types must be validated at compile time; TypeScript will catch any field that doesn't match the interface.

---

#### Task 3.3.5 — Migrate `dev-api/src/realtime.js` → `dev-api/src/realtime.ts`

**Key changes (JS → TS):**
- [ ] Import typed `store` from `./store.js`.
- [ ] Import `ReachabilityService` from `./reachability.js`.
- [ ] Type all step arrays as `ReadonlyArray<Readonly<DiscoveryStep>>` and `ReadonlyArray<Readonly<ConnectStep>>`.
- [ ] Instantiate `ReachabilityService` at module level (or inject via parameter for testability).
- [ ] Replace inline `successRate` calculation with `reachabilityService.computeRate()` — eliminates the last JS duplicate of this logic.

```typescript
// dev-api/src/realtime.ts
import { randomUUID } from 'crypto';
import { store } from './store.js';
import { storeDiagnostic, storeTestEvent, storeTranscript } from './mongo.js';
import { ReachabilityService } from './reachability.js';
import type { DiscoveryNodeRecord } from './types.js';

const sleep = (ms: number): Promise<void> => new Promise((r) => setTimeout(r, ms));
const reachabilityService = new ReachabilityService();

interface DiscoveryStep {
  event:       string;
  message:     string;
  progress:    number;
  transcript?: string;
  dtmf?:       string;
  node_type?:  string;
}

interface ConnectStep {
  event:    string;
  message:  string;
  progress: number;
}

const DISCOVERY_STEPS: ReadonlyArray<DiscoveryStep> = [
  { event: 'call_initiated',    message: 'Placing test call to IVR endpoint…',     progress: 10 },
  { event: 'call_connected',    message: 'Call connected — analyzing audio stream', progress: 20 },
  { event: 'prompt_detected',   message: 'Welcome prompt detected',                  transcript: 'Welcome. Press 1 for accounts, 2 for support.', progress: 35 },
  { event: 'dtmf_sent',         message: 'Sending DTMF: 1',                          dtmf: '1', progress: 45 },
  { event: 'dtmf_recognized',   message: 'DTMF input recognized',                    dtmf: '1', progress: 55 },
  { event: 'menu_discovered',   message: 'Sub-menu mapped: Accounts',                node_type: 'menu', progress: 70 },
  { event: 'speech_transcribed',message: 'Speech-to-text complete',                  transcript: 'Press 1 for balance inquiry.', progress: 82 },
  { event: 'transfer_validated',message: 'Agent transfer path validated',             progress: 92 },
  { event: 'traversal_complete',message: 'IVR discovery complete',                   progress: 100 },
];

const CONNECT_STEPS: ReadonlyArray<ConnectStep> = [
  { event: 'check_initiated', message: 'Starting TFN reachability check…',       progress: 10 },
  { event: 'dns_lookup',      message: 'Resolving toll-free routing tables',      progress: 25 },
  { event: 'carrier_selected',message: 'Carrier route selected',                  progress: 40 },
  { event: 'sip_invite',      message: 'Sending SIP INVITE to carrier gateway',   progress: 55 },
  { event: 'ringing',         message: 'Remote endpoint ringing…',               progress: 70 },
  { event: 'media_negotiation',message: 'RTP media stream established',           progress: 85 },
  { event: 'quality_analysis',message: 'Running MOS & latency analysis',          progress: 95 },
  { event: 'check_complete',  message: 'Reachability check complete',             progress: 100 },
];

export async function runDiscoveryTest(jobId: number, sessionId: string): Promise<void> {
  const job = store.discoveryJobs.find((j) => j.id === jobId);
  if (!job) return;

  job.status = 'running';
  job.started_at = new Date().toISOString();

  await storeTestEvent(sessionId, 'discovery', jobId,
    { type: 'status', status: 'running', message: 'Discovery test started', progress: 0 });

  let parentNodeId: number | null = null;

  for (const step of DISCOVERY_STEPS) {
    await sleep(800 + Math.random() * 700);

    await storeTestEvent(sessionId, 'discovery', jobId,
      { type: 'step', ...step, timestamp: new Date().toISOString() });

    if (step.transcript) {
      await storeTranscript('discovery', jobId,
        { event: step.event, transcript: step.transcript, session_id: sessionId });
    }

    if (step.event === 'menu_discovered') {
      const node: Omit<DiscoveryNodeRecord, 'children'> = {
        id:               store.nextNodeId++,
        discovery_job_id: jobId,
        parent_id:        parentNodeId,
        prompt_text:      step.transcript ?? 'Accounts menu discovered',
        dtmf_option:      step.dtmf ?? null,
        node_type:        step.node_type ?? 'menu',
        depth:            parentNodeId !== null ? 1 : 0,
      };
      store.discoveryNodes.push(node);
      parentNodeId = node.id;
      job.nodes_discovered = store.discoveryNodes.filter((n) => n.discovery_job_id === jobId).length;
    }
  }

  await storeDiagnostic('discovery', jobId, {
    session_id:      sessionId,
    mos_score:       4.0 + Math.random() * 0.5,
    latency_ms:      Math.floor(100 + Math.random() * 80),
    packet_loss_pct: 0,
    jitter_ms:       Math.floor(5 + Math.random() * 10),
  });

  job.status = 'completed';
  job.completed_at = new Date().toISOString();
  const depths = store.discoveryNodes
    .filter((n) => n.discovery_job_id === jobId)
    .map((n) => n.depth);
  job.menu_depth = depths.length > 0 ? Math.max(...depths) : 0; // Edge case E-9

  await storeTestEvent(sessionId, 'discovery', jobId, {
    type: 'complete', status: 'completed',
    message: `Discovery finished — ${job.nodes_discovered} nodes mapped`,
    progress: 100, nodes_discovered: job.nodes_discovered,
  });
}

export async function runConnectTest(monitorId: number, sessionId: string): Promise<void> {
  const monitor = store.connectMonitors.find((m) => m.id === monitorId);
  if (!monitor) return;

  await storeTestEvent(sessionId, 'connect', monitorId,
    { type: 'status', status: 'running', message: 'Connect test started', progress: 0 });

  const reachable = Math.random() > 0.2; // Edge case E-12

  for (const step of CONNECT_STEPS) {
    await sleep(600 + Math.random() * 500);
    const payload: Record<string, unknown> = { type: 'step', ...step, timestamp: new Date().toISOString() };
    if (step.event === 'check_complete') {
      payload['reachable'] = reachable;
      payload['latency_ms'] = reachable ? Math.floor(180 + Math.random() * 300) : null;
    }
    await storeTestEvent(sessionId, 'connect', monitorId, payload);
  }

  const latency: number | null = reachable ? Math.floor(180 + Math.random() * 300) : null;
  const check = {
    id:                 store.nextCheckId++,
    connect_monitor_id: monitorId,
    reachable,
    latency_ms:         latency,
    carrier_route:      monitor.carrier ? `${monitor.country_code} -> ${monitor.carrier} SIP` : null,
    failure_reason:     reachable ? null : 'Carrier routing failure',
    checked_at:         new Date().toISOString(),
  };
  store.connectChecks.unshift(check);

  // Use ReachabilityService instead of inline calculation — eliminates last JS duplicate.
  const recent = store.connectChecks
    .filter((c) => c.connect_monitor_id === monitorId)
    .slice(0, ReachabilityService.WINDOW);
  monitor.reachability_pct = reachabilityService.computeRate(recent);
  monitor.status = reachabilityService.statusFromRate(monitor.reachability_pct);
  monitor.last_checked_at = check.checked_at;

  await storeTranscript('connect', monitorId, {
    event:            reachable ? 'reachability_check_passed' : 'reachability_check_failed',
    session_id:       sessionId,
    toll_free_number: monitor.toll_free_number,
    latency_ms:       latency,
    failure_reason:   check.failure_reason,
  });

  await storeDiagnostic('connect', monitorId, {
    session_id:      sessionId,
    mos_score:       reachable ? 3.8 + Math.random() * 0.8 : 2.5 + Math.random() * 0.5,
    latency_ms:      latency ?? 0,
    packet_loss_pct: reachable ? 0 : 2.5 + Math.random() * 2,
  });

  await storeTestEvent(sessionId, 'connect', monitorId, {
    type: 'complete', status: reachable ? 'reachable' : 'failed',
    message: reachable ? 'TFN is reachable' : 'TFN reachability check failed',
    progress: 100, reachable, latency_ms: latency, check_id: check.id,
  });
}

export function createSession(): string {
  return randomUUID();
}
```

---

#### Task 3.3.6 — Migrate `dev-api/src/server.js` → `dev-api/src/server.ts`

**Changes required:**
- [ ] Add `import type { Request, Response, NextFunction } from 'express'`.
- [ ] Create `asyncHandler` wrapper to propagate errors to Express error middleware.
- [ ] Type all inline `req.body` destructuring with explicit types or zod schemas.
- [ ] Replace the dynamic `import('./mongo.js')` inside the diagnostics route with a static top-level import.
- [ ] Type `streamSession` function: `(res: Response, req: Request, sessionId: string) => void`.
- [ ] Type `serializeDoc` function: `(doc: Record<string, unknown> | null) => Record<string, unknown> | null`.
- [ ] Type `setupSse(res: Response): void` and `sendSse(res: Response, data: unknown): void`.

**Example — typed asyncHandler and route:**
```typescript
const asyncHandler =
  (fn: (req: Request, res: Response) => Promise<void>) =>
  (req: Request, res: Response, next: NextFunction): void => {
    fn(req, res).catch(next);
  };

// POST /api/connect/monitors
app.post(
  '/api/connect/monitors',
  (req: Request, res: Response): void => {
    const { name, toll_free_number, country_code, carrier } =
      req.body as {
        name?: string;
        toll_free_number?: string;
        country_code?: string;
        carrier?: string;
      };
    if (!name || !toll_free_number || !country_code) {
      res.status(422).json({ error: 'name, toll_free_number and country_code are required' });
      return;
    }
    const monitor = {
      id:               store.nextMonitorId++,
      name,
      toll_free_number,
      country_code,
      carrier:          carrier ?? null,
      status:           'active' as const,
      reachability_pct: 100,
      last_checked_at:  null,
    };
    store.connectMonitors.unshift(monitor);
    res.status(201).json({ data: monitor });
  }
);
```

---

### 3.4 Asynchronous Handling Conversion

#### Task 3.4.1 — Replace `usleep()` with `await sleep()`

- [ ] Confirm all callers of `runDiscoveryTest` and `runConnectTest` are `async` functions.
- [ ] Add `sleep` utility at the top of `realtime.ts`:
  ```typescript
  const sleep = (ms: number): Promise<void> => new Promise((r) => setTimeout(r, ms));
  ```
- [ ] Replace every `await sleep(800 + ...)` with the same expression from `realtime.js`.
- [ ] **Edge case E-3:** `sleep` values are in **milliseconds** in JS/TS; PHP `usleep` is in **microseconds**. Verify:
  - PHP `usleep(600_000)` = 600 ms → TS `await sleep(600)` ✓
  - PHP `usleep(500_000)` = 500 ms → TS `await sleep(500)` ✓
  - `realtime.js` already uses `800 + Math.random() * 700` ms (slightly longer than PHP to simulate network variance) — preserve this.

#### Task 3.4.2 — Convert `dispatch()->afterResponse()` pattern

PHP:
```php
dispatch(function () use ($id, $sessionId): void {
    app(RealTimeTestService::class)->runConnectTest($id, $sessionId);
})->afterResponse();
```

Node.js/Express equivalent (already in `server.js`; verify preserved in `server.ts`):
```typescript
app.post(
  '/api/connect/monitors/:id/run-check',
  asyncHandler(async (req: Request, res: Response): Promise<void> => {
    const monitorId = Number(req.params['id']);
    const monitor = store.connectMonitors.find((m) => m.id === monitorId);
    if (!monitor) { res.status(404).json({ error: 'Not found' }); return; }

    const sessionId = createSession();
    // Fire-and-forget: start the async test after the response is sent.
    // Edge case E-4: res.on('finish') is the Express equivalent of afterResponse().
    res.on('finish', () => {
      runConnectTest(monitorId, sessionId).catch(console.error);
    });
    res.json({ session_id: sessionId, message: 'Connect test started — connect to stream endpoint' });
  })
);
```
- [ ] Apply the same `res.on('finish', ...)` pattern to `POST /api/discovery/jobs/:id/start`.

#### Task 3.4.3 — SSE stream polling — type and guard

- [ ] Type the `poll` interval handle as `ReturnType<typeof setInterval>` (works for both Node and browser).
- [ ] Ensure `req.on('close', () => clearInterval(poll))` is called in `server.ts` (already present in `server.js`; verify).
- [ ] Type `events` from `getTestEvents(sessionId)` as `Record<string, unknown>[]`.

```typescript
async function streamSession(res: Response, req: Request, sessionId: string): Promise<void> {
  setupSse(res);
  let sent = 0;

  const poll: ReturnType<typeof setInterval> = setInterval(async () => {
    try {
      const events: Record<string, unknown>[] = await getTestEvents(sessionId);
      for (const evt of events.slice(sent)) {
        sendSse(res, serializeDoc(evt as Record<string, unknown>));
        sent++;
        const inner = (evt as { event?: { type?: string } }).event;
        if (inner?.type === 'complete') {
          clearInterval(poll);
          setTimeout(() => res.end(), 400);
          return;
        }
      }
    } catch (err: unknown) {
      console.error('SSE stream error:', err);
      clearInterval(poll);
      res.end();
    }
  }, 500);

  req.on('close', () => clearInterval(poll));
}
```

#### Task 3.4.4 — Frontend `useRealtimeTest.ts` — async tightening

The hook is already TypeScript and async-correct. The following type-tightening tasks remain:

- [ ] `startDiscovery` and `startConnectCheck` currently swallow errors (the `await api.post(...)` can reject but the caller in the page wraps in `try/finally`). Add JSDoc comment:
  ```typescript
  /**
   * Throws if the POST to the start endpoint fails.
   * Callers must handle via try/catch or .catch().
   */
  ```
- [ ] The `source.onmessage` handler silently catches all JSON parse errors. Add a `console.warn` inside the empty catch:
  ```typescript
  } catch {
    console.warn('[useRealtimeTest] malformed SSE frame:', msg.data);
  }
  ```
- [ ] The `cleanup` function in `useEffect` returns `cleanup` directly; the return type should be `() => void`. Verify `eslint-plugin-react-hooks` is satisfied.
- [ ] Tighten `module` param type — already typed as `'discovery' | 'connect'`; confirm it flows through to URL template literals without widening.

#### Task 3.4.5 — Frontend `api/client.ts` — type completeness check

Already TypeScript. Verify:
- [ ] Return type of `request<T>` is `Promise<T>` — confirmed.
- [ ] Network error catch re-throws as `Error` with message — confirmed.
- [ ] `api.delete<T>` generic is constrained — confirm callers pass explicit `T`.
- [ ] `getStreamUrl` strips trailing `/api` before re-appending — test with `VITE_API_URL` values with and without trailing slash.

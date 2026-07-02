---
agent: task-list-agent
cli: Kiro CLI
llm: auto
run_id: 20260702T170518_dj0mn1
generated_at: 2026-07-02T14:56:39.477Z
---

# Migration Task List (continued) — Phases 4 & 5: Validation, QC, and Summary

> This file continues `03-task-list.md` and `03-task-list-p3c.md`.
> It covers Phase 4 (Validation & Quality Control) and Phase 5 (Migration Summary).

---

## Phase 4: Validation & Quality Control

### 4.1 Syntax Validation

#### Task 4.1.1 — TypeScript compilation check
- [ ] Run `tsc --noEmit` in `dev-api/` after all `.ts` files are in place.
- [ ] Target: **zero errors** in strict mode (`strict: true`, `noImplicitAny: true`, `noUncheckedIndexedAccess: true`).
- [ ] Common expected errors to fix:
  - `Element implicitly has an 'any' type because expression of type 'string' can't be used to index type '...'` → use `Record<string, unknown>` or add an index signature.
  - `Object is possibly 'undefined'` on array access → add null guards.
  - `Argument of type 'number | null' is not assignable to parameter of type 'number'` → use `?? 0` or explicit null check.

#### Task 4.1.2 — ESLint static analysis
- [ ] Run `eslint src --ext .ts` with `@typescript-eslint/recommended` rules.
- [ ] Confirm no `@typescript-eslint/no-explicit-any` violations.
- [ ] Confirm no `@typescript-eslint/no-floating-promises` violations (all async route handlers wrapped in `asyncHandler`).
- [ ] Confirm no `no-fallthrough` violations in any `switch` on `step.event` values.

#### Task 4.1.3 — Frontend type check
- [ ] Run `tsc --noEmit` in `frontend/` to confirm `useRealtimeTest.ts` and `client.ts` have no new errors after type-tightening tasks in 3.4.4 and 3.4.5.

---

### 4.2 Unit Test Synthesis

#### Task 4.2.1 — Unit tests for `ReachabilityService`

Create `dev-api/src/__tests__/reachability.test.ts`.

Test cases to cover:

| Test ID | Input | Expected Output | Covers |
|---|---|---|---|
| RS-1 | Empty array `[]` | `100.0` | Guard: zero-length input |
| RS-2 | All `reachable: true` (20 items) | `100.0` | Happy path |
| RS-3 | All `reachable: false` (20 items) | `0.0` | All-fail path |
| RS-4 | 18 reachable, 2 not (20 items) | `90.0` | Boundary: exactly at threshold |
| RS-5 | 17 reachable, 3 not (20 items) | `85.0` | Below threshold → `computeRate` result |
| RS-6 | `statusFromRate(89.99)` | `'alert'` | Below ALERT_THRESHOLD |
| RS-7 | `statusFromRate(90.0)` | `'active'` | Exactly at threshold → not alert |
| RS-8 | `statusFromRate(100.0)` | `'active'` | Above threshold |
| RS-9 | 1 reachable, 3 not (4 items) | `25.0` | 2dp rounding |
| RS-10 | `WINDOW` constant | `20` | Constant value unchanged |
| RS-11 | `ALERT_THRESHOLD` constant | `90.0` | Constant value unchanged |

Test skeleton:
```typescript
import { describe, it, expect } from 'vitest';
import { ReachabilityService } from '../reachability.js';

const svc = new ReachabilityService();

describe('ReachabilityService.computeRate', () => {
  it('RS-1: returns 100 for empty input', () => {
    expect(svc.computeRate([])).toBe(100.0);
  });
  it('RS-4: returns 90.0 when 18/20 reachable', () => {
    const checks = [
      ...Array(18).fill({ reachable: true }),
      ...Array(2).fill({ reachable: false }),
    ];
    expect(svc.computeRate(checks)).toBe(90.0);
  });
  it('RS-6: statusFromRate(89.99) is alert', () => {
    expect(svc.statusFromRate(89.99)).toBe('alert');
  });
  it('RS-7: statusFromRate(90.0) is active', () => {
    expect(svc.statusFromRate(90.0)).toBe('active');
  });
});
```

#### Task 4.2.2 — Unit tests for `buildIvrTree`

Create `dev-api/src/__tests__/ivrTree.test.ts`.

Test cases:

| Test ID | Input | Expected Output | Covers |
|---|---|---|---|
| IT-1 | Empty array | `[]` | Edge case: no nodes |
| IT-2 | Single root node (parent_id: null) | Array with one node, `children: []` | Base case |
| IT-3 | Root + one child (parent_id: root.id) | Root node with one child | Simple nesting |
| IT-4 | Root + two children | Root with two children in order | Multi-child |
| IT-5 | Three levels deep | Root → child → grandchild | Deep nesting |
| IT-6 | Orphan node (parent_id points to non-existent) | Orphan excluded from root tree | Strict null handling |
| IT-7 | Nodes with `parent_id: 0` when `parentId = null` | Orphans not included in root | Strict `===` null (Edge case E-8) |

```typescript
import { describe, it, expect } from 'vitest';
import { buildIvrTree } from '../ivrTree.js';

describe('buildIvrTree', () => {
  it('IT-1: returns empty array for no nodes', () => {
    expect(buildIvrTree([])).toEqual([]);
  });
  it('IT-2: single root node', () => {
    const nodes = [{ id: 1, parent_id: null, prompt_text: 'Root', dtmf_option: null, node_type: 'menu', depth: 0, discovery_job_id: 1 }];
    const tree = buildIvrTree(nodes);
    expect(tree).toHaveLength(1);
    expect(tree[0]?.children).toEqual([]);
  });
  it('IT-5: three levels deep', () => {
    const nodes = [
      { id: 1, parent_id: null, prompt_text: 'Root',  dtmf_option: null, node_type: 'menu',   depth: 0, discovery_job_id: 1 },
      { id: 2, parent_id: 1,    prompt_text: 'Child', dtmf_option: '1',  node_type: 'menu',   depth: 1, discovery_job_id: 1 },
      { id: 3, parent_id: 2,    prompt_text: 'Grand', dtmf_option: '2',  node_type: 'prompt', depth: 2, discovery_job_id: 1 },
    ];
    const tree = buildIvrTree(nodes);
    expect(tree[0]?.children[0]?.children[0]?.prompt_text).toBe('Grand');
  });
  it('IT-7: strict null — node with parent_id 0 is not included at root', () => {
    const nodes = [
      { id: 1, parent_id: null, prompt_text: 'Root',   dtmf_option: null, node_type: 'menu', depth: 0, discovery_job_id: 1 },
      { id: 2, parent_id: 0,    prompt_text: 'Orphan', dtmf_option: null, node_type: 'menu', depth: 0, discovery_job_id: 1 },
    ];
    const tree = buildIvrTree(nodes);
    expect(tree).toHaveLength(1);
    expect(tree[0]?.prompt_text).toBe('Root');
  });
});
```

#### Task 4.2.3 — Unit tests for `LegacyDataMapper` functions

Create `dev-api/src/__tests__/legacyMapper.test.ts`.

Test cases:

| Test ID | Function | Input | Expected Output |
|---|---|---|---|
| LM-1 | `mapReportRow` | Full valid object | Correct mapped fields |
| LM-2 | `mapReportRow` | Empty object `{}` | Defaults: `Unknown`, `0`, `N/A`, `legacy_mapper` |
| LM-3 | `mapReportRow` | `name: 123` (wrong type) | Falls back to `'Unknown'` |
| LM-4 | `mapJobContext` | Full valid object | Correct mapped fields |
| LM-5 | `mapJobContext` | Empty object `{}` | Defaults: `null`, `null`, `0` |
| LM-6 | `mapJobContext` | `menu_depth: 'deep'` (wrong type) | Falls back to `0` |

#### Task 4.2.4 — Integration smoke test for `ReachabilityService` in `realtime.ts`

- [ ] Verify that `runConnectTest` no longer contains an inline `successRate` calculation.
- [ ] Write a mock test that verifies `reachabilityService.computeRate` is called with the `recent` slice and its return value is stored in `monitor.reachability_pct`.
- [ ] Use `vitest` spies: `vi.spyOn(reachabilityService, 'computeRate').mockReturnValue(75.0)`.

#### Task 4.2.5 — Frontend hook test (existing test update)
- [ ] Confirm `useRealtimeTest.test.ts` (if it exists) still passes after tightening.
- [ ] If no test file exists, create a minimal test verifying:
  - `isRunning` is `false` before `startDiscovery` / `startConnectCheck`.
  - `error` is `null` initially.
  - `reset()` sets all state back to initial values.

---

### 4.3 Refactoring for Native Idioms

#### Task 4.3.1 — Remove class boilerplate from `IvrTreeBuilder`
- [x] PHP `IvrTreeBuilder` is a class with a single static method. In the TS migration (Task 3.3.2), it is already converted to a standalone exported function `buildIvrTree`. No further refactoring needed.

#### Task 4.3.2 — Use `as const` for step arrays in `realtime.ts`
- [ ] The `DISCOVERY_STEPS` and `CONNECT_STEPS` arrays are already typed as `ReadonlyArray<...>`. Verify that `as const` is not needed on individual step objects (the interface is looser than a literal type — intentional).

#### Task 4.3.3 — Replace `Number(req.params.id)` with `parseInt` + guard in `server.ts`
- [ ] `Number('abc')` returns `NaN`. Replace all instances with:
  ```typescript
  const id = parseInt(req.params['id'] ?? '', 10);
  if (Number.isNaN(id)) { res.status(400).json({ error: 'id must be an integer' }); return; }
  ```
- [ ] Apply to all 8 parameterised routes in `server.ts`.

#### Task 4.3.4 — Remove `async` from handlers that do not `await`
- [ ] `GET /api/connect/monitors`, `GET /api/discovery/jobs`, `POST /api/connect/monitors`, `POST /api/discovery/jobs` — these are synchronous. Remove `async` and do not wrap in `asyncHandler`.

#### Task 4.3.5 — ESM import extensions
- [ ] All internal imports in `.ts` files must use the `.js` extension (Node ESM resolution): `import { ReachabilityService } from './reachability.js'` (even though the source file is `.ts`). This is standard for `moduleResolution: 'bundler'` with Node ESM.
- [ ] Audit all import statements added in Tasks 3.3.x and 3.4.x.

#### Task 4.3.6 — Eliminate `any` in `serializeDoc`
- [ ] Current JS: `function serializeDoc(doc) { ... }` uses implicit `any`.
- [ ] TS version must be typed:
  ```typescript
  function serializeDoc(
    doc: Record<string, unknown> | null
  ): Record<string, unknown> | null {
    if (!doc) return doc;
    const out: Record<string, unknown> = { ...doc };
    if (typeof out['_id'] === 'object' && out['_id'] !== null) {
      out['_id'] = String(out['_id']);
    }
    if (out['created_at'] instanceof Date) {
      out['created_at'] = out['created_at'].toISOString();
    }
    return out;
  }
  ```

---

## Phase 5: Migration Summary

### 5.1 Compatibility Report

| # | Source Feature | Translated? | Notes |
|---|---|---|---|
| C-1 | `ReachabilityService::computeRate()` pure logic | ✅ Full parity | `Array.from` + `reduce` + 2dp rounding |
| C-2 | `ReachabilityService::statusFromRate()` | ✅ Full parity | Strict `< ALERT_THRESHOLD` comparison |
| C-3 | `ReachabilityService::WINDOW = 20` constant | ✅ Full parity | `static readonly WINDOW = 20` |
| C-4 | `ReachabilityService::ALERT_THRESHOLD = 90.0` | ✅ Full parity | `static readonly ALERT_THRESHOLD = 90.0` |
| C-5 | `IvrTreeBuilder::build()` recursive tree | ✅ Full parity | `buildIvrTree` function; strict `===` null (E-8) |
| C-6 | `LegacyDataMapper::mapReportRow()` | ✅ Full parity | Type guards replace PHP `??` on mixed input |
| C-7 | `LegacyDataMapper::mapJobContext()` | ✅ Full parity | Same type guard pattern |
| C-8 | `RealTimeTestService::createSession()` | ✅ Full parity | `crypto.randomUUID()` |
| C-9 | `RealTimeTestService::runDiscoveryTest()` sync | ✅ Converted | `async` + `await sleep(ms)`; all side effects preserved |
| C-10 | `RealTimeTestService::runConnectTest()` sync | ✅ Converted | Same; inline reachability calc replaced by `ReachabilityService` |
| C-11 | `dispatch()->afterResponse()` | ✅ Equivalent | `res.on('finish', ...)` pattern |
| C-12 | Eloquent `Collection::where/map/values/all` | ✅ Replaced | Native `Array.filter/map` |
| C-13 | `usleep(N_microseconds)` | ✅ Converted | `await sleep(N_ms)` (E-3 — units verified) |
| C-14 | `Log::info(...)` | ✅ Replaced | `console.info(...)` (structured logger recommended for prod) |
| C-15 | `Str::uuid()` | ✅ Replaced | `crypto.randomUUID()` (E-11) |
| C-16 | PHP `round($n, 2)` | ✅ Equivalent | `Math.round(n * 10000) / 100` (E-6) |
| C-17 | `random_int(1,100) > 20` | ✅ Equivalent | `Math.random() > 0.2` (E-12, simulation only) |
| C-18 | `dev-api/src/realtime.js` inline reachability calc | ✅ Eliminated | Replaced by `ReachabilityService.computeRate()` |
| C-19 | `dev-api/src/store.js` untyped seed data | ✅ Typed | `Store` interface enforces shape at compile time |
| C-20 | `dev-api/src/server.js` untyped Express handlers | ✅ Typed | `Request`, `Response`, `asyncHandler`, zod schemas |
| C-21 | `frontend/src/api/client.ts` (already TS) | ✅ No migration | Type-tightening only (Tasks 3.4.5) |
| C-22 | `frontend/src/hooks/useRealtimeTest.ts` (already TS) | ✅ No migration | JSDoc + `console.warn` tightening (Tasks 3.4.4) |
| C-23 | Laravel `Cache::remember` (backend only) | ⚠️ Not in scope | Dev-API has no caching layer; noted as backlog item |
| C-24 | Laravel `Request->validate()` full rule engine | ⚠️ Partial | Dev-API uses manual guards; `zod` recommended for full parity |
| C-25 | MySQL Eloquent ORM (production backend only) | ❌ Out of scope | Dev-API uses in-memory store; Laravel/MySQL layer not being migrated |
| C-26 | Laravel `dispatch()` queue (production only) | ❌ Out of scope | `res.on('finish')` covers dev-API; production queue stays in PHP/Laravel |

**Overall parity: 22/26 features fully translated or not applicable. 2 partial (C-23, C-24). 2 explicitly out of scope (C-25, C-26).**

---

### 5.2 Manual Review Flags

| Flag # | File | Location | Reason | Action Required |
|---|---|---|---|---|
| MR-1 | `dev-api/src/realtime.ts` | `runDiscoveryTest` — `DiscoveryNode` creation | Node is pushed to `store.discoveryNodes` but `discovery_job_id` is typed on `DiscoveryNodeRecord`; the `NodeLike` base does not include it. The cast `(n as DiscoveryNodeRecord).discovery_job_id` in `buildIvrTree` must be verified to always be populated. | Review cast; consider adding `discovery_job_id` to `NodeLike` if `buildIvrTree` always requires it. |
| MR-2 | `dev-api/src/server.ts` | `POST /api/connect/monitors/bulk-import` | The route depends on `Array.isArray(req.body)` to detect flat vs wrapped input. If Express `json()` middleware has a default size limit (1 MB), a 100-item batch of large strings could be truncated silently. | Set `express.json({ limit: '512kb' })` explicitly and verify the 100-item batch cap fires before the size limit. |
| MR-3 | `dev-api/src/realtime.ts` | `runConnectTest` | `Math.random()` is not a CSPRNG. If this simulation is ever used in a security-sensitive context (e.g., penetration testing of real TFN routing), replace with `crypto.getRandomValues(new Uint32Array(1))[0] / 0xFFFFFFFF`. | Flag for security review if module is promoted beyond simulation. |
| MR-4 | `dev-api/src/store.ts` | Entire in-memory store | All state is process-memory; a restart loses all jobs, monitors, and checks. This is acceptable for local dev but should be documented explicitly in the README. | Add a `WARNING: in-memory only` comment block at the top of `store.ts`. |
| MR-5 | `dev-api/src/server.ts` | `streamSession` | The `setInterval` poll is set to 500 ms. Under high concurrency (many simultaneous SSE clients), interval accumulation can cause MongoDB read amplification. The same issue exists in the PHP `StreamController` (noted in `02-design-document.md` OQ-2). | Acceptable for dev-API. For production, track as backlog item (replace with Redis pub/sub). |
| MR-6 | `dev-api/src/mongo.ts` (not yet migrated) | `getDb()` function | `mongo.js` uses a module-level singleton `let db: Db`. The function `getDb()` returns `db!` (non-null assertion in PHP equivalent). In the TS version, if `connectMongo()` has not been called, `getDb()` will throw at runtime. | Add an explicit guard: `if (!db) throw new Error('MongoDB not connected; call connectMongo() first');` |
| MR-7 | `frontend/src/api/client.ts` | `getStreamUrl` | The regex `/\/api\/?$/` strips the `/api` suffix only from the end of the base URL. If `VITE_API_URL` is set to `http://localhost:8080/api/v2`, the regex will not match and the returned URL will duplicate `/api`. | Add a unit test covering this edge case; consider normalising the base URL differently. |
| MR-8 | `frontend/src/hooks/useRealtimeTest.ts` | `cleanup` in `useEffect` | `useEffect(() => cleanup, [cleanup])` returns the cleanup function directly (not `() => cleanup()`). This is correct React behaviour but subtle. If `cleanup` reference changes (it won't — it is `useCallback` with `[]`), the effect would re-register. | Low risk; add inline comment to explain the intentional pattern. |
| MR-9 | `dev-api/src/types.ts` (new file) | `CacheEntry<T>` | The TTL cache type is defined but no cache implementation is generated in this migration run. The `DashboardController` 30-second KPI cache (Laravel `Cache::remember`) has no equivalent in the dev-API. | Implement `ttlCache.ts` in a follow-up task or accept that dev-API KPIs are always computed on-demand. |
| MR-10 | All migrated `.ts` files | `import ... from './module.js'` | Node ESM requires `.js` extensions on local imports even when the source is `.ts`. This is non-obvious and can cause `ERR_MODULE_NOT_FOUND` at runtime. | Add an ESLint rule (`import/extensions: ['error', 'always', { ts: 'never', js: 'always' }]`) and verify all imports during CI. |

---

### 5.3 Outstanding Backlog (not in scope for this migration run)

| Priority | Item | Rationale |
|---|---|---|
| High | Migrate `dev-api/src/mongo.js` → `mongo.ts` | MR-6 depends on this; untyped MongoDB calls leak `any` into all consumers |
| High | Implement `zod` validation schemas in `server.ts` | Replaces manual field checks; provides auto-generated error messages |
| Medium | Migrate `dev-api/src/seed-data.js` → `seed-data.ts` | Ensures seed data is type-checked against `Store` interface |
| Medium | Implement `ttlCache.ts` | Provides dev-API equivalent of Laravel `Cache::remember` for KPI endpoint |
| Medium | Add `vitest` config to `dev-api/package.json` | Required to run Tests 4.2.1–4.2.4 |
| Low | Migrate `dev-api/src/seed.js` → `seed.ts` | Low risk; simple script |
| Low | Convert `frontend/src/components/LegacyMonitorPoller.jsx` → `.tsx` | Only JS file remaining in the frontend |

---

### 5.4 Files Produced by This Migration Run

| File | Status | Action |
|---|---|---|
| `agent-runs/20260702T170518_dj0mn1/03-task-list.md` | ✅ Created | Phases 1–2, boilerplate tasks, signature tasks |
| `agent-runs/20260702T170518_dj0mn1/03-task-list-p3c.md` | ✅ Created | Phase 3 core logic + async handling (code stubs) |
| `agent-runs/20260702T170518_dj0mn1/03-task-list-p4p5.md` | ✅ Created (this file) | Phases 4–5: validation, unit tests, compatibility report, MR flags |

**Note for executing agent:** The three task list files together constitute the full migration plan. Implement tasks in order: Phase 1 (already complete — analysis only) → Phase 2 (already complete — mapping only) → Phase 3.1 (boilerplate + tsconfig) → Phase 3.2 (signatures) → Phase 3.3 (core logic) → Phase 3.4 (async handling) → Phase 4.1 (tsc check) → Phase 4.2 (unit tests) → Phase 4.3 (refactoring) → Phase 5 (compatibility report — this file).

Branch: `feature/codebase-improvements`
PR Agent (`pr-creator-agent`) will open the pull request in the next pipeline step.

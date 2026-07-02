---
agent: task-list-agent
cli: Kiro CLI
llm: auto
run_id: 20260702T170518_dj0mn1
generated_at: 2026-07-02T14:56:39.477Z
---

# Migration Task List — Klearcom FSDKC
## Source → Target: PHP 8.x / Laravel 10 + Node.js/Express ESM → Node.js TypeScript (ESM)

> **Scope:** Migrate the three backend service/support classes introduced or refactored by the Code Analyser Agent —
> `ReachabilityService.php`, `IvrTreeBuilder.php`, `LegacyDataMapper.php`, and `RealTimeTestService.php` —
> plus the dev-API layer (`server.js`, `realtime.js`, `store.js`) to fully-typed TypeScript.
> The React/TypeScript frontend (`client.ts`, `useRealtimeTest.ts`) is already TypeScript; its tasks cover type-tightening and idiom alignment only.
>
> **Target language:** Node.js 20 LTS, TypeScript 5.x (strict mode), ESM modules.
> **Output directory (per file):** `dev-api/src/` (dev-api files), `frontend/src/` (frontend files).

---

## Phase 1: Source Analysis

### 1.1 Language Identification
- [x] **Source language confirmed:** PHP 8.1+ (readonly properties, named arguments, first-class callables, spread in arrays, `enum` types implied by string unions).
- [x] **Dev-API source language confirmed:** JavaScript (Node.js 20, ESM — `import`/`export`, top-level `await`, `randomUUID` from `crypto`).
- [x] **Frontend source language confirmed:** TypeScript 5 / React 18 (strict tsconfig, `.tsx` JSX, React Query v5 API).
- [x] **PHP syntax standards flagged for migration:**
  - Constructor property promotion (`private readonly MongoService $mongo`)
  - Named spread: `['type' => 'step', ...$step]` (PHP array union spread)
  - `iterator_to_array()` for Eloquent Collection → iterable unwrap
  - `usleep(600_000)` microsecond sleep (no native async in PHP; maps to `await sleep(600)` in TS)
  - `random_int(1, 100)` → `Math.random()`
  - `round($val, 2)` → `Math.round(val * 100) / 100`
  - `Str::uuid()` → `crypto.randomUUID()`
  - `Log::info(...)` → `console.info(...)` or a structured logger
  - `dispatch()->afterResponse()` (Laravel deferred job) → fire-and-forget `Promise` with `.catch()`

### 1.2 Dependency Mapping

| PHP / Laravel Dependency | Role | Target Equivalent |
|---|---|---|
| `Illuminate\Support\Collection` | Lazy collection with `where/map/values/all/count/max` | Native `Array` + array methods (`filter`, `map`, `reduce`, `at`, `length`) |
| `Illuminate\Support\Str::uuid()` | UUID v4 generation | `crypto.randomUUID()` (Node built-in) |
| `Illuminate\Support\Facades\Log` | Structured logging | `console.info/error` or `pino` |
| `Illuminate\Http\JsonResponse` | HTTP response wrapper | `express.Response` (`res.json()`) |
| `Illuminate\Http\Request` | HTTP request binding + validation | `express.Request` + `zod` schema validation |
| `App\Models\*` (Eloquent) | ORM models with relations | In-memory `store` object (dev-api) or a typed repository interface |
| `usleep(N)` | Synchronous microsecond pause | `await sleep(N / 1000)` where `sleep = (ms) => new Promise(r => setTimeout(r, ms))` |
| `random_int(1, 100)` | Cryptographically random integer | `Math.floor(Math.random() * 100) + 1` (or `crypto.getRandomValues` for CSPRNG) |
| `round($n, 2)` | Round to 2 decimal places | `Math.round(n * 100) / 100` |
| `array_reduce()` | Fold over array | `Array.prototype.reduce()` |
| `iterator_to_array()` | Materialise iterable | `Array.from(iterable)` |
| `count($arr)` | Array length | `arr.length` |
| `MongoDB\Driver\*` (PHP driver) | MongoDB reads/writes | `mongodb` npm package (`MongoClient`) — already used in `mongo.js` |
| `Cache::remember(key, ttl, fn)` | Laravel cache with TTL | Simple in-memory `Map` with timestamp, or `node-cache` / Redis |

### 1.3 Logic Extraction

#### A. `ReachabilityService` — Pure Computation
- **`computeRate(checks)`:** Accepts an iterable of objects with a `reachable: boolean` property. Returns `float` (0–100, 2dp). Guard: empty collection → `100.0`.
- **`statusFromRate(rate)`:** Returns `'alert'` if `rate < 90.0`, else `'active'`.
- **Constants:** `ALERT_THRESHOLD = 90.0`, `WINDOW = 20`.
- **No side effects.** Pure function — trivially unit-testable.

#### B. `IvrTreeBuilder` — Recursive Tree Construction
- **`build(nodes, parentId?)`:** Filters `nodes` by `parent_id === parentId`, maps each to a node DTO with a recursive `children` call. Returns a nested array.
- **Termination:** Recursion terminates when no nodes match the current `parentId`.
- **No side effects.** Pure function.

#### C. `LegacyDataMapper` — Array Key Remapping
- **`mapReportRow(row)`:** Reads `name`, `reachability_pct`, `country_code` from input array; returns `{ label, metric, region, source }`.
- **`mapJobContext(context)`:** Reads `job_name`, `phone_number`, `menu_depth`; returns `{ job_name, phone, depth }`.
- **No side effects.** Pure mapping.

#### D. `RealTimeTestService` — Async Orchestration
- **`createSession()`:** Returns UUID string.
- **`runDiscoveryTest(jobId, sessionId)`:** Mutates job status → runs 6-step loop (each `usleep(600_000)`) → writes MongoDB events/transcripts/diagnostics → creates `DiscoveryNode` record → updates job with node count and depth.
- **`runConnectTest(monitorId, sessionId)`:** Mutates monitor → runs 5-step loop (each `usleep(500_000)`) → creates `ConnectCheckResult` → computes reachability via `ReachabilityService` → updates monitor → stores transcript + complete event.
- **Side effects:** MongoDB writes (`storeTestEvent`, `storeTranscript`, `storeDiagnostic`), DB record creates/updates, structured logging.

#### E. `dev-api/src/server.js` — HTTP Route Layer
- 26 Express routes covering health, dashboard KPIs, discovery CRUD + SSE, connect CRUD + SSE, bulk import.
- Input validation guards (422 on missing required fields, 100-item batch cap).
- SSE helper: `setupSse`, `sendSse`, `streamSession` (interval-based MongoDB polling, `req.on('close')` cleanup).

#### F. `dev-api/src/realtime.js` — Async Test Simulation
- Same orchestration as PHP `RealTimeTestService` but uses `async/await` with `sleep()` instead of `usleep()`.
- 9-step discovery sequence, 8-step connect sequence.
- Mutates in-memory `store` directly rather than via ORM.

### 1.4 Edge Case Detection

| # | PHP / JS Quirk | Migration Risk | Handling Required |
|---|---|---|---|
| E-1 | PHP array spread `[...$step]` merges associative arrays | LOW | TS object spread `{ ...step }` — identical semantics |
| E-2 | `iterator_to_array($checks, false)` — preserves non-keyed order | LOW | `Array.from(checks)` |
| E-3 | `usleep()` is synchronous in PHP (blocks the process) | HIGH | Must become `await sleep(ms)` — all callers must be `async` |
| E-4 | PHP `dispatch()->afterResponse()` — fire after HTTP response is sent | MEDIUM | `setImmediate(() => runTest(...).catch(console.error))` or `res.on('finish', ...)` |
| E-5 | PHP `public const` on a class → accessed as `ClassName::CONST` | LOW | `static readonly ALERT_THRESHOLD = 90.0` on TS class, or plain `export const` |
| E-6 | PHP `round($n, 2)` rounds half-up; JS `Math.round` rounds half-up too | LOW | Behaviour matches; no change needed |
| E-7 | PHP `array_reduce` initial value is `0` (int); TS `reduce` initial must be typed | LOW | `reduce((carry, item) => carry + ..., 0 as number)` |
| E-8 | Eloquent `Collection::where('parent_id', null)` matches strict `null` | MEDIUM | `nodes.filter(n => n.parent_id === parentId)` — use `===` not `==` |
| E-9 | PHP `max()` returns `false` on empty array — guarded with `?? 0` | LOW | `Math.max(...arr)` returns `-Infinity` on empty — guard with `arr.length > 0 ? Math.max(...arr) : 0` |
| E-10 | MongoDB ObjectId `.toString()` in `serializeDoc` | LOW | Already handled in JS `serializeDoc`; preserve in TS version |
| E-11 | PHP `Str::uuid()` returns a `Stringable`; cast with `(string)` | LOW | `crypto.randomUUID()` returns `string` directly |
| E-12 | `random_int(1,100) > 20` — CSPRNG boolean | LOW | `Math.random() > 0.2` is sufficient for simulation (not security-sensitive here) |

---

## Phase 2: Environment & Target Mapping

### 2.1 Library Matching

| Source Dependency | Target Equivalent | Notes |
|---|---|---|
| `Illuminate\Support\Collection` | Native `Array` | Use `filter/map/reduce/find/flatMap` — no external lib needed |
| `Illuminate\Support\Str::uuid()` | `crypto.randomUUID()` | Built into Node 14.17+; no import needed in Node 20 |
| `Illuminate\Support\Facades\Log` | `console.info/warn/error` | For production, use `pino` (structured JSON logging) |
| `MongoDB\Driver` (PHP) | `mongodb` npm (already in `dev-api`) | `MongoClient` from `mongodb` package; already used in `mongo.js` |
| Laravel `Cache::remember` | Custom `ttlCache<T>` utility | Implement as a `Map<string, { value: T; expiresAt: number }>` wrapper |
| Laravel `Request->validate()` | `zod` schema `.parse()` | `zod` is idiomatic for runtime validation in TypeScript |
| PHP `usleep(N_microseconds)` | `await sleep(N_ms)` | `const sleep = (ms: number) => new Promise<void>(r => setTimeout(r, ms))` |
| `random_int(1,100)` | `Math.floor(Math.random() * 100) + 1` | Simulation only; acceptable |
| `round($n, 2)` | `Math.round(n * 100) / 100` | Identical rounding behaviour |

### 2.2 Type Mapping

| PHP Type | TypeScript Type | Notes |
|---|---|---|
| `float` | `number` | |
| `int` | `number` | |
| `bool` | `boolean` | |
| `string` | `string` | |
| `array<K,V>` (associative) | `Record<K, V>` or typed interface | |
| `array<int, T>` (list) | `T[]` | |
| `?T` (nullable) | `T \| null` | |
| `iterable<T>` | `Iterable<T> \| T[]` | |
| `mixed` | `unknown` | Avoid `any`; narrow with type guards |
| `void` | `void` | |
| `Collection<int, DiscoveryNode>` | `DiscoveryNode[]` | Already defined in `frontend/src/types/index.ts` |
| `ConnectCheckResult` (Eloquent model) | `ConnectCheckResult` interface (already in `types/index.ts`) | |
| `ConnectMonitor` (Eloquent model) | `ConnectMonitor` interface (already in `types/index.ts`) | |
| `DiscoveryJob` (Eloquent model) | `DiscoveryJob` interface (already in `types/index.ts`) | |
| `JsonResponse` | `void` (handler calls `res.json()`) | |
| `Request` | `express.Request` | |

**New types needed (not yet in `types/index.ts`):**

```typescript
// ReachabilityService
export interface CheckLike { reachable: boolean; }
export type MonitorStatus = 'active' | 'paused' | 'alert';

// IvrTreeBuilder
export interface NodeLike {
  id: number;
  parent_id: number | null;
  prompt_text: string;
  dtmf_option: string | null;
  node_type: string;
  depth: number;
}

// LegacyDataMapper
export interface ReportRow  { label: string; metric: number; region: string; source: string; }
export interface JobContext { job_name: string | null; phone: string | null; depth: number; }

// TTL cache
export interface CacheEntry<T> { value: T; expiresAt: number; }
```

### 2.3 Pattern Selection

| PHP Pattern | Target Idiomatic Pattern | Rationale |
|---|---|---|
| Class with `private readonly` injected deps | TypeScript `class` with `constructor(private readonly …)` | Identical; TS constructor shorthand is exactly PHP's property promotion |
| `public const WINDOW = 20` on class | `static readonly WINDOW = 20` on TS class, **or** `export const WINDOW = 20` module-level | Module-level constants preferred for tree-shaking; class constants for co-location |
| Static utility class (`IvrTreeBuilder`) | Exported standalone function `buildIvrTree(nodes, parentId?)` | Functional style is more idiomatic in TS/Node; avoids empty class boilerplate |
| Instance class (`ReachabilityService`) | Either a class (for DI/testing) or a plain object `export const reachabilityService = { computeRate, statusFromRate }` | Class preferred to match PHP structure and allow constructor injection in tests |
| `LegacyDataMapper` instance class | Standalone exported functions `mapReportRow` / `mapJobContext` | No state; functions are idiomatic |
| `RealTimeTestService` class with injected services | `class RealTimeTestService` with constructor DI of `MongoService`-like dependency | Matches PHP; preserves testability |
| Laravel `dispatch()->afterResponse()` | `res.on('finish', () => runTest().catch(console.error))` | Express-native deferred execution after response is flushed |
| PHP `foreach` over Eloquent Collection | `for...of` loop over typed array | Structurally identical; use `for...of` for async `await` inside loop |

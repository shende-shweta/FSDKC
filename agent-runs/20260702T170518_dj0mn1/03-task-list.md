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
  - `usleep(600_000)` microsecond sleep (maps to `await sleep(600)` in TS)
  - `random_int(1, 100)` → `Math.random()`
  - `round($val, 2)` → `Math.round(val * 100) / 100`
  - `Str::uuid()` → `crypto.randomUUID()`
  - `Log::info(...)` → `console.info(...)` or a structured logger
  - `dispatch()->afterResponse()` → fire-and-forget `Promise` with `.catch()`

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
| `random_int(1, 100)` | Cryptographically random integer | `Math.floor(Math.random() * 100) + 1` |
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
- **Constants:** `ALERT_THRESHOLD = 90.0`, `WINDOW = 20`. No side effects.

#### B. `IvrTreeBuilder` — Recursive Tree Construction
- **`build(nodes, parentId?)`:** Filters `nodes` by `parent_id === parentId`, maps each to a node DTO with a recursive `children` call. Terminates when no nodes match `parentId`. No side effects.

#### C. `LegacyDataMapper` — Array Key Remapping
- **`mapReportRow(row)`:** `name/reachability_pct/country_code` → `{ label, metric, region, source }`.
- **`mapJobContext(context)`:** `job_name/phone_number/menu_depth` → `{ job_name, phone, depth }`. No side effects.

#### D. `RealTimeTestService` — Async Orchestration
- **`createSession()`:** Returns UUID string.
- **`runDiscoveryTest(jobId, sessionId)`:** 6-step loop (`usleep(600_000)`) → MongoDB events/transcripts/diagnostics → `DiscoveryNode` record → job update.
- **`runConnectTest(monitorId, sessionId)`:** 5-step loop (`usleep(500_000)`) → `ConnectCheckResult` → `ReachabilityService` calculation → monitor update → transcript + complete event.

#### E. `dev-api/src/server.js` — HTTP Route Layer
- 26 Express routes: health, dashboard KPIs, discovery CRUD + SSE, connect CRUD + SSE, bulk import.
- Input validation guards (422 on missing fields, 100-item batch cap).
- SSE helpers: `setupSse`, `sendSse`, `streamSession` (interval-based polling, `req.on('close')` cleanup).

#### F. `dev-api/src/realtime.js` — Async Test Simulation
- Same orchestration as PHP `RealTimeTestService` using `async/await` + `sleep()`. 9-step discovery, 8-step connect. Mutates in-memory `store` directly.

### 1.4 Edge Case Detection

| # | PHP / JS Quirk | Migration Risk | Handling Required |
|---|---|---|---|
| E-1 | PHP array spread `[...$step]` | LOW | TS object spread `{ ...step }` — identical |
| E-2 | `iterator_to_array($checks, false)` | LOW | `Array.from(checks)` |
| E-3 | `usleep()` synchronous | HIGH | `await sleep(ms)` — all callers must be `async` |
| E-4 | `dispatch()->afterResponse()` | MEDIUM | `res.on('finish', () => runTest().catch(console.error))` |
| E-5 | PHP `public const` on class | LOW | `static readonly` on TS class or `export const` |
| E-6 | PHP `round($n, 2)` half-up | LOW | `Math.round(n * 100) / 100` — matches |
| E-7 | `array_reduce` initial `0` (int) | LOW | TS `reduce` initial typed as `number` |
| E-8 | Eloquent `Collection::where('parent_id', null)` | MEDIUM | `nodes.filter(n => n.parent_id === parentId)` — strict `===` |
| E-9 | PHP `max()` returns `false` on empty | LOW | `arr.length > 0 ? Math.max(...arr) : 0` |
| E-10 | MongoDB ObjectId `.toString()` | LOW | Already in JS `serializeDoc`; preserve in TS |
| E-11 | PHP `Str::uuid()` returns `Stringable` | LOW | `crypto.randomUUID()` returns `string` |
| E-12 | `random_int(1,100) > 20` CSPRNG | LOW | `Math.random() > 0.2` (simulation only) |

---

## Phase 2: Environment & Target Mapping

### 2.1 Library Matching

| Source Dependency | Target Equivalent | Notes |
|---|---|---|
| `Illuminate\Support\Collection` | Native `Array` | `filter/map/reduce/find/flatMap` |
| `Illuminate\Support\Str::uuid()` | `crypto.randomUUID()` | Node 14.17+ built-in |
| `Illuminate\Support\Facades\Log` | `console.info/warn/error` | `pino` for production |
| `MongoDB\Driver` (PHP) | `mongodb` npm (already in `dev-api`) | `MongoClient` from `mongodb` package |
| Laravel `Cache::remember` | Custom `ttlCache<T>` utility | `Map<string, { value: T; expiresAt: number }>` |
| Laravel `Request->validate()` | `zod` schema `.parse()` | Idiomatic TS runtime validation |
| PHP `usleep(N_microseconds)` | `await sleep(N_ms)` | `const sleep = (ms: number) => new Promise<void>(r => setTimeout(r, ms))` |
| `random_int(1,100)` | `Math.floor(Math.random() * 100) + 1` | Simulation only |
| `round($n, 2)` | `Math.round(n * 100) / 100` | Identical rounding |

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
| `Collection<int, DiscoveryNode>` | `DiscoveryNode[]` | Already in `frontend/src/types/index.ts` |
| `ConnectCheckResult` (Eloquent) | `ConnectCheckResult` interface | Already in `types/index.ts` |
| `ConnectMonitor` (Eloquent) | `ConnectMonitor` interface | Already in `types/index.ts` |
| `DiscoveryJob` (Eloquent) | `DiscoveryJob` interface | Already in `types/index.ts` |
| `JsonResponse` | `void` (handler calls `res.json()`) | |
| `Request` | `express.Request` | |

**New types needed:**

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
| Class with `private readonly` injected deps | TS `class` with `constructor(private readonly …)` | Identical; TS constructor shorthand maps directly |
| `public const WINDOW = 20` on class | `static readonly WINDOW = 20` on TS class | Class constants for co-location with methods |
| Static utility class (`IvrTreeBuilder`) | Exported standalone function `buildIvrTree(nodes, parentId?)` | Functional style is idiomatic in TS; avoids empty class |
| Instance class (`ReachabilityService`) | `class ReachabilityService` with typed methods | Preserves testability via DI |
| `LegacyDataMapper` instance class | Exported functions `mapReportRow` / `mapJobContext` | No state; functions are idiomatic |
| `RealTimeTestService` class with injected services | `class RealTimeTestService` with constructor DI | Matches PHP; allows mocking in tests |
| Laravel `dispatch()->afterResponse()` | `res.on('finish', () => runTest().catch(console.error))` | Express-native deferred execution |
| PHP `foreach` over Eloquent Collection | `for...of` loop over typed array | Use `for...of` (not `forEach`) to enable `await` inside loop |

---

## Phase 3: Incremental Translation

### 3.1 Boilerplate Generation

#### Task 3.1.1 — Create `dev-api/src/types.ts`
- [ ] Create `dev-api/src/types.ts` as the single source of truth for shared dev-api types.
- [ ] Export the following interfaces (expanded from `frontend/src/types/index.ts` with dev-api-specific fields):

```typescript
// dev-api/src/types.ts
export interface CheckLike { reachable: boolean; }
export type MonitorStatus = 'active' | 'paused' | 'alert';
export type JobStatus = 'pending' | 'running' | 'completed' | 'failed';

export interface NodeLike {
  id: number;
  parent_id: number | null;
  prompt_text: string;
  dtmf_option: string | null;
  node_type: string;
  depth: number;
}

export interface DiscoveryNodeRecord extends NodeLike {
  discovery_job_id: number;
  children?: DiscoveryNodeRecord[];
}

export interface DiscoveryJobRecord {
  id: number;
  name: string;
  phone_number: string;
  country_code: string;
  status: JobStatus;
  menu_depth: number;
  nodes_discovered: number;
  languages: string[];
  started_at: string | null;
  completed_at: string | null;
}

export interface ConnectMonitorRecord {
  id: number;
  name: string;
  toll_free_number: string;
  country_code: string;
  carrier: string | null;
  status: MonitorStatus;
  reachability_pct: number;
  last_checked_at: string | null;
}

export interface ConnectCheckRecord {
  id: number;
  connect_monitor_id: number;
  reachable: boolean;
  latency_ms: number | null;
  carrier_route: string | null;
  failure_reason: string | null;
  checked_at: string;
}

export interface ReportRow  { label: string; metric: number; region: string; source: string; }
export interface JobContext { job_name: string | null; phone: string | null; depth: number; }
export interface CacheEntry<T> { value: T; expiresAt: number; }
```

#### Task 3.1.2 — Configure `dev-api/tsconfig.json`
- [ ] Create or update `dev-api/tsconfig.json`:

```json
{
  "compilerOptions": {
    "target": "ES2022",
    "module": "ESNext",
    "moduleResolution": "bundler",
    "lib": ["ES2022"],
    "strict": true,
    "noImplicitAny": true,
    "noUncheckedIndexedAccess": true,
    "outDir": "dist",
    "rootDir": "src",
    "declaration": true,
    "esModuleInterop": true,
    "skipLibCheck": true
  },
  "include": ["src/**/*"],
  "exclude": ["dist", "node_modules"]
}
```

#### Task 3.1.3 — Add TypeScript dev dependencies to `dev-api/package.json`
- [ ] Add the following `devDependencies`:
  - `typescript` (exact: `5.5.4`)
  - `@types/node` (exact: `20.14.10`)
  - `@types/express` (exact: `4.17.21`)
  - `@types/cors` (exact: `2.8.17`)
- [ ] Add `build` script: `"build": "tsc"`.
- [ ] Add `start:ts` script: `"start:ts": "node --experimental-specifier-resolution=node dist/server.js"`.
- [ ] Verify existing `"type": "module"` is present in `package.json` (required for ESM).

#### Task 3.1.4 — Create file skeletons for all target `.ts` files
- [ ] `dev-api/src/types.ts` — type definitions (Task 3.1.1)
- [ ] `dev-api/src/reachability.ts` — `ReachabilityService` class
- [ ] `dev-api/src/ivrTree.ts` — `buildIvrTree` function
- [ ] `dev-api/src/legacyMapper.ts` — `mapReportRow`, `mapJobContext` functions
- [ ] `dev-api/src/realtime.ts` — `RealTimeTestService` class (typed version of `realtime.js`)
- [ ] `dev-api/src/store.ts` — typed in-memory store (replaces `store.js`)
- [ ] `dev-api/src/server.ts` — typed Express app (replaces `server.js`)

Each skeleton must start with:
```typescript
// THIS FILE IS AUTO-GENERATED — do not edit manually.
// Migrated from: <source file>
// Migration run: 20260702T170518_dj0mn1
```

### 3.2 Signature Translation

#### Task 3.2.1 — `ReachabilityService` signatures

| PHP Signature | TypeScript Signature |
|---|---|
| `public const ALERT_THRESHOLD = 90.0` | `static readonly ALERT_THRESHOLD: number = 90.0` |
| `public const WINDOW = 20` | `static readonly WINDOW: number = 20` |
| `public function computeRate(iterable $checks): float` | `computeRate(checks: CheckLike[] \| Iterable<CheckLike>): number` |
| `public function statusFromRate(float $rate): string` | `statusFromRate(rate: number): MonitorStatus` |

**Full typed signature block:**
```typescript
import type { CheckLike, MonitorStatus } from './types.js';

export class ReachabilityService {
  static readonly ALERT_THRESHOLD: number = 90.0;
  static readonly WINDOW: number = 20;

  computeRate(checks: CheckLike[] | Iterable<CheckLike>): number { /* TODO */ }
  statusFromRate(rate: number): MonitorStatus { /* TODO */ }
}
```

#### Task 3.2.2 — `buildIvrTree` function signature

| PHP Signature | TypeScript Signature |
|---|---|
| `public static function build(Collection $nodes, ?int $parentId = null): array` | `export function buildIvrTree(nodes: NodeLike[], parentId?: number \| null): DiscoveryNodeRecord[]` |

**Full typed signature block:**
```typescript
import type { NodeLike, DiscoveryNodeRecord } from './types.js';

export function buildIvrTree(
  nodes: NodeLike[],
  parentId: number | null = null
): DiscoveryNodeRecord[] { /* TODO */ }
```

#### Task 3.2.3 — `LegacyDataMapper` function signatures

| PHP Signature | TypeScript Signature |
|---|---|
| `public function mapReportRow(array $row): array` | `export function mapReportRow(row: Record<string, unknown>): ReportRow` |
| `public function mapJobContext(array $context): array` | `export function mapJobContext(context: Record<string, unknown>): JobContext` |

**Full typed signature block:**
```typescript
import type { ReportRow, JobContext } from './types.js';

export function mapReportRow(row: Record<string, unknown>): ReportRow { /* TODO */ }
export function mapJobContext(context: Record<string, unknown>): JobContext { /* TODO */ }
```

#### Task 3.2.4 — `RealTimeTestService` signatures

| PHP Signature | TypeScript Signature |
|---|---|
| `public function __construct(private readonly MongoService $mongo, private readonly ReachabilityService $reachability)` | `constructor(private readonly mongo: MongoFacade, private readonly reachability: ReachabilityService)` |
| `public function createSession(): string` | `createSession(): string` |
| `public function runDiscoveryTest(int $jobId, string $sessionId): void` | `async runDiscoveryTest(jobId: number, sessionId: string): Promise<void>` |
| `public function runConnectTest(int $monitorId, string $sessionId): void` | `async runConnectTest(monitorId: number, sessionId: string): Promise<void>` |

**Key change:** PHP methods are synchronous (blocking `usleep`). TypeScript methods must be `async` and use `await sleep(ms)`.

**MongoFacade interface (inferred from `mongo.js` usage):**
```typescript
export interface MongoFacade {
  storeTestEvent(sessionId: string, module: string, referenceId: number, event: Record<string, unknown>): Promise<void>;
  storeTranscript(module: string, referenceId: number, payload: Record<string, unknown>): Promise<void>;
  storeDiagnostic(module: string, referenceId: number, payload: Record<string, unknown>): Promise<void>;
  getTestEvents(sessionId: string): Promise<Record<string, unknown>[]>;
}
```

#### Task 3.2.5 — `store.ts` typed interface

Wrap the existing `store.js` in-memory object with explicit types:

```typescript
import type {
  DiscoveryJobRecord, DiscoveryNodeRecord,
  ConnectMonitorRecord, ConnectCheckRecord
} from './types.js';

export interface Store {
  discoveryJobs:   DiscoveryJobRecord[];
  discoveryNodes:  Omit<DiscoveryNodeRecord, 'children'>[];
  connectMonitors: ConnectMonitorRecord[];
  connectChecks:   ConnectCheckRecord[];
  nextJobId:       number;
  nextNodeId:      number;
  nextMonitorId:   number;
  nextCheckId:     number;
}

export const store: Store = { /* seed data */ };
export function buildTree(nodes: Omit<DiscoveryNodeRecord, 'children'>[], parentId?: number | null): DiscoveryNodeRecord[];
```

#### Task 3.2.6 — Express route handler signatures in `server.ts`

All route handlers must be typed. Example pattern:

```typescript
import { Request, Response, NextFunction } from 'express';

// GET /api/connect/monitors
app.get('/api/connect/monitors', (_req: Request, res: Response): void => {
  res.json({ data: store.connectMonitors });
});

// POST /api/connect/monitors
app.post('/api/connect/monitors', (req: Request, res: Response): void => {
  // zod validation or manual guard
});
```

- [ ] All `async` handlers must be wrapped in a `try/catch` and call `next(err)` on failure, or use an async wrapper utility.
- [ ] Define a `asyncHandler` wrapper: `const asyncHandler = (fn: (req: Request, res: Response) => Promise<void>) => (req: Request, res: Response, next: NextFunction) => fn(req, res).catch(next)`.

---
agent: progress-code-analyser-agent
cli: Kiro CLI
llm: auto
run_id: 20260702T170518_dj0mn1
generated_at: 2026-07-02T11:35:18.505Z
---

# Code Analysis Report — `dev-api/src/` (server.js · realtime.js · mongo.js · store.js · seed-data.js)

> **Run ID:** 20260702T170518_dj0mn1  
> **Repository:** shende-shweta/FSDKC @ main (481814fa)  
> **Scope:** All five source files that form the Node.js `dev-api` backend  
> **Analyst:** progress-code-analyser-agent  

---

## 1. Architectural Context

### Language & File Type
| File | Language | Classification |
|---|---|---|
| `server.js` | JavaScript ESM (Node 18+) | HTTP server / router / controller |
| `realtime.js` | JavaScript ESM | Domain-logic / simulation service |
| `mongo.js` | JavaScript ESM | Data-access layer (MongoDB driver) |
| `store.js` | JavaScript ESM | In-memory relational store + tree utility |
| `seed-data.js` | JavaScript ESM | Static test-data fixture factory |

The overall module is a **single-process Express REST API** that acts as a development/simulation stand-in for a production backend. It combines:
- An IVR-tree **discovery simulation** (fake DTMF/speech traversal steps)
- A toll-free-number **connect/reachability simulation**
- A **dual-store pattern**: MongoDB (for persisted transcripts, test events, diagnostics) and an in-memory JS object (`store`) for transient relational state (jobs, monitors, checks).

### Logic Separation — Findings
- **server.js** acts as both router **and** inline controller. Business logic (KPI calculations, bulk-import transformation) lives directly in route handlers — no service layer exists on the Node side.
- **realtime.js** is a clean domain/service module but directly mutates `store` (a shared mutable singleton), blurring service/state boundaries.
- **mongo.js** is a well-formed data-access layer but doubles as a connection lifecycle manager (module-level `let db` singleton).
- **store.js** is the only in-memory state container; it is exported as a mutable reference shared across all modules — effectively a global state bag.
- No middleware layer; auth, validation, and rate-limiting are entirely absent.

### Dependencies
```
Express 4.x        — HTTP framework
cors               — CORS middleware
mongodb (driver)   — Native MongoDB client
mongodb-memory-server — In-memory Mongo fallback (dev only)
Node built-in: crypto (randomUUID)
```
No Zod/Joi, no Pino/Winston, no auth middleware, no rate-limiter.

---

## 2. Data & Memory Structures

### Key Entities / Collections
| Store | Entity | Location |
|---|---|---|
| In-memory (`store`) | `discoveryJobs[]` | store.js |
| In-memory (`store`) | `discoveryNodes[]` | store.js |
| In-memory (`store`) | `connectMonitors[]` | store.js |
| In-memory (`store`) | `connectChecks[]` | store.js |
| MongoDB | `transcripts` collection | mongo.js |
| MongoDB | `test_events` collection | mongo.js |
| MongoDB | `call_diagnostics` collection | mongo.js |

### Structural Details
- `store` is a plain exported JS object — no class, no getter/setter, no immutability. All consumers mutate it directly with `job.status = 'running'`, `store.discoveryNodes.push(node)`, etc.
- Auto-increment IDs (`nextJobId`, `nextNodeId`, etc.) are plain mutable integers on the store — **not thread-safe** in any worker/cluster scenario.
- KPI computation in `server.js:/api/dashboard/kpis` uses `store.connectMonitors.reduce(...)` with no guard for a zero-length monitors array → **division by zero** yields `NaN`.
- `buildTree` in `store.js` is a recursive function with no cycle/depth guard. A malformed parent-id reference would cause infinite recursion and a stack overflow.
- MongoDB document `_id` serialization is handled by `serializeDoc` in `server.js` — correctly converts ObjectId and Date, but does NOT handle nested documents or arrays of documents (flat-copy only).

### State & Scope
- `client`, `db`, `memoryServer`, `usingMemory`, `dbName` in `mongo.js` are module-level `let` singletons. All exports close over these. This is an acceptable pattern for a single-process server, but fragile if the module is ever tested in isolation (no reset/teardown exported for test use).
- `store` is process-global and resets on every server restart — **all job/monitor state is ephemeral**.

### Caching / Reuse
- No caching at any layer. Every `/api/dashboard/kpis` call hits MongoDB (`healthCheck()`) performing a `ping` command plus three `countDocuments()` queries — 4 DB round-trips per KPI page load.
- `getTestEvents` fetches **all** events for a session on every SSE poll interval (every 500 ms). As session event counts grow, this becomes an O(n) scan per poll tick.

---

## 3. Procedural Logic & Parameters

### Function / Route Map

#### `server.js`
| Handler | Method & Path | Responsibility |
|---|---|---|
| Health | `GET /api/health` | Ping MongoDB, return version info |
| MongoDB status | `GET /api/mongodb/status` | Alias of healthCheck |
| MongoDB transcripts | `GET /api/mongodb/transcripts` | Query transcripts by module+reference_id |
| MongoDB diagnostics | `GET /api/mongodb/diagnostics/:module/:referenceId` | Inline query (no service fn) |
| Dashboard KPIs | `GET /api/dashboard/kpis` | Aggregate store + MongoDB health |
| Discovery jobs list | `GET /api/discovery/jobs` | Return all jobs from store |
| Discovery job create | `POST /api/discovery/jobs` | Create job in store |
| Discovery job detail | `GET /api/discovery/jobs/:id` | Job + nodes + MongoDB transcripts |
| Discovery tree | `GET /api/discovery/jobs/:id/tree` | Tree-built nodes for job |
| Discovery start | `POST /api/discovery/jobs/:id/start` | Launch async simulation |
| Discovery stream | `GET /api/discovery/jobs/:id/stream` | SSE stream for session |
| Connect bulk import | `POST /api/connect/monitors/bulk-import` | Bulk ingest monitors (no validation) |
| Connect monitors list | `GET /api/connect/monitors` | Return all monitors |
| Connect monitor create | `POST /api/connect/monitors` | Create monitor |
| Connect monitor detail | `GET /api/connect/monitors/:id` | Monitor + last 10 checks |
| Connect checks | `GET /api/connect/monitors/:id/checks` | All checks for monitor |
| Connect run-check | `POST /api/connect/monitors/:id/run-check` | Launch async reachability sim |
| Connect stream | `GET /api/connect/monitors/:id/stream` | SSE stream for session |

#### `realtime.js`
| Function | Responsibility |
|---|---|
| `runDiscoveryTest(jobId, sessionId)` | Async sim: iterates DISCOVERY_STEPS, writes store + MongoDB, completes job |
| `runConnectTest(monitorId, sessionId)` | Async sim: iterates CONNECT_STEPS, writes store + MongoDB, updates monitor |
| `createSession()` | Returns `randomUUID()` — session namespace for SSE streams |

#### `mongo.js`
| Function | Responsibility |
|---|---|
| `connectMongo()` | Connect to Atlas or start in-memory; create indexes |
| `getDb()` | Return db handle or throw |
| `getDbInfo()` | Return `{name, mode}` |
| `isUsingMemory()` | Boolean flag |
| `healthCheck()` | Ping + countDocuments ×3 |
| `storeTranscript(module, referenceId, payload)` | Insert into `transcripts` |
| `storeTestEvent(sessionId, module, referenceId, event)` | Insert into `test_events` |
| `storeDiagnostic(module, referenceId, data)` | Insert into `call_diagnostics` |
| `getTranscripts(module, referenceId)` | Find by module+refId, sort desc, limit 50 |
| `getTestEvents(sessionId)` | Find ALL events for session, sort asc |
| `seedMongoData(force)` | Idempotent seed; tags docs with `app: 'klearcom'` |
| `closeMongo()` | Close client + memory server |

#### `store.js`
| Export | Responsibility |
|---|---|
| `store` | Mutable in-memory state object with seed data |
| `buildTree(nodes, parentId)` | Recursive tree builder from flat node list |

### Complexity Flags
- `runDiscoveryTest` and `runConnectTest` are long (~80 lines each) but procedurally linear — acceptable complexity for simulation code.
- `server.js` has 18 route handlers across ~260 lines — the file is already at the edge of manageable size. Two more feature areas would justify a router-per-module split.
- `streamSession` is a locally-defined async helper inside `server.js` with a closure over `setInterval` and `req.on('close')` — correct lifecycle management but not unit-testable as written.
- `buildTree` has no visited-set guard: a cycle in `parent_id` values (possible via bulk-import) leads to infinite recursion.

---

## 4. Performance & Stability Audit

### Performance Risks

| ID | Risk | Location | Severity |
|---|---|---|---|
| P1 | **`getTestEvents` full-collection scan per SSE poll tick (every 500 ms)** — fetches ALL events for session on each interval; as events accumulate, this is O(n) per poll | `server.js:streamSession` + `mongo.js:getTestEvents` | High |
| P2 | **KPI endpoint calls `healthCheck()` on every request** — 4 DB round-trips (ping + 3×countDocuments) per page refresh | `server.js:/api/dashboard/kpis` | High |
| P3 | **No pagination on `GET /api/discovery/jobs`** — entire `discoveryJobs` array returned; unbounded as jobs accumulate | `server.js` | Medium |
| P4 | **No pagination on `GET /api/connect/monitors`** — same issue for monitors | `server.js` | Medium |
| P5 | **`buildTree` is O(n²)** — for each node it re-filters the entire array for children | `store.js:buildTree` | Medium |
| P6 | **`/api/mongodb/diagnostics` uses dynamic `import('./mongo.js')` inside route handler** — unnecessary repeated ESM module load (no-op after first call but semantically wrong) | `server.js` | Low |

### Resource Management

| ID | Risk | Location | Severity |
|---|---|---|---|
| R1 | **No `SIGTERM`/`SIGINT` handler** — `closeMongo()` is exported but never called on server shutdown; in-memory server process and Atlas connection may not be cleanly closed | `server.js` | High |
| R2 | **SSE interval not cleared on server error** — if `res.write` throws after connection drop, `clearInterval` may not execute correctly because the catch block only runs if the `getTestEvents` promise rejects, not on synchronous write failure | `server.js:streamSession` | Medium |
| R3 | **Unbounded `store.connectChecks` array** — every `runConnectTest` call pushes to `connectChecks`. Over time this array grows without bound; only the last 20 are used for percentage calculation | `realtime.js` + `store.js` | Medium |
| R4 | **`MongoMemoryServer` process not stopped on unhandled rejection** | `mongo.js` | Low |

### Error Handling

| ID | Finding | Location | Severity |
|---|---|---|---|
| E1 | **`runDiscoveryTest` / `runConnectTest` fired with `.catch(console.error)` only** — any exception inside the simulation (e.g., MongoDB write failure) silently logs to console; the job/monitor is left in `'running'` state forever | `server.js` | High |
| E2 | **`healthCheck` catches all errors and returns `{connected: false}` but callers never check this** — `/api/dashboard/kpis` proceeds to build a response even if MongoDB is down | `server.js` + `mongo.js` | Medium |
| E3 | **`/api/mongodb/diagnostics` has no try/catch** — any MongoDB error throws an unhandled promise rejection, crashing the route | `server.js` | High |
| E4 | **Division by zero in KPI calculation** — `store.connectMonitors.length === 0` → `avgReach = NaN`; serialized as JSON `null` | `server.js:/api/dashboard/kpis` | Medium |
| E5 | **`Number(req.params.id)` with non-numeric string returns `NaN`** — `store.discoveryJobs.find(j => j.id === NaN)` always returns `undefined`, triggering the 404 branch (benign but misleading) | Multiple routes | Low |
| E6 | **`storeDiagnostic` spreads `data` object directly into the doc** — if `data` contains a `_id` key or any MongoDB operator key, the insert will behave unexpectedly or throw | `mongo.js:storeDiagnostic` | Medium |

---

## 5. Integration & Connectivity

### Entry / Exit Points
- **Entry:** `server.js` is the ESM entry point. It is started via `node --experimental-vm-modules` (inferred from ESM top-level `await` at module scope for `connectMongo()` and `seedMongoData()`). Port is `process.env.PORT || 8080`.
- **Exit:** Routes return JSON. Two exit patterns exist for async jobs: immediate `res.json({ session_id })` then detached async test run, plus SSE stream endpoint for event delivery.

### API / External Calls
| Target | How | Notes |
|---|---|---|
| MongoDB Atlas | `mongodb` native driver | URI from `MONGODB_URI` env var |
| MongoDB in-memory | `mongodb-memory-server` | Auto-fallback when `MONGODB_URI` absent |
| No other outbound HTTP | — | System is entirely self-contained |

### SSE Streaming Design
- `streamSession` polls MongoDB every 500 ms for test events. This is a **polling-over-SSE** pattern — functional but inefficient vs. a push-based approach (e.g., EventEmitter or MongoDB change streams).
- The SSE stream endpoint is separate from the trigger endpoint. The client must:
  1. POST to `/start` or `/run-check` → receive `session_id`
  2. Immediately open SSE stream on corresponding `/stream?session_id=…`
  
  There is a **race condition**: if the SSE client connects after the test simulation has already completed (network delay), it will still receive all events because `getTestEvents` returns historical events — this is handled correctly by design.
- `req.on('close')` correctly clears the interval on client disconnect — good lifecycle management.
- **Missing `X-Accel-Buffering: no` header** — nginx reverse proxies buffer SSE by default; without this header, events will not stream in proxy-deployed environments.

### CORS Configuration
- `app.use(cors())` with no options applies **default permissive CORS** — allows all origins, all methods, standard headers. Acceptable for dev, problematic if this API were exposed externally.

---

## 6. Readability & Red-Flags

### Styling & Maintainability
- Code is consistently formatted, uses modern ESM `import/export`, and follows camelCase. Comments are sparse but section headers in `server.js` (e.g., `// ── Discovery ──`) aid navigation.
- `server.js` at 260 lines is not yet critical but will become difficult to maintain as endpoints are added. Route handlers should be split into separate router files (`discovery.router.js`, `connect.router.js`, `mongo.router.js`).
- `serializeDoc` is a private helper but defined near the bottom of the file — it is used in multiple routes earlier in the file (forward reference works in JS but reduces readability).
- The inline dynamic `import('./mongo.js')` on line 46 of `server.js` is a leftover code smell — the diagnostics query should use the already-imported `getDb` export.

### Security Red-Flags

| ID | Issue | Location | Priority |
|---|---|---|---|
| S1 | **No input validation anywhere** — all `req.body` fields are used directly (job name, phone_number, country_code, carrier, reachability_pct from bulk-import). Callers can inject arbitrary field values, corrupt store state, or send oversized payloads. | `server.js` — all POST handlers | **High** |
| S2 | **`/api/connect/monitors/bulk-import` accepts arbitrary body without rate limiting or size cap** — a client can push thousands of monitors in a single call, causing unbounded in-memory growth (DoS vector). The comment in the code itself calls this out: `// No validation, no rate limiting — accepts arbitrary body` | `server.js` | **High** |
| S3 | **`reachability_pct` accepted from user input without bounds check** — `item.reachability_pct ?? 100` accepts any numeric value including negative or >100 | `server.js:/bulk-import` | **Medium** |
| S4 | **Permissive CORS (`cors()` with no config)** — acceptable for localhost dev but a misconfiguration risk if deployed | `server.js` | **Medium** |
| S5 | **`storeDiagnostic` spreads arbitrary `data` into MongoDB document** — no key allowlist; prototype pollution / operator injection risk if `data` originates from untrusted input | `mongo.js` | **Medium** |
| S6 | **No authentication or authorization on any endpoint** — all routes are fully public | All routes | **High (scope: dev-only server)** |
| S7 | **`Number(req.params.referenceId)` — no NaN guard, no positive-integer validation** — `NaN` propagates to MongoDB queries silently | `server.js` | **Low** |

### Technical Notes / Code Smells

| ID | Smell | Priority |
|---|---|---|
| T1 | Hard-coded KPI values: `call_success_rate_pct: 94.2`, `transfer_success_rate_pct: 97.8` in dashboard handler | Medium |
| T2 | Reachability simulation uses `Math.random() > 0.2` — 80% success rate is hard-coded. No seed or config for deterministic tests | Medium |
| T3 | `store` is a plain mutable object with no access control — any module can corrupt state silently | Medium |
| T4 | `buildTree` in `store.js` has no protection against circular `parent_id` references | High |
| T5 | The inline dynamic import `await import('./mongo.js')` in the diagnostics route is redundant and confusing | Low |
| T6 | `seed-data.js` uses phone numbers and carrier names that match production-like data (`+18005551234`, `Verizon`, `Airtel`, `Vivo`) — should use clearly fictional values to avoid confusion | Low |
| T7 | `closeMongo` is exported but never called — no graceful shutdown handler | High |

---

## Summary for Agentic Memory

The `dev-api/src` module is a **Node.js ESM Express server** providing a simulation backend for the Klearcom IVR discovery and toll-free-number connect monitoring platform. It maintains a dual-store architecture: an in-process mutable JavaScript object (`store.js`) for ephemeral relational state (jobs, monitors, checks) and a MongoDB instance (Atlas or in-memory via `mongodb-memory-server`) for persisted transcripts, test events, and call diagnostics. Simulated test runs (`realtime.js`) iterate over fixed step arrays with artificial delays, writing events to MongoDB and mutating the store directly. Real-time event delivery to clients uses a **polling-over-SSE** pattern in which the server polls MongoDB every 500 ms per active stream session. The most critical risks are: zero input validation across all POST routes (including an explicitly unvalidated bulk-import endpoint), no graceful shutdown (MongoDB connection not closed on `SIGTERM`), async simulation errors that silently strand jobs in `'running'` status, and an O(n) MongoDB scan per SSE poll tick that will degrade performance as session event counts grow.

---

## Enhancement List

| # | Enhancement | Priority | Expected Outcome |
|---|---|---|---|
| EH-01 | Extract route handlers into separate Express routers: `discovery.router.js`, `connect.router.js`, `mongo.router.js` | High | Improved maintainability; `server.js` becomes a clean mount-point |
| EH-02 | Replace polling-over-SSE with an in-process EventEmitter publish/subscribe — emit events directly from `realtime.js` instead of polling MongoDB | High | Eliminates O(n) MongoDB scan per poll tick; sub-millisecond event delivery |
| EH-03 | Add request validation middleware (Zod or Joi) on all POST/PUT routes | High | Prevents corrupt store state; provides clear 400 error messages |
| EH-04 | Add `SIGTERM`/`SIGINT` handlers calling `closeMongo()` | High | Clean shutdown; prevents resource leaks in containerized deployments |
| EH-05 | Cap and prune `store.connectChecks` array (e.g., keep last 100 per monitor) | Medium | Prevents unbounded memory growth |
| EH-06 | Add depth/cycle guard to `buildTree` | High | Prevents stack overflow on circular parent_id data |
| EH-07 | Cache KPI MongoDB health aggregate with a short TTL (e.g., 10s) | Medium | Reduces DB round-trips from 4-per-request to ~1 per 10s |
| EH-08 | Add `X-Accel-Buffering: no` header to SSE responses | Medium | Fixes event buffering behind nginx/reverse proxies |
| EH-09 | Replace dynamic `import('./mongo.js')` in diagnostics route with direct import of `getDb` | Low | Removes confusing code smell; negligible runtime impact |
| EH-10 | Add structured logging (Pino) replacing `console.log/console.error` | Medium | Enables log aggregation and observability in production |
| EH-11 | Make simulation step arrays and randomness seeded/configurable via env vars | Low | Enables deterministic integration tests |
| EH-12 | Replace `buildTree` O(n²) filter with an O(n) index-based approach | Medium | Performance improvement for large node sets |

---

## Required Changes List

| # | Change | Impact | Urgency | Implementation Direction |
|---|---|---|---|---|
| RC-01 | **Add NaN guard to KPI `avgReach` calculation** | `/api/dashboard/kpis` returns `NaN` for `number_reachability_pct` when monitors array is empty — propagated as JSON `null` silently | **Immediate** | `const avgReach = store.connectMonitors.length > 0 ? store.connectMonitors.reduce(...) / store.connectMonitors.length : 0;` |
| RC-02 | **Wrap `/api/mongodb/diagnostics` route in try/catch** | Unhandled MongoDB error crashes the route with an unhandled promise rejection | **Immediate** | Add `try { ... } catch (err) { res.status(500).json({ error: err.message }); }` around the query |
| RC-03 | **Set job/monitor status to `'failed'` inside `.catch()` on async test runs** | Jobs triggered by `runDiscoveryTest`/`runConnectTest` remain in `'running'` state forever on any error | **High** | In server.js start/run-check handlers: `runDiscoveryTest(jobId, sessionId).catch(err => { job.status = 'failed'; console.error(err); });` |
| RC-04 | **Add `SIGTERM`/`SIGINT` graceful shutdown** | Without it, MongoDB Atlas connections and in-memory server processes are orphaned on container stop | **High** | `process.on('SIGTERM', async () => { await closeMongo(); process.exit(0); });` |
| RC-05 | **Add size cap to `/api/connect/monitors/bulk-import`** | Current implementation allows unlimited array intake — DoS vector against in-memory store | **High** | Validate `items.length <= 100` (or configurable limit); return 400 if exceeded |
| RC-06 | **Add cycle/depth guard to `buildTree`** | Circular `parent_id` reference (possible via bulk-import) causes infinite recursion and stack overflow | **High** | Track a `visited = new Set()` or enforce `depth < MAX_DEPTH` (e.g., 20) |
| RC-07 | **Fix `storeDiagnostic` — allowlist keys before spreading into doc** | Arbitrary `data` spread into MongoDB document can include `_id`, `$set`, or prototype-pollution keys | **Medium** | Destructure only known keys: `const { mos_score, latency_ms, packet_loss_pct, jitter_ms, session_id } = data;` and build doc explicitly |
| RC-08 | **Remove inline dynamic `import('./mongo.js')` in diagnostics route** | Semantically incorrect (module already imported at top); misleading to future maintainers | **Low** | Replace `const { getDb } = await import('./mongo.js');` with the top-level-imported `getDb` function |

---

## Confidence & Unknowns

### Certain
- All findings above are based on direct source code inspection of all 5 files.
- The NaN division-by-zero (RC-01), missing try/catch (RC-02), no shutdown handler (RC-04), and infinite recursion risk (RC-06) are confirmed bugs.
- The bulk-import endpoint explicitly notes the absence of validation in an inline comment.
- SSE polling pattern and O(n) scan per tick are confirmed by the `streamSession` + `getTestEvents` implementation.

### Inferred
- This is a **dev/simulation server only** — the security findings (no auth, permissive CORS) are likely acceptable in the current intended context, but must be addressed before any internet-facing deployment.
- The `store` pattern is intentionally ephemeral (resets on restart) as a dev convenience.
- The `mongodb-memory-server` dependency suggests the intent is to run the full stack without an external Atlas connection during local development and CI.

### Needs More Context
- **Is there a production counterpart to this Node API, or is the Laravel backend (`backend/`) the production API?** If this `dev-api` is only used in local/CI, several security findings are lower priority.
- **Is there a test suite for `dev-api`?** No test files were found under `dev-api/`. Without tests, the simulation step arrays and store mutations are entirely unverified.
- **What is the expected maximum number of concurrent SSE sessions?** This affects severity of the O(n) polling issue (P1).
- **Are there plans to persist `store` across restarts?** The dual-store pattern creates a sync gap: MongoDB has persisted events but the in-memory store (jobs, monitors) resets. This could cause orphaned MongoDB records.

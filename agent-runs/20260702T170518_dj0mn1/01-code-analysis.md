---
agent: progress-code-analyser-agent
cli: Kiro CLI
llm: auto
run_id: 20260702T170518_dj0mn1
generated_at: 2026-07-02T14:35:12.306Z
---

# Codebase Improvement Analysis
**Run ID:** 20260702T170518_dj0mn1  
**Scope:** Full codebase — backend (Laravel), dev-api (Node.js/Express), frontend (React/TypeScript)

---

## 1. Architectural Context

| Layer | Technology | Classification |
|---|---|---|
| Backend | PHP 8.x / Laravel 10 | REST API, Service layer, Eloquent ORM |
| Dev-API | Node.js / Express (ESM) | In-memory dev server / mock API |
| Frontend | React 18 / TypeScript / Vite | SPA with React Query + Zustand |
| Database | MySQL (Eloquent) + MongoDB (raw driver) | Dual-store |

**Logic separation:** Mostly clean. Controllers are thin, Services hold business logic. One exception: `LegacyReportController` was a fat controller mixing DB queries, math, and mapping.

---

## 2. Findings by File

### B1 — `LegacyDataMapper.php` ✓ Fixed
**Severity: HIGH (Security)**  
`extract($row)` and `extract($context)` without `EXTR_SKIP` on the second call allow an attacker-controlled array to overwrite local variables including `$this`. Replaced with direct array access.

```php
// BEFORE (dangerous)
extract($context);  // no EXTR_SKIP — overwrites locals

// AFTER (safe)
'job_name' => $context['job_name'] ?? null,
```

### B2 — Reachability calculation triplicated ✓ Fixed
**Severity: HIGH (Correctness / Maintainability)**  
The same 8-line block (fetch last 20 checks → compute % → derive status) appeared identically in:
- `ConnectController::checks()`
- `LegacyReportController::carrierSummary()`
- `RealTimeTestService::runConnectTest()`

Extracted to `ReachabilityService` (single source of truth). `ALERT_THRESHOLD` and `WINDOW` are now named constants.

### B3 — `buildTree()` duplicated ✓ Fixed
**Severity: MEDIUM (Maintainability)**  
Identical recursive method existed in `DiscoveryController` and `LegacyReportController`. Extracted to `App\Support\IvrTreeBuilder::build()` (static utility class). Both callers now delegate to it.

### B4 — `LegacyReportController` used `$request->all()` + `extract()` ✓ Fixed
**Severity: HIGH (Security)**  
Raw `extract($filters)` on request data is a variable injection vector. Replaced with explicit `$request->string('country_code')` / `$request->string('carrier')` calls.

### B5 — `DashboardController::kpis()` — 7 uncached DB queries per request ✓ Fixed
**Severity: MEDIUM (Performance)**  
Every dashboard load fired 7 separate `COUNT`/`AVG` queries with no caching. Added `Cache::remember('dashboard.kpis', 30)` wrapping all queries. TTL is 30 seconds — suitable for a KPI tile that does not need sub-second freshness.  
Also flagged: `call_success_rate_pct: 94.2` and `transfer_success_rate_pct: 97.8` are hard-coded magic numbers. Added a TODO comment to replace with real computations.

### B6 — `StreamController::streamSession()` — repeated inline SSE write ✓ Refactored
**Severity: LOW (Readability)**  
The `echo ... ob_flush(); flush()` triplet was duplicated twice inside the method. Extracted to a private `sendSseEvent()` helper. Also added `MAX_POLL_CYCLES` constant.

### B7 — `RealTimeTestService` — no logging, used copy-pasted reachability block ✓ Fixed
**Severity: MEDIUM**  
Added `Log::info()` calls at test completion (discovery & connect). Now uses `ReachabilityService` instead of the inline calculation.

### N1 — `dev-api/src/server.js` — bulk-import has no validation or size cap ✓ Fixed
**Severity: HIGH (Security/Stability)**  
`POST /api/connect/monitors/bulk-import` accepted an arbitrary array with no field validation and no batch size limit — a single request could insert thousands of records and exhaust in-memory store.  
Added:
- Empty-array guard (422)
- `toll_free_number` presence check per item
- Hard cap of 100 items per batch

### N2 — `POST /api/discovery/jobs` and `POST /api/connect/monitors` — no validation ✓ Fixed
**Severity: MEDIUM (Correctness)**  
Dev-API accepted any body, silently creating records with `undefined` fields. Added explicit required-field checks returning 422 with a descriptive message.

### N3 — `avgReach` divide-by-zero when no monitors exist ✓ Fixed
**Severity: LOW (Correctness)**  
```js
// BEFORE: NaN when connectMonitors is empty
const avgReach = store.connectMonitors.reduce(...) / store.connectMonitors.length;

// AFTER: guard
const avgReach = totalMonitors > 0 ? ... / totalMonitors : 0;
```

### N4 — SSE error handler silently swallowed all errors – Fixed
**Severity: LOW (Observability)**  
`catch { clearInterval(poll); res.end(); }` had an empty catch. Added `console.error('SSE stream error:', err)`.

### F1 — `useRealtimeTest` — SSE `onerror` had no user-visible feedback ✓ Fixed
**Severity: MEDIUM (UX)**  
When the EventSource connection dropped, `isRunning` was set to false silently. Added an `error` state returned from the hook; both `DiscoveryPage` and `ConnectPage` now render it as a `role="alert"` paragraph.

### F2 — `api/client.ts` — network errors were swallowed – Fixed
**Severity: MEDIUM (Observability)**  
`fetch()` can reject (DNS failure, CORS, timeout) with no outer try/catch — the error would bubble unhandled. Added a try/catch around `fetch()` that rethrows as `new Error('Network error: …')`. Also added `patch` and `delete` helpers for completeness.

### F3 — Unhandled promise warnings in event handlers – Fixed
**Severity: LOW (Correctness)**  
`onClick={() => handleStart(job.id)}` calls an `async` function without awaiting or voiding it, triggering a floating Promise. Changed to `onClick={() => void handleStart(job.id)}` pattern throughout.

### F4 — Form `<label>` elements not linked to inputs – Fixed
**Severity: MEDIUM (Accessibility)**  
All `<label>` tags in `DiscoveryPage` and `ConnectPage` lacked `htmlFor` attributes, meaning screen readers could not associate the label with its field. Added `id`/`htmlFor` pairs to every input.

### F5 — Query error states not rendered – Fixed
**Severity: LOW (UX)**  
`jobsQuery.isError` and `monitorsQuery.isError` were not handled — the table would silently show nothing. Added explicit error fallback divs.

### F6 — `handleRunCheck`/`handleStart` invalidated queries even on error – Fixed
**Severity: LOW (Correctness)**  
Wrapped cache invalidation in `finally` blocks so it runs regardless of success or failure.

---

## 3. Files Changed

| File | Change |
|---|---|
| `backend/app/Services/ReachabilityService.php` | **NEW** — centralised reachability calculation |
| `backend/app/Support/IvrTreeBuilder.php` | **NEW** — centralised IVR tree builder |
| `backend/app/Legacy/LegacyDataMapper.php` | Removed `extract()` anti-pattern |
| `backend/app/Http/Controllers/Api/ConnectController.php` | Uses `ReachabilityService` |
| `backend/app/Http/Controllers/Api/DiscoveryController.php` | Uses `IvrTreeBuilder`, added `languages.*` validation |
| `backend/app/Http/Controllers/Api/LegacyReportController.php` | Removed `extract()`, uses `ReachabilityService` + `IvrTreeBuilder` |
| `backend/app/Http/Controllers/Api/DashboardController.php` | Added 30s KPI cache, TODO on magic numbers |
| `backend/app/Http/Controllers/Api/StreamController.php` | Extracted `sendSseEvent()`, added `MAX_POLL_CYCLES` constant |
| `backend/app/Services/RealTimeTestService.php` | Uses `ReachabilityService`, added `Log::info()` |
| `dev-api/src/server.js` | Added input validation, batch cap, divide-by-zero fix, SSE error logging |
| `frontend/src/api/client.ts` | Added network error handling, `patch`/`delete` helpers |
| `frontend/src/hooks/useRealtimeTest.ts` | Added `error` state, SSE JSON parse guard |
| `frontend/src/pages/DiscoveryPage.tsx` | Error UI, `htmlFor` labels, floating Promise fix |
| `frontend/src/pages/ConnectPage.tsx` | Error UI, `htmlFor` labels, floating Promise fix |

---

## 4. Not Changed (Out of Scope for this PR)

| Issue | Rationale |
|---|---|
| No authentication on any endpoint | Requires new feature: User model, Sanctum, LoginPage — separate PR |
| `call_success_rate_pct` / `transfer_success_rate_pct` hard-coded | Requires a real call-result data model — TODO comment added |
| `StreamController` SSE polling vs true push | Requires Redis pub/sub or Laravel Reverb — infrastructure change |
| `ConnectMonitor` missing DB index on `status` | Requires a migration — out of scope for this PR |

---

## Summary for Agentic Memory

The codebase is a three-tier voice observability platform (Laravel backend, Node.js dev-API, React frontend) with no authentication layer. The most critical code-quality issues were: (1) `extract()` on request-controlled arrays in `LegacyDataMapper` and `LegacyReportController` — a variable injection vulnerability; (2) a reachability calculation block copied identically into three files with no shared abstraction; (3) no input validation on three dev-API POST endpoints including a bulk-import route that had no size cap. All critical and medium issues were fixed in this PR. The major additions are `ReachabilityService` (single-source reachability math) and `IvrTreeBuilder` (extracted from two duplicate private methods). Frontend improvements address SSE error surfacing, network error handling, accessibility labels, and floating Promise warnings.

---

## Enhancement List (remaining backlog)

| Priority | Item |
|---|---|
| High | Add authentication (Sanctum + LoginPage) |
| High | Replace polling SSE with Redis pub/sub or Reverb |
| Medium | Add DB index on `connect_monitors.status` and `discovery_jobs.status` |
| Medium | Replace hard-coded KPI percentages with real computed values |
| Medium | Add pagination to `GET /discovery/jobs` and `GET /connect/monitors` |
| Low | Convert dev-api server.js to TypeScript |
| Low | Add OpenAPI/Swagger documentation |
| Low | Increase test coverage (ReachabilityService, IvrTreeBuilder) |

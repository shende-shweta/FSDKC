I'll analyze the FSDKC repository architecture via GitHub OAuth (no local clone), starting with stack detection and a full hotspot scan.Exploring the repository structure and stack via GitHub API.Stack looks like Laravel + React + Node. Digging into backend and frontend layouts next.Code search isn't indexed for this repo, so I'll walk the tree via contents API instead.Reading controllers, services, and frontend sources for hotspot evidence.I have enough evidence to draft the report. Computing exact metrics, then writing and publishing it.Writing the full architecture report, then publishing it to GitHub.Publishing the report to GitHub on a feature branch and opening a PR.Report saved to `docs/discovery/01-architecture-design.md` and opened as PR: https://github.com/shende-shweta/FSDKC/pull/14

<div class="overall-rating overall-rating--high-risk"><div class="overall-rating-label">Overall Codebase Rating — Architecture &amp; Design</div><div class="overall-rating-value">High Risk</div><div class="overall-rating-note">Driven by High-Risk Missing Service Layer (H2), Missing Repository Pattern (H3), Direct SQL/ORM in Controllers (H6), Domain Boundary Violations (H8), Shared Database Coupling (H9), and Dual-Runtime Drift (H10).</div></div>

> **Executive Summary**
>
> Klearcom is a modular monolith for Discovery (IVR) and Connect (TFN) with three runtime layers: Laravel 12 API (`backend/`), React 19 SPA (`frontend/`), and a parallel Node Express `dev-api` used for local development. Layers covered: **backend** (~20 PHP application files under `backend/app/` plus routes), **frontend** (~14 TS/JSX source files under `frontend/src/`), and **dev-api** (6 JS modules). The dominant risk is **missing application/repository boundaries**: controllers and Express handlers own Eloquent/in-memory store access (25 Eloquent call sites in controllers alone), while declared `app/Modules/{Discovery,Connect}` folders contain only `AGENTS.md` stubs. Cross-domain coupling through `DashboardController`, `LegacyReportController`, `RealTimeTestService`, and shared Mongo collections amplifies change cost. Frontend is comparatively healthier (shared `api/client.ts`, React Query) but still carries legacy class/unbounded widgets without error boundaries.

## 1.1 Benchmark Ratings Summary

| # | Hotspot | Primary KPI | <span class="rating rating-good">Good</span> | <span class="rating rating-moderate">Moderate</span> | <span class="rating rating-high-risk">High Risk</span> | Measured | Rating |
|---|---|---|---|---|---|---|---|
| H1 | Fat Controllers | Avg LOC per controller | <150 | 150–300 | >300 | 62 LOC | <span class="rating rating-good">Good</span> |
| H2 | Missing Service Layer | Controllers accessing repos/models | <10 | 10–20 | >20 | 25 Eloquent sites in 4 controllers | <span class="rating rating-high-risk">High Risk</span> |
| H3 | Missing Repository Pattern | Direct DB access points | <10 | 10–20 | >20 | 32+ (0 repositories) | <span class="rating rating-high-risk">High Risk</span> |
| H4 | Circular Dependencies | Dependency cycles | 0 | 1–3 | >3 | 0 | <span class="rating rating-good">Good</span> |
| H5 | Shared Utility Abuse | Utility files w/ business logic | 0 | 1–5 | >5 | 2 (`LegacyDataMapper`, `store.buildTree`) | <span class="rating rating-moderate">Moderate</span> |
| H6 | Direct SQL in Controllers | ORM compliance % | >90% | 60–90% | <60% | ~22% (7/32 Eloquent sites in services) | <span class="rating rating-high-risk">High Risk</span> |
| H7 | God Classes | Classes >1000 LOC | 0 | 1–3 | >3 | 0 (largest: `server.js` 224 LOC) | <span class="rating rating-good">Good</span> |
| H8 | Domain Boundary Violations | Cross-domain access points | 0 | 1–5 | >5 | 8+ | <span class="rating rating-high-risk">High Risk</span> |
| H9 | Shared Database Coupling | Tables shared across domains | <10% | 10–30% | >30% | ~38% (3/8 stores shared via Mongo) | <span class="rating rating-high-risk">High Risk</span> |
| H10 | Dual-Runtime Drift (additional) | Parallel domain impls Laravel vs Node | 0 | 1–2 | >2 | 4+ duplicated flows | <span class="rating rating-high-risk">High Risk</span> |
| F1 | Business Logic in Components | Avg LOC per component | <150 | 150–300 | >300 | 74 LOC | <span class="rating rating-good">Good</span> |
| F2 | Missing Frontend Service/Data Layer | Components w/ inline API calls | <10 | 10–20 | >20 | 6 components + 1 hook | <span class="rating rating-good">Good</span> |
| F3 | God / Oversized Components | Components >400 LOC | 0 | 1–3 | >3 | 0 (max `ConnectPage` 208 LOC) | <span class="rating rating-good">Good</span> |
| F4 | Prop Drilling / Global State Abuse | Max prop-drilling depth | ≤2 | 3–4 | >4 | ≤2 (`uiStore` focused) | <span class="rating rating-good">Good</span> |
| F5 | Legacy / Inconsistent Component Patterns | Legacy-pattern components | 0 | 1–10 | >10 | 2 | <span class="rating rating-moderate">Moderate</span> |

## 1.4 Actions Required

| Hotspot | Action | Rating | Priority |
|---|---|---|---|
| H2 Missing Service Layer | Extract Discovery/Connect/Dashboard application services; centralize reachability + tree builders; thin controllers to HTTP adapters | <span class="rating rating-high-risk">High Risk</span> | <span class="sev sev-critical">Critical</span> |
| H3 Missing Repository Pattern | Introduce repository interfaces for Eloquent models and Mongo collections; bind in `AppServiceProvider`; zero direct ORM in controllers | <span class="rating rating-high-risk">High Risk</span> | <span class="sev sev-critical">Critical</span> |
| H5 Shared Utility Abuse | Replace `LegacyDataMapper` extract mapping and duplicate `buildTree` with domain services (`IvrTreeBuilder`, explicit DTOs) | <span class="rating rating-moderate">Moderate</span> | <span class="sev sev-medium">Medium</span> |
| H6 Direct SQL/ORM in Controllers | Move all Eloquent/Mongo queries out of controllers (incl. Node diagnostics route) behind repositories | <span class="rating rating-high-risk">High Risk</span> | <span class="sev sev-high">High</span> |
| H8 Domain Boundary Violations | Populate `Modules/{Discovery,Connect}` with real code; ban cross-model imports; dashboard via published interfaces only | <span class="rating rating-high-risk">High Risk</span> | <span class="sev sev-critical">Critical</span> |
| H9 Shared Database Coupling | Split shared Mongo collections per domain or wrap with per-module ACLs; keep MariaDB table ownership | <span class="rating rating-high-risk">High Risk</span> | <span class="sev sev-high">High</span> |
| H10 Dual-Runtime Drift | Consolidate Laravel vs Node `dev-api` duplicated flows into one source of truth | <span class="rating rating-high-risk">High Risk</span> | <span class="sev sev-critical">Critical</span> |
| F5 Legacy Component Patterns | Remove/rewrite `LegacyMonitorPoller` + `LegacyDashboardWidget`; add root Error Boundary; colocate under `frontend/src/modules/` | <span class="rating rating-moderate">Moderate</span> | <span class="sev sev-medium">Medium</span> |

## 1.5 Expected Outcomes

- Controllers become thin HTTP adapters; Discovery/Connect workflows are unit-testable via application and domain services without booting Laravel HTTP kernel.
- Repository interfaces enable swapping MariaDB/Mongo implementations and aligning `dev-api` with the same contracts.
- Bounded contexts with published interfaces stop silent cross-domain regressions when IVR or TFN schemas change.
- Split or ACL-wrapped Mongo collections let each module evolve retention, indexes, and payload shapes independently.
- A single API runtime eliminates Laravel/Node drift so frontend testing matches production Docker behavior.
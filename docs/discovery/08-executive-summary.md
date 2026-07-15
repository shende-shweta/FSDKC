# Discovery Executive Summary

**Project:** discovery-15july-01 · **Generated:** 15/07/2026, 19:47:53

> **Executive Summary**
>
> This report consolidates the overall ratings, key findings, and recommended actions from the 4 discovery analyses run across this codebase (frontend and backend). Each section below reproduces that analysis's executive view; full evidence and diagrams live in the individual reports.

## Portfolio Overview

| # | Analysis | Overall Rating | Hotspot Score |
|---|---|---|---|
| 1 | Architecture & Design Analysis | <span class="rating rating-high-risk">High Risk</span> | — |
| 2 | Code Quality & Complexity Analysis | <span class="rating rating-high-risk">High Risk</span> | 45 / 100 — Moderate |
| 3 | Frontend Modernization Analysis | <span class="rating rating-high-risk">High Risk</span> | — |
| 4 | Backend Modernization Analysis | <span class="rating rating-high-risk">High Risk</span> | — |

---

## 1. Architecture & Design Analysis

<div class="overall-rating overall-rating--high-risk"><div class="overall-rating-label">Overall Codebase Rating — Architecture &amp; Design</div><div class="overall-rating-value">High Risk</div><div class="overall-rating-note">Driven by High-Risk Missing Service Layer (H2), Missing Repository Pattern (H3), Domain Boundary Violations (H8), Shared Database Coupling (H9), and Parallel API Runtimes (H10).</div></div>

> **Executive Summary**
>
> Analysis covered **backend** (15 PHP application files: 6 API controllers, 4 Eloquent models, 2 injectable services, 0 repositories), **frontend** (15 TS/TSX/JSX source files across pages, components, hooks, and store), and **dev-api** (6 JS files mirroring 18 Laravel routes) from `shende-shweta/FSDKC@main` via GitHub REST API (tree + raw content fetch). The Klearcom monorepo runs three parallel runtimes with no repository layer and thin service coverage. Backend layering is the dominant risk: **25 controller-to-model Eloquent access points** bypass application services, **71 persistence access points** occur outside any repository abstraction (32 Eloquent + 39 MongoDB/in-memory store delegations across Laravel and dev-api), and **8 cross-domain query sites** in `DashboardController::kpis` couple Discovery and Connect models without an anti-corruption layer. Four of five MariaDB tables lack domain-exclusive ownership (**80% shared-table coupling**), and Laravel + `dev-api` duplicate reachability KPI math, IVR `buildTree`, and dashboard aggregation in parallel. Frontend architecture is comparatively healthy (avg **86 LOC** per view component, centralized `api/client.ts`, max prop-drilling depth **2**), but one legacy class component lacks lifecycle cleanup. Overall verdict: **High Risk**, driven by H2, H3, H8, H9, and H10.

## 1.1 Benchmark Ratings Summary

| # | Hotspot | Primary KPI | <span class="rating rating-good">Good</span> | <span class="rating rating-moderate">Moderate</span> | <span class="rating rating-high-risk">High Risk</span> | Measured | Rating |
|---|---|---|---|---|---|---|---|
| H1 | Fat Controllers | Avg LOC per controller | <150 | 150–300 | >300 | 74 LOC avg | <span class="rating rating-good">Good</span> |
| H2 | Missing Service Layer | Controllers accessing repos/models | <10 | 10–20 | >20 | 25 access points | <span class="rating rating-high-risk">High Risk</span> |
| H3 | Missing Repository Pattern | Direct DB access points | <10 | 10–20 | >20 | 71 access points | <span class="rating rating-high-risk">High Risk</span> |
| H4 | Circular Dependencies | Dependency cycles | 0 | 1–3 | >3 | 0 | <span class="rating rating-good">Good</span> |
| H5 | Shared Utility Abuse | Utility files w/ business logic | 0 | 1–5 | >5 | 4 (`extract` sites) | <span class="rating rating-moderate">Moderate</span> |
| H6 | Direct SQL in Controllers | ORM compliance % | >90% | 60–90% | <60% | 100% ORM | <span class="rating rating-good">Good</span> |
| H7 | God Classes | Classes >1000 LOC | 0 | 1–3 | >3 | 0 (max 276 LOC) | <span class="rating rating-good">Good</span> |
| H8 | Domain Boundary Violations | Cross-domain access points | 0 | 1–5 | >5 | 8 | <span class="rating rating-high-risk">High Risk</span> |
| H9 | Shared Database Coupling | Tables shared across domains | <10% | 10–30% | >30% | 80% (4/5 tables) | <span class="rating rating-high-risk">High Risk</span> |
| F1 | Business Logic in Components | Avg LOC per component | <150 | 150–300 | >300 | 86 LOC avg | <span class="rating rating-good">Good</span> |
| F2 | Missing Frontend Service/Data Layer | Components w/ inline API calls | <10 | 10–20 | >20 | 0 inline fetch/axios | <span class="rating rating-good">Good</span> |
| F3 | God / Oversized Components | Components >400 LOC | 0 | 1–3 | >3 | 0 | <span class="rating rating-good">Good</span> |
| F4 | Prop Drilling / Global State Abuse | Max prop-drilling depth | ≤2 | 3–4 | >4 | 2 levels | <span class="rating rating-good">Good</span> |
| F5 | Legacy / Inconsistent Component Patterns | Legacy-pattern components | 0 | 1–10 | >10 | 1 class component | <span class="rating rating-moderate">Moderate</span> |
| H10 | Parallel API Runtimes (additional) | Duplicate API implementations across runtimes | 0 | 1 | ≥2 | 2 (Laravel + dev-api) | <span class="rating rating-high-risk">High Risk</span> |

No additional hotspots beyond the standard set were observed beyond H10 (Parallel API Runtimes).

## 1.4 Actions Required

| Hotspot | Action | Rating | Priority |
|---|---|---|---|
| H2 — Missing Service Layer | Introduce `ConnectMonitorService`, `DiscoveryJobService`, and `DashboardKpiService`; move all 25 controller model-access points into application services with constructor DI. | <span class="rating rating-high-risk">High Risk</span> | <span class="sev sev-critical">Critical</span> |
| H3 — Missing Repository Pattern | Create repository interfaces for all 4 Eloquent models and 3 MongoDB collections; route 71 persistence access points through injected repository implementations. | <span class="rating rating-high-risk">High Risk</span> | <span class="sev sev-critical">Critical</span> |
| H5 — Shared Utility Abuse | Replace `extract($filters)` with typed DTO mappers; extract shared `IvrTreeBuilder` from duplicated `buildTree` in `LegacyReportController`, `DiscoveryController`, and `dev-api/src/store.js`. | <span class="rating rating-moderate">Moderate</span> | <span class="sev sev-medium">Medium</span> |
| H8 — Domain Boundary Violations | Split cross-domain access in `DashboardController` and `LegacyReportController`; enforce Discovery/Connect bounded contexts with published read APIs. | <span class="rating rating-high-risk">High Risk</span> | <span class="sev sev-critical">Critical</span> |
| H9 — Shared Database Coupling | Assign table ownership per domain in `docker/mariadb/init.sql`; introduce per-domain migration files and integration views for cross-domain KPIs. | <span class="rating rating-high-risk">High Risk</span> | <span class="sev sev-critical">Critical</span> |
| H10 — Parallel API Runtimes | Deprecate `dev-api` duplicate runtime; consolidate to Laravel API with Docker Compose for local dev, or generate dev-api from OpenAPI spec. | <span class="rating rating-high-risk">High Risk</span> | <span class="sev sev-critical">Critical</span> |
| F5 — Legacy Component Patterns | Rewrite `LegacyMonitorPoller.jsx` as function component with `useEffect` cleanup; add Error Boundary around `LegacyDashboardWidget`. | <span class="rating rating-moderate">Moderate</span> | <span class="sev sev-medium">Medium</span> |

## 1.5 Expected Outcomes

- **Testable business logic:** Application services and repositories enable unit tests without HTTP or database bootstrapping, cutting test setup time for Connect reachability and Discovery tree workflows.
- **Independent domain evolution:** Bounded contexts with owned schemas let Discovery and Connect teams ship schema changes without cross-module regressions in dashboard KPIs.
- **Single source of truth:** Eliminating the parallel `dev-api` runtime removes behavioral drift between local development and production Laravel deployments.
- **Reduced change amplification:** Centralizing reachability calculation and IVR tree building in one service eliminates the current four-way duplication across controllers, services, and Node handlers.
- **Frontend stability:** Migrating the legacy class component and adding Error Boundaries prevents interval leaks and uncaught render errors during SPA navigation.

---

## 2. Code Quality & Complexity Analysis

<div class="overall-rating overall-rating--high-risk"><div class="overall-rating-label">Overall Codebase Rating — Code Quality &amp; Complexity</div><div class="overall-rating-value">High Risk</div><div class="overall-rating-note">Driven by High-Risk cyclomatic complexity in frontend page components (H1), oversized `ConnectPage` function (H3), cross-runtime business-logic duplication (H4), parallel Laravel/dev-api workflow copies (H9), and PHP `extract()` dynamic variables (H11).</div></div>

> **Executive Summary**
>
> Analysis covered **backend** (16 PHP application files, 807 SLOC), **frontend** (14 TS/TSX/JSX files, 874 SLOC), and **dev-api** (6 JS files, 720 SLOC) — **36 files / 2,401 SLOC** total — from `shende-shweta/FSDKC@main` via GitHub REST API (public tree + raw content + Commits API). No cyclomatic-complexity linter (`eslint-plugin-complexity`, `phpmd`, Sonar) is configured; metrics were derived by manual branch/loop counting on fetched source. The dominant risks are **frontend page-level complexity** (`ConnectPage.tsx` cyclomatic complexity **44**, **201 LOC** default-export function), **cross-runtime business-logic duplication** (~**13.2%** of codebase duplicated between Laravel and `dev-api` for KPI aggregation, realtime test orchestration, and IVR `buildTree`), and **three `extract()` dynamic-variable sites** in legacy PHP code. No files exceed 1,000 LOC (largest: `dev-api/src/server.js` at **224 SLOC**). Git history is shallow (**3 commits**, single author `ksabai-gl` since June 2026), so churn and ownership signals are healthy but low-confidence. Overall verdict: **High Risk**, driven by H1, H3, H4, H9, and H11.

## 2.1 Benchmark Ratings Summary

| # | Hotspot | Primary KPI | <span class="rating rating-good">Good</span> | <span class="rating rating-moderate">Moderate</span> | <span class="rating rating-high-risk">High Risk</span> | Measured | Rating |
|---|---|---|---|---|---|---|---|
| H1 | High Cyclomatic Complexity | Max complexity per method | <10 | 10–20 | >20 | 44 (`ConnectPage`) | <span class="rating rating-high-risk">High Risk</span> |
| H2 | Large Classes | Largest class LOC | <300 | 300–1000 | >1000 | 224 LOC (`dev-api/src/server.js`) | <span class="rating rating-good">Good</span> |
| H3 | Large Functions | Largest function LOC | <50 | 50–200 | >200 | 201 LOC (`ConnectPage`) | <span class="rating rating-high-risk">High Risk</span> |
| H4 | Business Logic Duplication | Duplicated business logic % | <5% | 5–10% | >10% | ~13.2% (~317 / 2,401 LOC) | <span class="rating rating-high-risk">High Risk</span> |
| H5 | Duplicate Code (general) | Overall duplicate code % | <5% | 5–10% | >10% | ~8.6% (~206 / 2,401 LOC) | <span class="rating rating-moderate">Moderate</span> |
| H6 | High Churn Areas | Monthly changes (top files) | <5 | 5–10 | >10 | 2 changes/mo (top app files) | <span class="rating rating-good">Good</span> |
| H7 | Defect-Prone Files | Fix commits (hottest file) | 1–3 | 4–5 | >5 | 1 fix commit (`frontend/src/App.tsx`) | <span class="rating rating-good">Good</span> |
| H8 | Ownership Issues | Top-author ownership % | >80% | 60–80% | <60% | 100% (1 author / 3 commits) | <span class="rating rating-good">Good</span> |
| H9 | Parallel Runtime Duplication (additional) | Duplicated workflow LOC across Laravel + dev-api / backend LOC | <5% | 5–15% | >15% | ~20.8% (~317 / 1,527 backend+dev-api LOC) | <span class="rating rating-high-risk">High Risk</span> |
| H10 | God Page Components (additional) | Largest page component LOC (UI + data + realtime) | <150 | 150–300 | >300 | 208 LOC (`ConnectPage.tsx`) | <span class="rating rating-moderate">Moderate</span> |
| H11 | PHP extract() Dynamic Variables (additional) | `extract()` call sites in application code | 0 | 1–2 | >2 | 3 (`LegacyReportController`, `LegacyDataMapper` ×2) | <span class="rating rating-high-risk">High Risk</span> |

### Hotspot Score breakdown

| Component | Weight | Sub-score (0–100) | Weighted |
|---|---|---|---|
| Cyclomatic Complexity | 25% | 82 | 20.5 |
| Code Churn | 25% | 10 | 2.5 |
| Defect Density | 20% | 15 | 3.0 |
| Class/Function Size | 15% | 75 | 11.25 |
| Business Logic Duplication | 10% | 78 | 7.8 |
| Developer Ownership Risk | 5% | 5 | 0.25 |
| **Hotspot Score** | **100%** | | **45 / 100** |

## 2.5 Actions Required

| Hotspot | Action | Rating | Priority |
|---|---|---|---|
| H1 — High Cyclomatic Complexity | Extract `useModulePageQueries` hook; split `ConnectPage` and `DiscoveryPage` into ≤80 LOC sub-components; enable ESLint complexity rule (max 15) on `frontend/src/pages/`. | <span class="rating rating-high-risk">High Risk</span> | <span class="sev sev-critical">Critical</span> |
| H3 — Large Functions | Decompose `ConnectPage` (201 LOC) into `useConnectPageState.ts` + 3 presentational components; apply same pattern to `DiscoveryPage` (154 LOC). | <span class="rating rating-high-risk">High Risk</span> | <span class="sev sev-high">High</span> |
| H4 — Business Logic Duplication | Create `DashboardKpiCalculator` and shared test-step definitions in Laravel; remove duplicated KPI and realtime blocks from `dev-api/src/server.js` and `dev-api/src/realtime.js`. | <span class="rating rating-high-risk">High Risk</span> | <span class="sev sev-critical">Critical</span> |
| H5 — Duplicate Code | Extract `TreeBuilder` utility from four `buildTree` copies; add `jscpd`/`phpcpd` CI gate at 5% threshold. | <span class="rating rating-moderate">Moderate</span> | <span class="sev sev-medium">Medium</span> |
| H9 — Parallel Runtime Duplication | Deprecate `dev-api` runtime or auto-generate from Laravel OpenAPI; add contract tests until removal. | <span class="rating rating-high-risk">High Risk</span> | <span class="sev sev-critical">Critical</span> |
| H10 — God Page Components | Introduce `ModulePageLayout`, `EntityForm`, and `TestSessionPanel` shared components; cap page files at 120 LOC. | <span class="rating rating-moderate">Moderate</span> | <span class="sev sev-medium">Medium</span> |
| H11 — PHP extract() Dynamic Variables | Replace `extract($filters)` with typed `CarrierSummaryFilter` DTO; refactor `LegacyDataMapper` to explicit key access; add PHPStan `extract()` ban. | <span class="rating rating-high-risk">High Risk</span> | <span class="sev sev-high">High</span> |

## 2.6 Expected Outcomes

- **Lower defect rate on UI changes:** Splitting `ConnectPage`/`DiscoveryPage` and extracting shared hooks reduces cyclomatic complexity from 44 to a testable target of <15 per function.
- **Single source of truth for KPIs and test pipelines:** Consolidating dashboard and realtime logic in Laravel eliminates the ~13% duplicated business-rule surface between production and `dev-api`.
- **Safer refactors:** Extracting `TreeBuilder` and domain services lets IVR tree and reachability changes propagate from one module instead of four `buildTree` copies.
- **Eliminated dynamic-variable risk:** Replacing `extract()` with typed DTOs enables PHPStan static analysis and prevents variable-injection bugs in legacy report endpoints.
- **Faster reviews:** Smaller page components and a complexity lint gate keep new features from re-expanding god components.

---

## 3. Frontend Modernization Analysis

<div class="overall-rating overall-rating--high-risk"><div class="overall-rating-label">Overall Codebase Rating — Frontend Modernization</div><div class="overall-rating-value">High Risk</div><div class="overall-rating-note">Driven by High-Risk UI Component Duplication (H1), Global State Dependencies (H4), Inline Styles Without Design Tokens (H6), and Missing Accessibility Roles (H7).</div></div>

> **Executive Summary**
>
> Analysis covered **14 frontend source files** (8 view components across `pages/` and `components/`) from `shende-shweta/FSDKC@main` via GitHub REST API (tree + raw content fetch). The Klearcom SPA is predominantly modern — function components, TanStack React Query v5, Zustand v5, and React Router v7 — with a centralized `api/client.ts` and reusable `useRealtimeTest` hook. However, **ConnectPage.tsx** and **DiscoveryPage.tsx** duplicate an identical workbench shell (entity form, live feed, two-column grid, transcript panel), inflating UI duplication to **25%**. Six of eight view components depend on shared global state (Zustand, React Query cache, or the singleton `api` client), and **30 inline `style={{}}` occurrences** bypass the CSS token system in `index.css`. One legacy class component (`LegacyMonitorPoller.jsx`) lacks `componentWillUnmount` cleanup and leaks intervals; two orphan legacy modules coexist with the React Query stack. Zero `aria-*` / `role` attributes were found across all view components. Overall verdict: **High Risk**, driven by H1 (UI duplication), H4 (global state coupling), H6 (inline styles), and H7 (missing accessibility).

## 3.1 Benchmark Ratings Summary

| # | Hotspot | Primary KPI | <span class="rating rating-good">Good</span> | <span class="rating rating-moderate">Moderate</span> | <span class="rating rating-high-risk">High Risk</span> | Measured | Rating |
|---|---|---|---|---|---|---|---|
| H1 | UI Component Duplication | Duplicate components % | <5% | 5–10% | >10% | 25.0% (2/8) | <span class="rating rating-high-risk">High Risk</span> |
| H2 | Legacy Class-Based Components | Modern component adoption % | >90% | 70–90% | <70% | 87.5% (7/8) | <span class="rating rating-moderate">Moderate</span> |
| H3 | Massive Components | Largest component LOC | <200 | 200–500 | >500 | 222 (`ConnectPage.tsx`) | <span class="rating rating-moderate">Moderate</span> |
| H4 | Global State Dependencies | Components reading global state % | <30% | 30–60% | >60% | 75.0% (6/8) | <span class="rating rating-high-risk">High Risk</span> |
| H5 | Complex State Management | Max prop-drilling depth | <3 | 3–5 | >5 | 2 levels | <span class="rating rating-good">Good</span> |
| H6 | Inline Styles / No Design Tokens (additional) | Total `style={{}}` occurrences (target <10) | <10 | 10–20 | >20 | 30 across 7 files | <span class="rating rating-high-risk">High Risk</span> |
| H7 | Missing Accessibility Roles (additional) | Components with `aria-*` or `role` (target >80%) | >80% | 50–80% | <50% | 0% (0/8) | <span class="rating rating-high-risk">High Risk</span> |

## 3.5 Actions Required

| Hotspot | Action | Rating | Priority |
|---|---|---|---|
| H1 — UI Component Duplication | Extract `DomainWorkbenchPage`, `EntityFormCard`, and `TranscriptPanel` from `ConnectPage.tsx` and `DiscoveryPage.tsx`; reduce duplicate shell to domain hooks only. | <span class="rating rating-high-risk">High Risk</span> | <span class="sev sev-critical">Critical</span> |
| H2 — Legacy Class-Based Components | Convert `LegacyMonitorPoller.jsx` to `useMonitorReachability` hook; migrate `LegacyDashboardWidget.tsx` to React Query or delete both orphan modules. | <span class="rating rating-moderate">Moderate</span> | <span class="sev sev-medium">Medium</span> |
| H3 — Massive Components | Split `ConnectPage.tsx` (222 LOC) and `DiscoveryPage.tsx` (176 LOC) into container hooks + presentational sub-components; target <150 LOC per page. | <span class="rating rating-moderate">Moderate</span> | <span class="sev sev-medium">Medium</span> |
| H4 — Global State Dependencies | Replace `useUiStore` selection with URL search params; add `frontend/src/api/queryKeys.ts`; scope query invalidation to domain keys. | <span class="rating rating-high-risk">High Risk</span> | <span class="sev sev-high">High</span> |
| H6 — Inline Styles | Add CSS utility classes to `index.css`; migrate 30 inline `style={{}}` occurrences (18 in domain pages) to token-based classes. | <span class="rating rating-high-risk">High Risk</span> | <span class="sev sev-high">High</span> |
| H7 — Missing Accessibility | Add `aria-live` to `LiveTestFeed`, keyboard-accessible row selection in domain tables, and `htmlFor` on form labels. | <span class="rating rating-high-risk">High Risk</span> | <span class="sev sev-high">High</span> |

## 3.6 Expected Outcomes

- **Shared component library** eliminates the 25% duplicate page-shell pattern; Connect and Discovery fixes ship once via `DomainWorkbenchPage` and `TranscriptPanel`.
- **Hooks-first migration** of `LegacyMonitorPoller` removes interval leaks and aligns all data access on React Query with testable custom hooks.
- **Scoped state** (URL params + domain query keys) reduces cross-feature invalidation fan-out and makes monitor/job selection shareable via deep links.
- **Design-token consistency** from replacing 30 inline styles lowers theming cost and aligns with the existing `:root` token system in `index.css`.
- **Accessibility baseline** (`aria-live`, keyboard rows, label associations) makes realtime test feeds and data tables operable for keyboard and screen-reader users in an operations-focused product.

---

**Deliverables saved:**
- Full report: `target/docs/discovery/03-frontend-modernization.md`
- Pipeline summary: `agent-runs/20260715T193441_nth87z/03-frontend-modernization-summary.md`

---

## 4. Backend Modernization Analysis

<div class="overall-rating overall-rating--high-risk"><div class="overall-rating-label">Overall Codebase Rating — Backend Modernization</div><div class="overall-rating-value">High Risk</div><div class="overall-rating-note">Driven by Direct SQL/ORM Outside Data Layer (H3), Missing Service Layer (H5), API Sprawl (H6), Missing API Governance (H7), and Parallel API Runtimes (H8).</div></div>

> **Executive Summary**
>
> Analysis covered **24 controllers/handlers** (6 Laravel API controllers + 18 Express route handlers), **37 REST endpoints**, and **2 injectable service classes** (0 repositories) from `shende-shweta/FSDKC@main` via GitHub REST API (recursive tree + raw content fetch). The Klearcom backend runs two parallel API runtimes: Laravel 12 uses constructor injection for `MongoService` and `RealTimeTestService`, but **25 of 32 Eloquent access points (78%)** remain in controllers with no repository layer, and four controllers embed KPI math, IVR tree building, and reachability calculations inline. Three PHP `extract()` calls — including one on raw `$request->all()` — create untyped variable scope from user input. The Node `dev-api` holds all relational state in a module-level mutable `store` singleton with **18 inline route handlers** mirroring Laravel. No OpenAPI spec, API versioning, or contract tests exist; CI runs PHPUnit and frontend build only. Overall verdict: **High Risk**, driven by data-layer bypass (H3), missing service tier across dual runtimes (H5), API sprawl and zero governance (H6–H7), and parallel API implementations (H8).

## 4.1 Benchmark Ratings Summary

| # | Hotspot | Primary KPI | <span class="rating rating-good">Good</span> | <span class="rating rating-moderate">Moderate</span> | <span class="rating rating-high-risk">High Risk</span> | Measured | Rating |
|---|---|---|---|---|---|---|---|
| H1 | Dynamic Variable Creation | Dynamic-var-from-input occurrences | 0 | 1–10 | >10 | 3 (`extract()` calls) | <span class="rating rating-moderate">Moderate</span> |
| H2 | Global Mutable State | Globals / mutable static state | 0 | 1–5 | >5 | 2 module-level stores | <span class="rating rating-moderate">Moderate</span> |
| H3 | Direct SQL Outside Data Layer | Data-layer compliance % | >90% | 60–90% | <60% | 22% (7/32 Eloquent in services) | <span class="rating rating-high-risk">High Risk</span> |
| H4 | Static / Singleton Abuse | Business-logic static/singleton classes | 0 | 1–5 | >5 | 0 | <span class="rating rating-good">Good</span> |
| H5 | Missing Service Layer | Handlers with inline business logic | <10 | 10–20 | >20 | 22 (4 Laravel controllers + 18 dev-api routes) | <span class="rating rating-high-risk">High Risk</span> |
| H6 | API Sprawl | Documented & governed endpoints % | >90% | 80–90% | <80% | 15% single-source (3/20 capabilities); 0% governed | <span class="rating rating-high-risk">High Risk</span> |
| H7 | Missing API Governance | Governance compliance % | 100% | 90–99% | <90% | 0% (no spec, versioning, or contract tests) | <span class="rating rating-high-risk">High Risk</span> |
| H8 | Parallel API Runtimes (additional) | Capabilities with duplicate Laravel + dev-api handlers (target 0) | 0 | 1–5 | >5 | 17 duplicated capabilities | <span class="rating rating-high-risk">High Risk</span> |
| H9 | Missing Input Validation on dev-api (additional) | POST/PUT routes without schema validation (target 0) | 0 | 1–3 | >3 | 4 unvalidated write routes | <span class="rating rating-high-risk">High Risk</span> |

## 4.5 Actions Required

| Hotspot | Action | Rating | Priority |
|---|---|---|---|
| H1 — Dynamic Variable Creation | Replace `extract($filters)` in `LegacyReportController` with validated request DTO; refactor `LegacyDataMapper` to explicit field access; add CI ban on `extract()`. | <span class="rating rating-moderate">Moderate</span> | <span class="sev sev-high">High</span> |
| H2 — Global Mutable State | Encapsulate `dev-api/src/store.js` in an injectable repository; eliminate module-level business mutation or retire dev-api. | <span class="rating rating-moderate">Moderate</span> | <span class="sev sev-medium">Medium</span> |
| H3 — Direct SQL Outside Data Layer | Create Eloquent repositories for all four models; move 25 controller ORM calls into repositories; target >90% data-layer compliance. | <span class="rating rating-high-risk">High Risk</span> | <span class="sev sev-critical">Critical</span> |
| H5 — Missing Service Layer | Add `DashboardService`, `DiscoveryService`, `ConnectService`; move KPI/tree/reachability logic out of controllers and dev-api routes. | <span class="rating rating-high-risk">High Risk</span> | <span class="sev sev-critical">Critical</span> |
| H6 — API Sprawl | Consolidate 17 duplicated capabilities under Laravel; deprecate overlapping dev-api routes; publish canonical endpoint list. | <span class="rating rating-high-risk">High Risk</span> | <span class="sev sev-high">High</span> |
| H7 — Missing API Governance | Author `docs/openapi.yaml`; add `/api/v1/` versioning; introduce contract tests in CI via schemathesis or equivalent. | <span class="rating rating-high-risk">High Risk</span> | <span class="sev sev-high">High</span> |
| H8 — Parallel API Runtimes | Retire or proxy `dev-api` to Laravel; eliminate triplicated reachability and dashboard KPI logic. | <span class="rating rating-high-risk">High Risk</span> | <span class="sev sev-critical">Critical</span> |
| H9 — Missing Input Validation on dev-api | Add express-validator schemas on all POST routes; remove or secure `bulk-import` endpoint. | <span class="rating rating-high-risk">High Risk</span> | <span class="sev sev-high">High</span> |

## 4.6 Expected Outcomes

- **Repository layer** moves 25 controller Eloquent calls behind testable boundaries, raising data-layer compliance from 22% to >90% and enabling mocked persistence in PHPUnit feature tests.
- **Application services** (`DashboardService`, `DiscoveryService`, `ConnectService`) eliminate triplicated KPI, tree, and reachability logic — fixes ship once and propagate to all entry points.
- **Retiring parallel dev-api routes** removes 17 duplicate handlers and the module-level `store` singleton, halving API surface area and integration drift risk.
- **OpenAPI spec + contract tests** catch breaking response-shape changes before merge, giving the React SPA a machine-verifiable integration contract.
- **Replacing `extract()` with typed DTOs** closes the variable-scope injection vector in legacy reporting and aligns with AGENTS.md engineering standards.
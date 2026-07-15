# Discovery Executive Summary

**Project:** discovery-15july-01 · **Generated:** 15/07/2026, 20:00:54

> **Executive Summary**
>
> This report consolidates the overall ratings, key findings, and recommended actions from the 7 discovery analyses run across this codebase (frontend and backend). Each section below reproduces that analysis's executive view; full evidence and diagrams live in the individual reports.

## Portfolio Overview

| # | Analysis | Overall Rating | Hotspot Score |
|---|---|---|---|
| 1 | Architecture & Design Analysis | <span class="rating rating-high-risk">High Risk</span> | — |
| 2 | Code Quality & Complexity Analysis | <span class="rating rating-high-risk">High Risk</span> | 44 / 100 — Moderate |
| 3 | Frontend Modernization Analysis | <span class="rating rating-high-risk">High Risk</span> | — |
| 4 | Backend Modernization Analysis | <span class="rating rating-high-risk">High Risk</span> | — |
| 5 | Testing & Quality Assurance Analysis | <span class="rating rating-high-risk">High Risk</span> | — |
| 6 | Security Analysis | <span class="rating rating-high-risk">High Risk</span> | — |
| 7 | Technical Debt | <span class="rating rating-high-risk">High Risk</span> | — |

---

## 1. Architecture & Design Analysis

<div class="overall-rating overall-rating--high-risk"><div class="overall-rating-label">Overall Codebase Rating — Architecture &amp; Design</div><div class="overall-rating-value">High Risk</div><div class="overall-rating-note">Driven by High-Risk Missing Service Layer (H2), Missing Repository Pattern (H3), Domain Boundary Violations (H8), Shared Database Coupling (H9), and Parallel API Runtimes (H10).</div></div>

> **Executive Summary**
>
> Analysis covered **backend** (15 PHP application files: 6 API controllers, 4 Eloquent models, 2 injectable services, 0 repositories), **frontend** (15 TS/TSX/JSX source files across pages, components, hooks, and store), and **dev-api** (6 JS files mirroring 18 Laravel routes) from `shende-shweta/FSDKC@main` via GitHub REST API (recursive tree + raw content fetch). The Klearcom monorepo runs three parallel runtimes with no repository layer and thin service coverage. Backend layering is the dominant risk: **25 controller-to-model Eloquent access points** bypass application services, **83 persistence access points** occur outside any repository abstraction (Eloquent + MongoDB/in-memory store delegations across Laravel and dev-api), and **12 cross-domain query sites** in `DashboardController` and `LegacyReportController` couple Discovery and Connect models without an anti-corruption layer. Four of five MariaDB tables are accessed from three or more modules (**80% shared-table coupling**), and Laravel + `dev-api` duplicate reachability KPI math, IVR `buildTree`, and dashboard aggregation in parallel. Frontend architecture is comparatively healthy (avg **86 LOC** per view component, centralized `api/client.ts`, max prop-drilling depth **2**), but one legacy class component lacks lifecycle cleanup. Overall verdict: **High Risk**, driven by H2, H3, H8, H9, and H10.

## 1.1 Benchmark Ratings Summary

| # | Hotspot | Primary KPI | <span class="rating rating-good">Good</span> | <span class="rating rating-moderate">Moderate</span> | <span class="rating rating-high-risk">High Risk</span> | Measured | Rating |
|---|---|---|---|---|---|---|---|
| H1 | Fat Controllers | Avg LOC per controller | <150 | 150–300 | >300 | 74 LOC avg | <span class="rating rating-good">Good</span> |
| H2 | Missing Service Layer | Controllers accessing repos/models | <10 | 10–20 | >20 | 25 access points | <span class="rating rating-high-risk">High Risk</span> |
| H3 | Missing Repository Pattern | Direct DB access points | <10 | 10–20 | >20 | 83 access points | <span class="rating rating-high-risk">High Risk</span> |
| H4 | Circular Dependencies | Dependency cycles | 0 | 1–3 | >3 | 0 | <span class="rating rating-good">Good</span> |
| H5 | Shared Utility Abuse | Utility files w/ business logic | 0 | 1–5 | >5 | 4 (`extract` sites) | <span class="rating rating-moderate">Moderate</span> |
| H6 | Direct SQL in Controllers | ORM compliance % | >90% | 60–90% | <60% | 100% ORM | <span class="rating rating-good">Good</span> |
| H7 | God Classes | Classes >1000 LOC | 0 | 1–3 | >3 | 0 (max 276 LOC) | <span class="rating rating-good">Good</span> |
| H8 | Domain Boundary Violations | Cross-domain access points | 0 | 1–5 | >5 | 12 | <span class="rating rating-high-risk">High Risk</span> |
| H9 | Shared Database Coupling | Tables shared across domains | <10% | 10–30% | >30% | 80% (4/5 tables) | <span class="rating rating-high-risk">High Risk</span> |
| F1 | Business Logic in Components | Avg LOC per component | <150 | 150–300 | >300 | 86 LOC avg | <span class="rating rating-good">Good</span> |
| F2 | Missing Frontend Service/Data Layer | Components w/ inline API calls | <10 | 10–20 | >20 | 0 inline fetch/axios | <span class="rating rating-good">Good</span> |
| F3 | God / Oversized Components | Components >400 LOC | 0 | 1–3 | >3 | 0 | <span class="rating rating-good">Good</span> |
| F4 | Prop Drilling / Global State Abuse | Max prop-drilling depth | ≤2 | 3–4 | >4 | 2 levels | <span class="rating rating-good">Good</span> |
| F5 | Legacy / Inconsistent Component Patterns | Legacy-pattern components | 0 | 1–10 | >10 | 1 class component | <span class="rating rating-moderate">Moderate</span> |
| H10 | Parallel API Runtimes (additional) | Duplicate API implementations across runtimes | 0 | 1 | ≥2 | 2 (Laravel + dev-api) | <span class="rating rating-high-risk">High Risk</span> |

## 1.4 Actions Required

| Hotspot | Action | Rating | Priority |
|---|---|---|---|
| H2 — Missing Service Layer | Introduce `ConnectMonitorService`, `DiscoveryJobService`, and `DashboardKpiService`; move all 25 controller model-access points into application services with constructor DI. | <span class="rating rating-high-risk">High Risk</span> | <span class="sev sev-critical">Critical</span> |
| H3 — Missing Repository Pattern | Create repository interfaces for all 4 Eloquent models and 3 MongoDB collections; route 83 persistence access points through injected repository implementations. | <span class="rating rating-high-risk">High Risk</span> | <span class="sev sev-critical">Critical</span> |
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

Full report with §1.2 evidence and §1.3 Mermaid diagrams: `target/docs/discovery/01-architecture-design.md` (PDF conversion runs automatically in the orchestration UI).

---

## 2. Code Quality & Complexity Analysis

<div class="overall-rating overall-rating--high-risk"><div class="overall-rating-label">Overall Codebase Rating — Code Quality &amp; Complexity</div><div class="overall-rating-value">High Risk</div><div class="overall-rating-note">Driven by High-Risk cyclomatic complexity in frontend page components (H1), oversized `ConnectPage` function (H3), cross-runtime business-logic duplication (H4), parallel Laravel/dev-api workflow copies (H9), and PHP `extract()` dynamic variables (H11).</div></div>

> **Executive Summary**
>
> Re-analysis covered **backend** (16 PHP application files, 807 SLOC), **frontend** (14 TS/TSX/JSX files, 874 SLOC), and **dev-api** (6 JS files, 720 SLOC) — **36 files / 2,401 SLOC** total — from `shende-shweta/FSDKC@main` via GitHub REST API (tree + raw content; Commits API partially rate-limited). No cyclomatic-complexity linter (`eslint-plugin-complexity`, `phpmd`, Sonar) is configured; metrics were derived by automated branch/loop counting on fetched source. The dominant risks remain **frontend page-level complexity** (`ConnectPage.tsx` cyclomatic complexity **37**, **201 LOC** default-export function), **cross-runtime business-logic duplication** (~**13.2%** of codebase duplicated between Laravel and `dev-api` for KPI aggregation, realtime test orchestration, and IVR `buildTree`), and **three `extract()` dynamic-variable sites** in legacy PHP code. No files exceed 1,000 LOC (largest: `dev-api/src/server.js` at **224 SLOC**). Git history is shallow (**3 commits**, single author `ksabai-gl` since June 2026), so churn and ownership signals are healthy but low-confidence. Overall verdict: **High Risk**, driven by H1, H3, H4, H9, and H11.

## 2.1 Benchmark Ratings Summary

| # | Hotspot | Primary KPI | <span class="rating rating-good">Good</span> | <span class="rating rating-moderate">Moderate</span> | <span class="rating rating-high-risk">High Risk</span> | Measured | Rating |
|---|---|---|---|---|---|---|---|
| H1 | High Cyclomatic Complexity | Max complexity per method | <10 | 10–20 | >20 | 37 (`ConnectPage`) | <span class="rating rating-high-risk">High Risk</span> |
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
| Cyclomatic Complexity | 25% | 76 | 19.0 |
| Code Churn | 25% | 10 | 2.5 |
| Defect Density | 20% | 15 | 3.0 |
| Class/Function Size | 15% | 75 | 11.25 |
| Business Logic Duplication | 10% | 78 | 7.8 |
| Developer Ownership Risk | 5% | 5 | 0.25 |
| **Hotspot Score** | **100%** | | **44 / 100** |

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

- **Lower defect rate on UI changes:** Splitting `ConnectPage`/`DiscoveryPage` and extracting shared hooks reduces cyclomatic complexity from 37 to a testable target of <15 per function.
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

---

## 5. Testing & Quality Assurance Analysis

<div class="overall-rating overall-rating--high-risk"><div class="overall-rating-label">Overall Codebase Rating — Testing &amp; Quality Assurance</div><div class="overall-rating-value">High Risk</div><div class="overall-rating-note">Driven by High-Risk untested critical logic (H1), low coverage (H2), missing integration tests (H3), missing contract tests (H4), absent E2E tests (H7), and assertion-free unit tests (H8).</div></div>

> **Executive Summary**
>
> Test-suite health across the FSDKC monorepo is **critically thin**. The backend ships **PHPUnit 11** with only **2 unit test files** (3 test methods) that assert hard-coded arrays and random numbers — none import `App\` production classes. The React 19 / Vite 6 / TypeScript frontend (**15 source files**) and Node `dev-api` (**6 source files, 18 route handlers**) have **zero** test files and no Jest, Vitest, Cypress, or Playwright configuration. Estimated overall coverage by test-file-to-source ratio is **~6%** (2 test files / 36 application source files); per-layer split is **backend ~13% file ratio (0% effective)**, **frontend 0%**, and **dev-api 0%**. No coverage reports (`lcov`, `clover.xml`) exist in the repository. GitHub Actions runs `vendor/bin/phpunit` against MariaDB on every PR (backend gate), but the frontend job only runs `npm run build` and `dev-api` is excluded entirely. The highest-risk gaps are untested Discovery/Connect orchestration (`RealTimeTestService`, six API controllers), no integration or contract tests for **19 Laravel API routes** (37 total across dual runtimes), and no E2E coverage for realtime SSE workflows. Overall verdict: **High Risk**.

## 5.1 Benchmark Ratings Summary

| # | Hotspot | Primary KPI | <span class="rating rating-good">Good</span> | <span class="rating rating-moderate">Moderate</span> | <span class="rating rating-high-risk">High Risk</span> | Measured | Rating |
|---|---|---|---|---|---|---|---|
| H1 | Untested Critical Logic | Critical modules with zero tests | 0 | 1–3 | >3 | 9 modules | <span class="rating rating-high-risk">High Risk</span> |
| H2 | Low Test Coverage | Overall coverage % | >80% | 50–80% | <50% | ~6% estimated (2/36 app source files; no coverage report) | <span class="rating rating-high-risk">High Risk</span> |
| H3 | Missing Integration Tests | Boundaries covered % | >70% | 30–70% | <30% | 0% (0 Feature/HTTP/DB tests) | <span class="rating rating-high-risk">High Risk</span> |
| H4 | Missing Contract Tests | APIs with contract tests % | >80% | 40–80% | <40% | 0% (0/19 Laravel endpoints; 0/18 dev-api routes) | <span class="rating rating-high-risk">High Risk</span> |
| H5 | Flaky / Skipped Tests | Skipped/flaky test count | 0 | 1–5 | >5 | 0 | <span class="rating rating-good">Good</span> |
| H6 | No CI Test Gate | Tests enforced in CI | Required gate | Runs, not required | No CI test run | Backend PHPUnit on PR; frontend build-only; dev-api excluded | <span class="rating rating-moderate">Moderate</span> |
| H7 | No End-to-End Tests (additional) | Critical user flows with E2E specs | ≥2 flows | 1 flow | 0 flows | 0 E2E specs | <span class="rating rating-high-risk">High Risk</span> |
| H8 | Assertion-Free Unit Tests (additional) | Unit tests exercising production code % | >80% | 40–80% | <40% | 0% (0/2 tests import `App\` code) | <span class="rating rating-high-risk">High Risk</span> |

## 5.4 Actions Required

| Hotspot | Action | Rating | Priority |
|---|---|---|---|
| H1 — Untested Critical Logic | Add PHPUnit tests for `RealTimeTestService`, all six API controllers, and `MongoService`; add Vitest tests for `useRealtimeTest`, pages, and `api/client.ts`; add or remove dev-api with parity tests. | <span class="rating rating-high-risk">High Risk</span> | <span class="sev sev-critical">Critical</span> |
| H2 — Low Test Coverage | Establish coverage baselines (PHPUnit `--coverage-text`, Vitest `--coverage`); enforce 75% backend / 60% frontend thresholds in CI before refactors. | <span class="rating rating-high-risk">High Risk</span> | <span class="sev sev-critical">Critical</span> |
| H3 — Missing Integration Tests | Create `backend/tests/Feature/` with `RefreshDatabase` + MariaDB; test Discovery job lifecycle, Connect check flow, and SSE streaming boundaries. | <span class="rating rating-high-risk">High Risk</span> | <span class="sev sev-high">High</span> |
| H4 — Missing Contract Tests | Publish OpenAPI spec; add JSON Schema contract tests for all 19 API endpoints; validate TypeScript types against fixtures. | <span class="rating rating-high-risk">High Risk</span> | <span class="sev sev-high">High</span> |
| H6 — No CI Test Gate | Add `vitest run` to frontend CI job; add dev-api test job or deprecate runtime; mark all test jobs as required PR checks. | <span class="rating rating-moderate">Moderate</span> | <span class="sev sev-medium">Medium</span> |
| H7 — No End-to-End Tests | Add Playwright with Docker Compose; cover Discovery create→start→stream and Connect create→check→alert flows. | <span class="rating rating-high-risk">High Risk</span> | <span class="sev sev-high">High</span> |
| H8 — Assertion-Free Unit Tests | Replace `HealthTest` and `ReachabilityCalculationTest` with tests that import `App\` classes; fail CI on assertion-free unit tests. | <span class="rating rating-high-risk">High Risk</span> | <span class="sev sev-medium">Medium</span> |

## 5.5 Expected Outcomes

- **Critical paths protected:** Discovery job orchestration, Connect reachability calculation, and MongoDB event streaming have automated regression tests before any service-layer extraction.
- **CI catches full-stack regressions:** Required PHPUnit + Vitest + Playwright gates on every PR prevent untested changes from merging.
- **Contract stability:** JSON Schema tests for 19 API endpoints prevent breaking changes to `{ data: ... }` envelopes and SSE event shapes consumed by the React SPA.
- **Effective coverage above 75%:** Replacing smoke tests and adding Feature/Vitest suites raises measured coverage from ~6% to refactor-safe levels.
- **Parallel runtime drift eliminated:** Contract parity tests (or dev-api removal) ensure local development matches production Laravel behavior.

---

## 6. Security Analysis

<div class="overall-rating overall-rating--high-risk"><div class="overall-rating-label">Overall Codebase Rating — Security</div><div class="overall-rating-value">High Risk</div><div class="overall-rating-note">Driven by Critical missing API authentication (H1), High permissive CORS and session stream IDOR (H2), and 9/12 OWASP categories with concrete findings (H5).</div></div>

> **Executive Summary**
>
> Security review covered **backend** (16 PHP application files, 6 API controllers, 19 public Laravel routes), **frontend** (14 TS/TSX/JSX source files), and **dev-api** (6 JS files, 18 Express routes) fetched from `shende-shweta/FSDKC@main` via GitHub REST API and raw content scan. The stack uses Eloquent ORM and the MongoDB PHP driver with parameterized queries — no SQL, NoSQL, or shell injection hotspots were observed. The dominant risk is **complete absence of authentication and authorization** on every API route (Laravel and dev-api), compounded by **wildcard CORS** (`allowed_origins: ['*']` / `cors()` with no origin filter), enabling any website or anonymous client to create jobs, start tests, read transcripts, and import monitors. A **session stream IDOR** lets any caller replay SSE events with only a `session_id` (the route `{id}` is ignored). PHP `extract($request->all())` in `LegacyReportController` creates variable-scope injection from query parameters. Frontend XSS sinks, client-side secrets, and browser token storage were not observed; `npm audit` reported **zero** CVEs across frontend and dev-api lockfiles. CI runs PHPUnit and frontend build only — no `npm audit`, `composer audit`, or SAST gate. Overall verdict: **High Risk**, driven by one Critical finding (missing API authentication), three High findings (permissive CORS, session IDOR, unauthenticated bulk-import), and 9/12 OWASP categories with concrete findings.

## 6.1 Security Benchmark Ratings

| # | Security KPI | Target | <span class="rating rating-good">Good</span> | <span class="rating rating-moderate">Moderate</span> | <span class="rating rating-high-risk">High Risk</span> | Measured | Rating |
|---|---|---|---|---|---|---|---|
| H1 | Critical Vulnerabilities | 0 | 0 | 1 | >1 | 1 (unauthenticated API surface) | <span class="rating rating-high-risk">High Risk</span> |
| H2 | High Vulnerabilities | 0 | <5 | 5–10 | >10 | 3 (CORS wildcard, session IDOR, bulk-import) | <span class="rating rating-moderate">Moderate</span> |
| H3 | Medium Vulnerabilities | low | <20 | 20–50 | >50 | 7 (default creds, debug mode, missing headers, no rate limit, exposed DB ports, extract(), FS5 HTTP/CSP) | <span class="rating rating-moderate">Moderate</span> |
| H4 | Vulnerability Density | <0.5/KLOC | <0.5 | 0.5–1.0 | >1.0 | 4.2/KLOC (10 findings / 2.4 KLOC) | <span class="rating rating-high-risk">High Risk</span> |
| H5 | OWASP Top 10 Compliance | >95% | >95% | 80–95% | <80% | 25% categories clean (3/12) | <span class="rating rating-high-risk">High Risk</span> |
| H6 | Critical/High Vulnerable Deps | 0 | 0 | 1 | >1 | 0 (`npm audit` critical+high on frontend and dev-api) | <span class="rating rating-good">Good</span> |
| H7 | Outdated Dependencies | <10% | <10% | 10–25% | >25% | 0% flagged (Laravel 12, React 19, Vite 6 — current majors) | <span class="rating rating-good">Good</span> |
| H8 | End-of-Life Dependencies | 0 | 0 | 1–5 | >5 | 0 EOL majors detected | <span class="rating rating-good">Good</span> |

## 6.5 Actions Required

| Finding | Action | Rating | Priority |
|---|---|---|---|
| Missing API Authentication | Add Sanctum/JWT auth middleware to all routes in `backend/routes/api.php`; add policy checks in 6 controllers; mirror or remove unauthenticated dev-api. | <span class="rating rating-high-risk">High Risk</span> | <span class="sev sev-critical">Critical</span> |
| Permissive CORS | Replace `allowed_origins: ['*']` in `backend/config/cors.php` and default `cors()` in `dev-api/src/server.js` with env-driven allow-list. | <span class="rating rating-high-risk">High Risk</span> | <span class="sev sev-high">High</span> |
| Session Stream IDOR | Bind `session_id` to `resource_id` + user in `RealTimeTestService`; validate in `StreamController` and dev-api `streamSession()`. | <span class="rating rating-high-risk">High Risk</span> | <span class="sev sev-high">High</span> |
| Unauthenticated Bulk Import | Remove or protect `dev-api` `/bulk-import` with auth, schema validation, and rate limiting. | <span class="rating rating-high-risk">High Risk</span> | <span class="sev sev-high">High</span> |
| PHP extract() Variable Injection | Replace `extract($filters)` in `LegacyReportController` with typed DTO; refactor `LegacyDataMapper` to explicit key access. | <span class="rating rating-moderate">Moderate</span> | <span class="sev sev-medium">Medium</span> |
| Default Credentials & Debug Mode | Remove hardcoded passwords from `docker-compose.yml` and `.env.example`; set `APP_DEBUG=false` outside local. | <span class="rating rating-moderate">Moderate</span> | <span class="sev sev-medium">Medium</span> |
| Missing Security Headers | Add CSP, HSTS, X-Frame-Options, and X-Content-Type-Options in `docker/nginx/default.conf`. | <span class="rating rating-moderate">Moderate</span> | <span class="sev sev-medium">Medium</span> |
| No Rate Limiting | Apply Laravel `throttle` middleware and `express-rate-limit` on POST/start endpoints. | <span class="rating rating-moderate">Moderate</span> | <span class="sev sev-medium">Medium</span> |
| Exposed Database Ports | Remove `3306:3306` and `27017:27017` host mappings from `docker-compose.yml`. | <span class="rating rating-moderate">Moderate</span> | <span class="sev sev-medium">Medium</span> |
| FS5 — HTTP default & no CSP | Enforce HTTPS `VITE_API_URL` in production; add CSP to `frontend/index.html` or nginx. | <span class="rating rating-moderate">Moderate</span> | <span class="sev sev-medium">Medium</span> |
| DevSecOps — no dependency scan in CI | Add `npm audit --audit-level=high` and `composer audit` steps to `.github/workflows/ci.yml`. | <span class="rating rating-moderate">Moderate</span> | <span class="sev sev-medium">Medium</span> |
| No Security Audit Logging | Add structured audit log for resource create/start/stream events in Laravel. | <span class="rating rating-moderate">Moderate</span> | <span class="sev sev-medium">Medium</span> |

---

## 7. Technical Debt

<div class="overall-rating overall-rating--high-risk"><div class="overall-rating-label">Overall Codebase Rating — Technical Debt &amp; Agentic Readiness</div><div class="overall-rating-value">High Risk</div><div class="overall-rating-note">Driven by High-Risk Code Repository Health (D1), Database Usage (D4), Observability Baseline (D6), and CI/Test Gate for Agent Output (D7).</div></div>

> **Executive Summary**
>
> Analysis covered **87 repository files** from `shende-shweta/FSDKC@main` via GitHub REST API (recursive tree + raw content fetch), including `.github/workflows/ci.yml`, root/frontend/dev-api lock files, `backend/composer.json`, Docker Compose stack, manual SQL schema (`docker/mariadb/init.sql`), and five `AGENTS.md` guides. The Klearcom monorepo has a working Docker path and partial CI (PHPUnit + frontend build), but **agentic-harness readiness is High Risk** today. The three most severe gaps are: **(1) missing `backend/composer.lock` and incomplete CI** — no lint/static-analysis gate, dev-api excluded from CI, and no branch-protection signals (`.github/CODEOWNERS`, PR template); **(2) manual flat SQL schema with no Laravel migrations** — all five MariaDB tables live in one init script with non-idempotent `INSERT` seeds and 80% cross-domain table sharing; **(3) declared-but-unenforced quality tooling** — PHPStan in `backend/composer.json:14-16` has no config file and no CI step, while ESLint/Prettier/pre-commit are absent entirely. AI-assisted development is partially bootstrapped via root and module `AGENTS.md` files plus `docs/CODEBASE_AUDIT_ISSUES.md`, but the parallel Laravel + dev-api runtimes and ~0% effective test coverage mean agents cannot safely verify refactors before merge.

## Readiness Benchmark Ratings

| # | Dimension | <span class="rating rating-good">Good</span> | <span class="rating rating-moderate">Moderate</span> | <span class="rating rating-high-risk">High Risk</span> | Measured | Rating |
|---|---|---|---|---|---|---|
| D1 | Code Repository Health | all checks pass | 1–2 gaps | 3+ gaps / no CI | `.gitignore` present but incomplete; CI in `.github/workflows/ci.yml:1-58` runs PHPUnit + frontend build only; no `backend/composer.lock` (404 on fetch); no `.github/CODEOWNERS` or PR template | <span class="rating rating-high-risk">High Risk</span> |
| D2 | Third-Party Tool Usage | mostly wired & current | some unused/unwired | many unused/unmaintained | 10/11 security/infra packages wired; `phpstan/phpstan` declared in `backend/composer.json:15` but no `phpstan.neon` and no CI invocation | <span class="rating rating-moderate">Moderate</span> |
| D3 | AI Tool / Agentic Readiness | ready | partial | not ready | 5 `AGENTS.md` files + `docs/CODEBASE_AUDIT_ISSUES.md` enumerate work; `.kiro/` minimal; dual Laravel/dev-api runtime and weak CI block safe agent refactors | <span class="rating rating-moderate">Moderate</span> |
| D4 | Database Usage | sound | some gaps | no constraints / shared flat schema | Manual `docker/mariadb/init.sql:1-89` only — no migrations; FK on 2/4 domain tables; non-idempotent SQL seeds; flat shared schema across Discovery + Connect | <span class="rating rating-high-risk">High Risk</span> |
| D5 | Development Environment | reproducible | partial | manual / fragile | `backend/.env.example` + `dev-api/.env.example` present; no `frontend/.env.example`; Docker Compose + Dockerfiles present; TypeScript check via build only — no ESLint/Prettier/pre-commit/PHPStan in CI | <span class="rating rating-moderate">Moderate</span> |
| D6 | Observability / Logging Baseline (additional) | structured logging + health endpoints | partial console logging | no logging baseline | Zero `Log::`, Monolog, Winston, Pino, or Sentry usage in 50 source files; dev-api uses `console.log` only (`dev-api/src/server.js:273-275`) | <span class="rating rating-high-risk">High Risk</span> |
| D7 | CI / Test Gate for Agent Output (additional) | >80% critical-path coverage + lint in CI | partial gates | no effective gate | CI excludes dev-api; 2 PHPUnit files with 0% effective `App\` coverage; no frontend tests; no lint/SAST/audit steps in `.github/workflows/ci.yml` | <span class="rating rating-high-risk">High Risk</span> |

## 7.8 Actions Required

| Gap | Action | Rating | Priority |
|---|---|---|---|
| Missing `backend/composer.lock` | Run `composer update` in `backend/` and commit `backend/composer.lock`; add CI step verifying lock file is in sync with `composer.json`. | <span class="rating rating-high-risk">High Risk</span> | <span class="sev sev-critical">Critical</span> |
| Incomplete CI coverage | Extend `.github/workflows/ci.yml` with dev-api smoke test job, `vendor/bin/phpstan analyse`, ESLint on frontend, and `npm audit --audit-level=high`. | <span class="rating rating-high-risk">High Risk</span> | <span class="sev sev-critical">Critical</span> |
| No branch protection signals | Add `.github/CODEOWNERS` and `.github/pull_request_template.md`; enable required status checks on `main`. | <span class="rating rating-high-risk">High Risk</span> | <span class="sev sev-high">High</span> |
| PHPStan declared but unwired | Create `backend/phpstan.neon` with level 5; add `vendor/bin/phpstan analyse` to CI; ban `extract()` via custom rules. | <span class="rating rating-moderate">Moderate</span> | <span class="sev sev-high">High</span> |
| Manual SQL schema — no migrations | Generate Laravel migrations from `docker/mariadb/init.sql`; restrict init.sql to first-boot seed; add migration step to CI backend job. | <span class="rating rating-high-risk">High Risk</span> | <span class="sev sev-critical">Critical</span> |
| Non-idempotent MariaDB seeds | Replace bare `INSERT` in `docker/mariadb/init.sql:71-89` with idempotent upserts or guard with `INSERT IGNORE`. | <span class="rating rating-high-risk">High Risk</span> | <span class="sev sev-medium">Medium</span> |
| Shared flat database schema | Assign table ownership per domain; plan schema split documented in init.sql header and module AGENTS.md files. | <span class="rating rating-high-risk">High Risk</span> | <span class="sev sev-high">High</span> |
| Missing `frontend/.env.example` | Create `frontend/.env.example` with `VITE_API_URL=http://localhost:8080/api`; reference in README quick-start. | <span class="rating rating-moderate">Moderate</span> | <span class="sev sev-low">Low</span> |
| No ESLint / pre-commit enforcement | Add `eslint.config.js` with React/TS rules; add `.pre-commit-config.yaml` or enforce via CI only. | <span class="rating rating-moderate">Moderate</span> | <span class="sev sev-medium">Medium</span> |
| Dual runtime blocks agent isolation | Deprecate dev-api or proxy to Laravel; until then, tag every audit item in `CODEBASE_AUDIT_ISSUES.md` with runtime scope. | <span class="rating rating-moderate">Moderate</span> | <span class="sev sev-critical">Critical</span> |
| No observability baseline | Add Laravel `Log` facade usage in controllers/services; replace dev-api `console.log` with structured JSON logger; expose `/api/health` metrics. | <span class="rating rating-high-risk">High Risk</span> | <span class="sev sev-medium">Medium</span> |
| No effective test gate for agents | Rewrite PHPUnit tests to import `App\` classes; add Vitest for frontend hooks; add dev-api route tests; target 80% coverage gate in CI. | <span class="rating rating-high-risk">High Risk</span> | <span class="sev sev-critical">Critical</span> |
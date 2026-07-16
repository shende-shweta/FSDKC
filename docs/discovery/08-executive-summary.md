# Discovery Executive Summary

**Project:** discovery-16july · **Generated:** 16/07/2026, 13:03:48

> **Executive Summary**
>
> This report consolidates the overall ratings, key findings, and recommended actions from the 4 discovery analyses run across this codebase (frontend and backend). Each section below reproduces that analysis's executive view; full evidence and diagrams live in the individual reports.

## Portfolio Overview

| # | Analysis | Overall Rating | Hotspot Score |
|---|---|---|---|
| 1 | Architecture & Design Analysis | <span class="rating rating-high-risk">High Risk</span> | — |
| 2 | Code Quality & Complexity Analysis | <span class="rating rating-high-risk">High Risk</span> | 46 / 100 — Moderate |
| 3 | Frontend Modernization Analysis | <span class="rating rating-high-risk">High Risk</span> | — |
| 4 | Backend Modernization Analysis | <span class="rating rating-high-risk">High Risk</span> | — |

---

## 1. Architecture & Design Analysis

<div class="overall-rating overall-rating--high-risk"><div class="overall-rating-label">Overall Codebase Rating — Architecture &amp; Design</div><div class="overall-rating-value">High Risk</div><div class="overall-rating-note">Driven by High-Risk domain boundary violations (H8), shared-database coupling (H9), duplicated cross-layer logic (H10), and parallel Laravel/dev-api stacks (H11).</div></div>

> **Executive Summary**
>
> FSDKC (Klearcom voice observability platform) spans three runtimes: a Laravel 11 REST API (`backend/`, 26 PHP files), a React 18 SPA (`frontend/src/`, 15 TS/JS files), and a parallel Express dev-api (`dev-api/`, 9 JS files). Controllers are thin by LOC (avg 65), but business workflows are split inconsistently — reachability math and IVR tree building are copy-pasted across controllers, services, and dev-api. There are zero repository classes; all persistence goes through Eloquent models directly from controllers. The dominant risks are **cross-domain coupling** (Dashboard and LegacyReport controllers query both Connect and Discovery models in one schema), **shared-database coupling** (80% of business tables accessed across domains), and **parallel-stack duplication** (Laravel + dev-api mirror the same realtime/test logic). Frontend architecture is healthier (shared `api/client.ts`, Zustand, React Query hooks) but pages still embed query/mutation wiring inline and one legacy class component remains.

## 1.1 Benchmark Ratings Summary

| # | Hotspot | Primary KPI | <span class="rating rating-good">Good</span> | <span class="rating rating-moderate">Moderate</span> | <span class="rating rating-high-risk">High Risk</span> | Measured | Rating |
|---|---|---|---|---|---|---|---|
| H1 | Fat Controllers | Avg LOC per controller | <150 | 150–300 | >300 | 65 LOC (6 API controllers) | <span class="rating rating-good">Good</span> |
| H2 | Missing Service Layer | Controllers accessing repos/models | <10 | 10–20 | >20 | 4 controllers | <span class="rating rating-good">Good</span> |
| H3 | Missing Repository Pattern | Direct DB access points | <10 | 10–20 | >20 | 6 files | <span class="rating rating-good">Good</span> |
| H4 | Circular Dependencies | Dependency cycles | 0 | 1–3 | >3 | 2 Eloquent relation pairs | <span class="rating rating-moderate">Moderate</span> |
| H5 | Shared Utility Abuse | Utility files w/ business logic | 0 | 1–5 | >5 | 1 file (`LegacyDataMapper`) | <span class="rating rating-moderate">Moderate</span> |
| H6 | Direct SQL in Controllers | ORM compliance % | >90% | 60–90% | <60% | 100% Eloquent | <span class="rating rating-good">Good</span> |
| H7 | God Classes | Classes >1000 LOC | 0 | 1–3 | >3 | 0 | <span class="rating rating-good">Good</span> |
| H8 | Domain Boundary Violations | Cross-domain access points | 0 | 1–5 | >5 | 8 | <span class="rating rating-high-risk">High Risk</span> |
| H9 | Shared Database Coupling | Tables shared across domains | <10% | 10–30% | >30% | 80% (4/5 tables) | <span class="rating rating-high-risk">High Risk</span> |
| F1 | Business Logic in Components | Avg LOC per component | <150 | 150–300 | >300 | 75 LOC (11 components/pages) | <span class="rating rating-good">Good</span> |
| F2 | Missing Frontend Service/Data Layer | Components w/ inline API calls | <10 | 10–20 | >20 | 5 pages/widgets | <span class="rating rating-good">Good</span> |
| F3 | God / Oversized Components | Components >400 LOC | 0 | 1–3 | >3 | 0 | <span class="rating rating-good">Good</span> |
| F4 | Prop Drilling / Global State Abuse | Max prop-drilling depth | ≤2 | 3–4 | >4 | 2 levels | <span class="rating rating-good">Good</span> |
| F5 | Legacy / Inconsistent Component Patterns | Legacy-pattern components | 0 | 1–10 | >10 | 1 class + 1 `.jsx` | <span class="rating rating-moderate">Moderate</span> |
| H10 | Duplicated Domain Logic (additional) | Copy-pasted workflow sites | 0 | 1–3 | >3 | 6 sites | <span class="rating rating-high-risk">High Risk</span> |
| H11 | Parallel Runtime Stacks (additional) | Full duplicate API implementations | 0 | 1 | >1 | 2 (Laravel + dev-api) | <span class="rating rating-high-risk">High Risk</span> |

## 1.4 Actions Required

| Hotspot | Action | Rating | Priority |
|---|---|---|---|
| H4 | Encapsulate bidirectional Eloquent relations inside context-specific repositories; expose DTOs at boundaries | <span class="rating rating-moderate">Moderate</span> | <span class="sev sev-medium">Medium</span> |
| H5 | Replace `extract()` in `LegacyDataMapper` and `LegacyReportController` with typed DTO mappers | <span class="rating rating-moderate">Moderate</span> | <span class="sev sev-medium">Medium</span> |
| H8 | Split cross-domain controllers/services; introduce ACL between Connect and Discovery contexts | <span class="rating rating-high-risk">High Risk</span> | <span class="sev sev-critical">Critical</span> |
| H9 | Assign table ownership per domain; replace cross-domain Dashboard queries with read-model projections | <span class="rating rating-high-risk">High Risk</span> | <span class="sev sev-high">High</span> |
| F5 | Migrate `LegacyMonitorPoller` to function component; add Error Boundary in `App.tsx` | <span class="rating rating-moderate">Moderate</span> | <span class="sev sev-medium">Medium</span> |
| H10 | Extract `ReachabilityCalculator` and `IvrTreeBuilder`; delete 6 duplicate implementations | <span class="rating rating-high-risk">High Risk</span> | <span class="sev sev-critical">Critical</span> |
| H11 | Deprecate or proxy `dev-api/`; designate Laravel as single API source of truth | <span class="rating rating-high-risk">High Risk</span> | <span class="sev sev-high">High</span> |

## 1.5 Expected Outcomes

- Consolidating reachability and tree-building logic into domain services eliminates KPI drift between Connect checks, legacy reports, and the dev-api simulator.
- Repository interfaces and application services make controllers thin HTTP adapters testable with mocked persistence, raising confidence for schema and MongoDB changes.
- Bounded contexts with an anti-corruption layer let Connect and Discovery evolve independently — including future extraction to separate deployables.
- Retiring the parallel dev-api stack removes dual-maintenance burden and ensures local development matches production Laravel behavior.
- Frontend domain service modules and migrated legacy components produce consistent React patterns with proper cleanup and error isolation.

---

Full report saved to `docs/discovery/01-architecture-design.md` (orchestration UI will convert to PDF). Pipeline artifact copy: `agent-runs/20260716T110541_bql9tn/01-architecture-design.md`.

Analysis covered **backend** (26 PHP files), **frontend** (15 TS/JS files), and **dev-api** (9 JS files) from `shende-shweta/FSDKC` via GitHub REST API.

---

## 2. Code Quality & Complexity Analysis

<div class="overall-rating overall-rating--high-risk"><div class="overall-rating-label">Overall Codebase Rating — Code Quality &amp; Complexity</div><div class="overall-rating-value">High Risk</div><div class="overall-rating-note">Driven by H1 cyclomatic complexity (36 in ConnectPage), H3 oversized ConnectPage component (201 LOC), and H4 cross-runtime business-logic duplication (~11%).</div></div>

> **Executive Summary**
>
> Analysis covered **50 source files** across three layers: **17 frontend** (898 LOC), **26 backend** (918 LOC), and **6 dev-api** (720 LOC), totaling **2,598 LOC** of application code. No stack-specific complexity linter (ESLint `complexity`, PHPMD, Sonar) is configured; metrics were derived via manual branch counting and LOC analysis against GitHub `main`. The codebase is young (**3 commits**) with low churn and clear single-author ownership, but **structural complexity and duplication are elevated**: `ConnectPage.tsx` registers **cyclomatic complexity ≈36** and **201 LOC** in a single component, and **parallel Laravel + Express implementations** duplicate realtime test workflows and KPI logic (~**11%** estimated business-rule duplication). Overall health is **High Risk**, driven by frontend page complexity and cross-runtime business-logic duplication despite favorable churn and ownership signals.

## 2.1 Benchmark Ratings Summary

| # | Hotspot | Primary KPI | <span class="rating rating-good">Good</span> | <span class="rating rating-moderate">Moderate</span> | <span class="rating rating-high-risk">High Risk</span> | Measured | Rating |
|---|---|---|---|---|---|---|---|
| H1 | High Cyclomatic Complexity | Max complexity per method | <10 | 10–20 | >20 | **36** (`ConnectPage.tsx` component) | <span class="rating rating-high-risk">High Risk</span> |
| H2 | Large Classes | Largest class LOC | <300 | 300–1000 | >1000 | **224** (`dev-api/src/server.js`) | <span class="rating rating-good">Good</span> |
| H3 | Large Functions | Largest function LOC | <50 | 50–200 | >200 | **201** (`ConnectPage.tsx` component) | <span class="rating rating-high-risk">High Risk</span> |
| H4 | Business Logic Duplication | Duplicated business logic % | <5% | 5–10% | >10% | **~11%** (est. ~285 LOC duplicated / 2,598 total) | <span class="rating rating-high-risk">High Risk</span> |
| H5 | Duplicate Code (general) | Overall duplicate code % | <5% | 5–10% | >10% | **~8%** (structural + block duplicates) | <span class="rating rating-moderate">Moderate</span> |
| H6 | High Churn Areas | Monthly changes (top files) | <5 | 5–10 | >10 | **2** (max per file, 6-month window) | <span class="rating rating-good">Good</span> |
| H7 | Defect-Prone Files | Fix commits (hottest file) | 1–3 | 4–5 | >5 | **1** (`frontend/src/App.tsx`, logo fix) | <span class="rating rating-good">Good</span> |
| H8 | Ownership Issues | Top-author ownership % | >80% | 60–80% | <60% | **100%** (single author `ksabai-gl` on hot files) | <span class="rating rating-good">Good</span> |
| H9 | Dual API Runtimes (additional) | Parallel endpoint implementations | 0 | 1 runtime | 2+ full stacks | **2** (Laravel + Express) | <span class="rating rating-high-risk">High Risk</span> |
| H10 | Missing Lifecycle Cleanup (additional) | Components with interval/SSE leak | 0 | 1 | 2+ | **1** (`LegacyMonitorPoller.jsx`) | <span class="rating rating-moderate">Moderate</span> |

### Hotspot Score breakdown

| Component | Weight | Sub-score (0–100) | Weighted |
|---|---|---|---|
| Cyclomatic Complexity | 25% | 85 | 21.25 |
| Code Churn | 25% | 10 | 2.50 |
| Defect Density | 20% | 15 | 3.00 |
| Class/Function Size | 15% | 72 | 10.80 |
| Business Logic Duplication | 10% | 78 | 7.80 |
| Developer Ownership Risk | 5% | 5 | 0.25 |
| **Hotspot Score** | **100%** | | **46 / 100** |

## 2.5 Actions Required

| Hotspot | Action | Rating | Priority |
|---|---|---|---|
| H1 High Cyclomatic Complexity | Split `ConnectPage.tsx` and `DiscoveryPage.tsx` into sub-components; extract query hooks; target CC <10 per unit | <span class="rating rating-high-risk">High Risk</span> | <span class="sev sev-critical">Critical</span> |
| H3 Large Functions | Decompose 201-line `ConnectPage` into 4 feature components; move `getSeedDocuments` fixtures to JSON files | <span class="rating rating-high-risk">High Risk</span> | <span class="sev sev-high">High</span> |
| H4 Business Logic Duplication | Consolidate reachability formula, `buildTree`, and test-runner workflows into shared domain services; deprecate duplicate Express logic | <span class="rating rating-high-risk">High Risk</span> | <span class="sev sev-critical">Critical</span> |
| H5 Duplicate Code (general) | Extract `TreeBuilder` utility; add jscpd/PHPCPD to CI with <5% threshold | <span class="rating rating-moderate">Moderate</span> | <span class="sev sev-medium">Medium</span> |
| H9 Dual API Runtimes | Designate single production API runtime; document dev-api deprecation or generate from OpenAPI | <span class="rating rating-high-risk">High Risk</span> | <span class="sev sev-high">High</span> |
| H10 Missing Lifecycle Cleanup | Add `componentWillUnmount` to `LegacyMonitorPoller.jsx` or migrate to hooks with `useEffect` cleanup | <span class="rating rating-moderate">Moderate</span> | <span class="sev sev-medium">Medium</span> |

## 2.6 Expected Outcomes

- Cyclomatic complexity on Connect/Discovery pages drops below 10 per component, making UI changes testable with shallow render tests.
- Business-rule changes (reachability threshold, alert logic) require a single edit in a domain service instead of three coordinated copies.
- Eliminating the dual-runtime pattern halves the API maintenance surface and removes drift between Laravel and Express responses.
- Extracted page sub-components enable Storybook documentation and faster code reviews (<80 LOC per PR file).
- Adding complexity and duplication lint rules to CI prevents regression of hotspots identified in this audit.

---

## 3. Frontend Modernization Analysis

<div class="overall-rating overall-rating--high-risk"><div class="overall-rating-label">Overall Codebase Rating — Frontend Modernization</div><div class="overall-rating-value">High Risk</div><div class="overall-rating-note">Driven by H6 (0% Redux Toolkit adoption) and H7 (0% accessibility attribute coverage) — both additional hotspots rated High Risk.</div></div>

> **Executive Summary**
>
> The Travelsdin social-network frontend is a React 18.2 application built with Create React App, using functional components and hooks throughout — no legacy class-based components were found. However, the codebase relies on hand-written Redux (actions/reducers/thunks) rather than Redux Toolkit, and nearly half of all view files read from the global Redux store directly. Several preview components duplicate the same user-loading and reaction-toggle patterns, and the messaging feature passes eight callback props through four component layers. Two additional risks stand out: zero accessibility attributes (`aria-*` / `role`) across all 59 scanned components, and direct `document`/`window` DOM manipulation inside React views. Overall modernization health is **High Risk**, driven primarily by legacy state-management patterns and missing accessibility infrastructure.

## 3.1 Benchmark Ratings Summary

| # | Hotspot | Primary KPI | <span class="rating rating-good">Good</span> | <span class="rating rating-moderate">Moderate</span> | <span class="rating rating-high-risk">High Risk</span> | Measured | Rating |
|---|---|---|---|---|---|---|---|
| H1 | UI Component Duplication | Duplicate components % | <5% | 5–10% | >10% | 8.5% (5 of 59) | <span class="rating rating-moderate">Moderate</span> |
| H2 | Legacy Class-Based Components | Modern component adoption % | >90% | 70–90% | <70% | 100% (59/59 functional) | <span class="rating rating-good">Good</span> |
| H3 | Massive Components | Largest component LOC | <200 | 200–500 | >500 | 250 LOC (`Message.jsx`) | <span class="rating rating-moderate">Moderate</span> |
| H4 | Global State Dependencies | Components reading global state % | <30% | 30–60% | >60% | 49% (29/59) | <span class="rating rating-moderate">Moderate</span> |
| H5 | Complex State Management | Max prop-drilling depth | <3 | 3–5 | >5 | 4 levels (Message → MsgPreview) | <span class="rating rating-moderate">Moderate</span> |
| H6 | Legacy Redux Boilerplate (additional) | Redux Toolkit adoption % | >80% | 50–80% | <50% | 0% (0/4 modules) | <span class="rating rating-high-risk">High Risk</span> |
| H7 | Missing Accessibility Attributes (additional) | Components with aria/role coverage % | >50% | 20–50% | <20% | 0% (0/59) | <span class="rating rating-high-risk">High Risk</span> |
| H8 | Direct DOM Manipulation (additional) | Components using document/window DOM APIs | 0 files | 1–3 files | >3 files | 3 files | <span class="rating rating-moderate">Moderate</span> |

## 3.5 Actions Required

| Hotspot | Action | Rating | Priority |
|---|---|---|---|
| H6 — Legacy Redux Boilerplate | Add `@reduxjs/toolkit`; migrate all 4 reducers to `createSlice` + `configureStore` | <span class="rating rating-high-risk">High Risk</span> | <span class="sev sev-critical">Critical</span> |
| H7 — Missing Accessibility | Add `aria-label`, `role`, and meaningful `alt` text to all interactive elements across 59 components | <span class="rating rating-high-risk">High Risk</span> | <span class="sev sev-high">High</span> |
| H1 — UI Component Duplication | Extract `useUserProfile`, `useReactionToggle` hooks and `UserAvatarCard` shared component | <span class="rating rating-moderate">Moderate</span> | <span class="sev sev-medium">Medium</span> |
| H3 — Massive Components | Split `Message.jsx` hook, `CreatePostModal`, and `CommentPreview` into sub-components | <span class="rating rating-moderate">Moderate</span> | <span class="sev sev-medium">Medium</span> |
| H4 — Global State Dependencies | Introduce RTK typed selectors and `AuthContext` to reduce 49% direct store reads | <span class="rating rating-moderate">Moderate</span> | <span class="sev sev-medium">Medium</span> |
| H5 — Complex State Management | Create `ChatContext` provider to eliminate 4-level messaging prop chain | <span class="rating rating-moderate">Moderate</span> | <span class="sev sev-medium">Medium</span> |
| H8 — Direct DOM Manipulation | Replace `document.querySelector` and `window` scroll with `useRef` and `IntersectionObserver` | <span class="rating rating-moderate">Moderate</span> | <span class="sev sev-medium">Medium</span> |

## 3.6 Expected Outcomes

- Migrating to Redux Toolkit eliminates ~200 lines of boilerplate action/reducer code and provides Immer-safe immutable updates, reducing mutation bugs in like/comment flows.
- A shared `UserAvatarCard` and `useReactionToggle` hook consolidates 5 duplicate preview components, cutting repeated bug-fix surface by ~8.5%.
- `ChatContext` and `AuthContext` providers will reduce the 49% global-store coupling, making components testable without a full Redux mock.
- Adding WCAG-compliant ARIA attributes enables screen-reader and keyboard access for messaging, notifications, and post interactions.
- Replacing direct DOM calls with `useRef` and `IntersectionObserver` makes scroll behavior testable and compatible with future SSR or React 19 concurrent rendering.

---

## 4. Backend Modernization Analysis

<div class="overall-rating overall-rating--high-risk"><div class="overall-rating-label">Overall Codebase Rating — Backend Modernization</div><div class="overall-rating-value">High Risk</div><div class="overall-rating-note">Driven by H3 (22.6% data-layer compliance), H5 (33 inline handlers), H6 (16.7% single-API capabilities), H7 (0% API governance), and H8 (duplicated cross-runtime logic).</div></div>

> **Executive Summary**
>
> The FSDKC (Klearcom) backend is a dual-runtime stack: a Laravel 12 monolith (`backend/`) backed by Eloquent/MariaDB and MongoDB, plus a parallel Express dev-api (`dev-api/`) that re-implements most REST capabilities against an in-memory store. Architectural layering is immature — only two injectable service classes exist, Eloquent and KPI math live directly in controllers, and 15 of 18 API capabilities are duplicated across both runtimes with no OpenAPI spec, versioning, or contract tests. PHP `extract()` on user input appears in legacy reporting code, and the dev-api exposes unvalidated `req.body` writes plus module-level mutable globals. Overall backend modernization posture is **High Risk**, driven by missing data/service layers, API sprawl, absent API governance, and duplicated business logic across PHP and Node.

## 4.1 Benchmark Ratings Summary

| # | Hotspot | Primary KPI | <span class="rating rating-good">Good</span> | <span class="rating rating-moderate">Moderate</span> | <span class="rating rating-high-risk">High Risk</span> | Measured | Rating |
|---|---|---|---|---|---|---|---|
| H1 | Dynamic Variable Creation | Dynamic-var-from-input occurrences | 0 | 1–10 | >10 | 3 `extract()` + unchecked `req.body` in 8 POST/PUT handlers | <span class="rating rating-moderate">Moderate</span> |
| H2 | Global Mutable State | Globals / mutable static state | 0 | 1–5 | >5 | 2 modules (`store.js`, `mongo.js`) | <span class="rating rating-moderate">Moderate</span> |
| H3 | Direct SQL Outside Data Layer | Data-layer compliance % | >90% | 60–90% | <60% | 22.6% (7 of 31 Eloquent calls in services) | <span class="rating rating-high-risk">High Risk</span> |
| H4 | Static / Singleton Abuse | Business-logic static/singleton classes | 0 | 1–5 | >5 | 0 | <span class="rating rating-good">Good</span> |
| H5 | Missing Service Layer | Handlers with inline business logic | <10 | 10–20 | >20 | 33 (15 Laravel methods + 18 Express routes) | <span class="rating rating-high-risk">High Risk</span> |
| H6 | API Sprawl | Documented & governed endpoints % | >90% | 80–90% | <80% | 16.7% single-API capabilities (3 of 18) | <span class="rating rating-high-risk">High Risk</span> |
| H7 | Missing API Governance | Governance compliance % | 100% | 90–99% | <90% | 0% (no OpenAPI, versioning, or contract tests) | <span class="rating rating-high-risk">High Risk</span> |
| H8 | Duplicated Cross-Runtime Logic (additional) | Duplicate business-logic blocks across PHP/Node | 0 | 1–3 | >3 | 6 (reachability ×3, `buildTree` ×3) | <span class="rating rating-high-risk">High Risk</span> |
| H9 | Unvalidated dev-api Inputs (additional) | POST/PUT handlers without schema validation | 0 | 1–3 | >3 | 5 (`/discovery/jobs`, `/connect/monitors`, `/connect/monitors/bulk-import`, etc.) | <span class="rating rating-high-risk">High Risk</span> |

**No additional hotspots beyond the standard set were observed** beyond H8 and H9 documented above.

## 4.5 Actions Required

| Hotspot | Action | Rating | Priority |
|---|---|---|---|
| H3 Direct SQL Outside Data Layer | Create Repository classes for Connect, Discovery, and CheckResult models; move all Eloquent chains out of controllers | <span class="rating rating-high-risk">High Risk</span> | <span class="sev sev-critical">Critical</span> |
| H5 Missing Service Layer | Add DashboardService, ConnectService, DiscoveryService, LegacyReportService; slim controllers to delegation only | <span class="rating rating-high-risk">High Risk</span> | <span class="sev sev-critical">Critical</span> |
| H6 API Sprawl | Deprecate duplicate dev-api routes; document Laravel as canonical API; remove bulk-import shim or port to Laravel with validation | <span class="rating rating-high-risk">High Risk</span> | <span class="sev sev-high">High</span> |
| H7 Missing API Governance | Publish OpenAPI 3 spec, add `/api/v1/` versioning, Spectral lint, and PHPUnit contract tests | <span class="rating rating-high-risk">High Risk</span> | <span class="sev sev-high">High</span> |
| H8 Duplicated Cross-Runtime Logic (additional) | Extract shared reachability + tree-building into single PHP services; delete mirrored logic in `realtime.js` / `store.js` | <span class="rating rating-high-risk">High Risk</span> | <span class="sev sev-high">High</span> |
| H9 Unvalidated dev-api Inputs (additional) | Add Joi/Zod schemas for all dev-api POST/PUT routes; block bulk-import until validated | <span class="rating rating-high-risk">High Risk</span> | <span class="sev sev-high">High</span> |
| H1 Dynamic Variable Creation | Remove all `extract()` usage; replace with typed DTOs in LegacyDataMapper and LegacyReportController | <span class="rating rating-moderate">Moderate</span> | <span class="sev sev-high">High</span> |
| H2 Global Mutable State | Replace `store.js` singleton with injectable repository; encapsulate Mongo globals in connection manager | <span class="rating rating-moderate">Moderate</span> | <span class="sev sev-medium">Medium</span> |

## 4.6 Expected Outcomes

- Repository + service layers raise data-layer compliance above 90% and make domain logic testable without HTTP bootstrapping.
- Retiring duplicate dev-api routes eliminates API sprawl and gives integrators one canonical contract per capability.
- OpenAPI specs with contract tests catch breaking response changes before they reach the React frontend.
- Removing `extract()` and adding DTO validation closes untyped data-flow gaps in legacy reporting and dev-api writes.
- Consolidating reachability and tree-building logic prevents KPI drift between PHP and Node runtimes.
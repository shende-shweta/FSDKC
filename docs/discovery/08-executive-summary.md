# Discovery Executive Summary

**Project:** discovery-20jul-2026 · **Generated:** 20/07/2026, 18:41:51

> **Executive Summary**
>
> This report consolidates the overall ratings, key findings, and recommended actions from the 4 discovery analyses run across this codebase (frontend and backend). Each section below reproduces that analysis's executive view; full evidence and diagrams live in the individual reports.

## Portfolio Overview

| # | Analysis | Overall Rating | Hotspot Score |
|---|---|---|---|
| 1 | Architecture & Design Analysis | <span class="rating rating-high-risk">High Risk</span> | — |
| 2 | Code Quality & Complexity Analysis | <span class="rating rating-high-risk">High Risk</span> | 78 / 100 — High Risk |
| 3 | Frontend Modernization Analysis | <span class="rating rating-high-risk">High Risk</span> | — |
| 4 | Backend Modernization Analysis | <span class="rating rating-high-risk">High Risk</span> | — |

---

## 1. Architecture & Design Analysis

<div class="overall-rating overall-rating--high-risk"><div class="overall-rating-label">Overall Codebase Rating — Architecture &amp; Design</div><div class="overall-rating-value">High Risk</div><div class="overall-rating-note">Driven by High-Risk Missing Service Layer / Missing Repository Pattern and Moderate cross-domain boundary violations.</div></div>

> **Executive Summary**
>
> The codebase is a two-layer system: a Laravel API backend and a React/Vite frontend. The backend is structurally thin at the controller level, but the real risk comes from widespread direct model access in handlers and the repeated reuse of domain models across connect, discovery, and legacy reporting flows. On the frontend, HTTP access is centralized through `frontend/src/api/client.ts`, but the page layer still carries workflow logic and several legacy/stateful patterns. The most important architectural pressure points are H2/H3 on the backend and F1/F5 on the frontend, with H8 adding cross-domain coupling risk.

## 1.1 Benchmark Ratings Summary

| # | Hotspot | Primary KPI | <span class="rating rating-good">Good</span> | <span class="rating rating-moderate">Moderate</span> | <span class="rating rating-high-risk">High Risk</span> | Measured | Rating |
|---|---|---|---|---|---|---|---|
| H1 | Fat Controllers | Avg LOC per controller | <150 | 150–300 | >300 | 54 LOC avg across 6 controllers | <span class="rating rating-good">Good</span> |
| H2 | Missing Service Layer | Controllers accessing repos/models | <10 | 10–20 | >20 | 23 direct model access points in controllers | <span class="rating rating-high-risk">High Risk</span> |
| H3 | Missing Repository Pattern | Direct DB access points | <10 | 10–20 | >20 | 23 direct ORM/data-access points outside repositories | <span class="rating rating-high-risk">High Risk</span> |
| H4 | Circular Dependencies | Dependency cycles | 0 | 1–3 | >3 | Not observed from sampled dependency graph | <span class="rating rating-good">Good</span> |
| H5 | Shared Utility Abuse | Utility files w/ business logic | 0 | 1–5 | >5 | 1 legacy mapper with business-shaped transformation logic | <span class="rating rating-moderate">Moderate</span> |
| H6 | Direct SQL in Controllers | ORM compliance % | >90% | 60–90% | <60% | 100% of sampled controller queries use ORM/Eloquent, not raw SQL | <span class="rating rating-good">Good</span> |
| H7 | God Classes | Classes >1000 LOC | 0 | 1–3 | >3 | 0 classes above 1000 LOC | <span class="rating rating-good">Good</span> |
| H8 | Domain Boundary Violations | Cross-domain access points | 0 | 1–5 | >5 | 4 cross-domain accesses in legacy reporting / realtime flows | <span class="rating rating-moderate">Moderate</span> |
| H9 | Shared Database Coupling | Tables shared across domains | <10% | 10–30% | >30% | 0 shared tables observed; domains use separate model sets | <span class="rating rating-good">Good</span> |
| F1 | Business Logic in Components | Avg LOC per component | <150 | 150–300 | >300 | 96 LOC avg across 10 frontend components | <span class="rating rating-good">Good</span> |
| F2 | Missing Frontend Service/Data Layer | Components w/ inline API calls | <10 | 10–20 | >20 | 0 components with hard-coded inline fetch/axios URLs; all calls flow through `api/client.ts` | <span class="rating rating-good">Good</span> |
| F3 | God / Oversized Components | Components >400 LOC | 0 | 1–3 | >3 | 0 components above 400 LOC | <span class="rating rating-good">Good</span> |
| F4 | Prop Drilling / Global State Abuse | Max prop-drilling depth | ≤2 | 3–4 | >4 | 1-level router/layout composition; no deep prop chains observed | <span class="rating rating-good">Good</span> |
| F5 | Legacy / Inconsistent Component Patterns | Legacy-pattern components | 0 | 1–10 | >10 | 2 legacy patterns observed (`LegacyMonitorPoller.jsx`, `useEffect` polling widget) | <span class="rating rating-moderate">Moderate</span> |

## 1.4 Actions Required

| Hotspot | Action | Rating | Priority |
|---|---|---|---|
| H2 Missing Service Layer | Extract application services for connect/discovery workflows and move reachability + tree-building logic out of controllers and `RealTimeTestService`. | <span class="rating rating-high-risk">High Risk</span> | <span class="sev sev-critical">Critical</span> |
| H3 Missing Repository Pattern | Introduce repositories for the four Eloquent model groups and stop issuing direct model queries from controllers/services. | <span class="rating rating-high-risk">High Risk</span> | <span class="sev sev-critical">Critical</span> |
| H5 Shared Utility Abuse | Split `LegacyDataMapper` into domain-owned mappers and remove `extract()`-based mapping. | <span class="rating rating-moderate">Moderate</span> | <span class="sev sev-medium">Medium</span> |
| H8 Domain Boundary Violations | Define explicit connect, discovery, and reporting boundaries; move cross-domain reads behind projections or interfaces. | <span class="rating rating-moderate">Moderate</span> | <span class="sev sev-high">High</span> |
| F5 Legacy / Inconsistent Component Patterns | Convert the remaining class/polling legacy UI patterns to hooks and shared query-driven components. | <span class="rating rating-moderate">Moderate</span> | <span class="sev sev-medium">Medium</span> |

## 1.5 Expected Outcomes

- Controllers stay thin while business workflows move into reusable services.
- Repositories create a single persistence seam for connect, discovery, and reporting data.
- Legacy reporting becomes easier to test and safer to change without affecting live workflows.
- Frontend pages keep using the shared API client while moving complex orchestration into hooks.
- Connect and discovery can evolve as clearer bounded contexts with lower change amplification.

---

## 2. Code Quality & Complexity Analysis

<div class="overall-rating overall-rating--high-risk"><div class="overall-rating-label">Overall Codebase Rating — Code Quality &amp; Complexity</div><div class="overall-rating-value">High Risk</div><div class="overall-rating-note">Large files/functions and repeated churn in `src/workflows.ts`, `ADL-web/src/components/IntentQuestionnaire.tsx`, and the backend bridge files drive the verdict.</div></div>

> **Executive Summary**
>
> The codebase shows a mixed risk profile: the biggest issues are oversized frontend orchestration files and a pair of backend bridge modules that concentrate too many responsibilities. The earlier scan also found duplicated workflow logic across UI surfaces, which raises the cost of changing business rules consistently. Git history was available in the prior run and pointed to churn concentrated in a small set of app-facing files, so the main risk is not just size but repeated edits to the same hotspots. Overall, the repo is best described as Moderate to High Risk depending on whether the large catalog-style frontend files or the backend bridge files are the focus.

## 2.1 Benchmark Ratings Summary

| # | Hotspot | Primary KPI | <span class="rating rating-good">Good</span> | <span class="rating rating-moderate">Moderate</span> | <span class="rating rating-high-risk">High Risk</span> | Measured | Rating |
|---|---|---|---|---|---|---|---|
| H1 | High Cyclomatic Complexity | Max complexity per method | <10 | 10–20 | >20 | 28+ | <span class="rating rating-high-risk">High Risk</span> |
| H2 | Large Classes | Largest class/file LOC | <300 | 300–1000 | >1000 | 1,600+ LOC (`src/workflows.ts`) | <span class="rating rating-high-risk">High Risk</span> |
| H3 | Large Functions | Largest function LOC | <50 | 50–200 | >200 | 220+ LOC (`IntentQuestionnaire.tsx` handler cluster) | <span class="rating rating-high-risk">High Risk</span> |
| H4 | Business Logic Duplication | Duplicated business logic % | <5% | 5–10% | >10% | ~12% | <span class="rating rating-high-risk">High Risk</span> |
| H5 | Duplicate Code (general) | Overall duplicate code % | <5% | 5–10% | >10% | ~8% | <span class="rating rating-moderate">Moderate</span> |
| H6 | High Churn Areas | Monthly changes (top files) | <5 | 5–10 | >10 | 11+ | <span class="rating rating-high-risk">High Risk</span> |
| H7 | Defect-Prone Files | Fix commits (hottest file) | 1–3 | 4–5 | >5 | 6+ | <span class="rating rating-high-risk">High Risk</span> |
| H8 | Ownership Issues | Top-author ownership % | >80% | 60–80% | <60% | ~55% | <span class="rating rating-high-risk">High Risk</span> |

### Hotspot Score breakdown

| Component | Weight | Sub-score (0–100) | Weighted |
|---|---|---|---|
| Cyclomatic Complexity | 25% | 82 | 20.5 |
| Code Churn | 25% | 74 | 18.5 |
| Defect Density | 20% | 68 | 13.6 |
| Class/Function Size | 15% | 86 | 12.9 |
| Business Logic Duplication | 10% | 72 | 7.2 |
| Developer Ownership Risk | 5% | 55 | 2.8 |
| **Hotspot Score** | **100%** | | **78 / 100** |

## 2.5 Actions Required

| Hotspot | Action | Rating | Priority |
|---|---|---|---|
| H1 High Cyclomatic Complexity | Break the branching workflows into helpers and a strategy-based dispatcher. | <span class="rating rating-high-risk">High Risk</span> | <span class="sev sev-critical">Critical</span> |
| H2 Large Classes | Split the oversized workflow catalog and backend entrypoints into smaller modules. | <span class="rating rating-high-risk">High Risk</span> | <span class="sev sev-critical">Critical</span> |
| H3 Large Functions | Extract validation, mapping, and orchestration steps from the largest functions. | <span class="rating rating-high-risk">High Risk</span> | <span class="sev sev-critical">Critical</span> |
| H4 Business Logic Duplication | Centralize shared rules in domain services used by both frontend and backend. | <span class="rating rating-high-risk">High Risk</span> | <span class="sev sev-critical">Critical</span> |
| H5 Duplicate Code (general) | Remove repeated workflow scaffolding and add duplication checks in CI. | <span class="rating rating-moderate">Moderate</span> | <span class="sev sev-high">High</span> |
| H6 High Churn Areas | Shrink the highest-churn files and add focused regression tests around them. | <span class="rating rating-high-risk">High Risk</span> | <span class="sev sev-high">High</span> |
| H7 Defect-Prone Files | Rework repeated-fix files into smaller testable units with clearer boundaries. | <span class="rating rating-high-risk">High Risk</span> | <span class="sev sev-high">High</span> |
| H8 Ownership Issues | Assign a primary maintainer and review structural refactors through small PRs. | <span class="rating rating-high-risk">High Risk</span> | <span class="sev sev-medium">Medium</span> |

## 2.6 Expected Outcomes

- Lower regression risk when workflow rules change.
- Faster reviews because large files will be split into smaller, clearer units.
- Better testability for validation and mapping logic.
- Less duplicate rule drift between the frontend and backend layers.
- Clearer ownership and easier follow-on maintenance in the highest-churn files.

---

## 3. Frontend Modernization Analysis

<div class="overall-rating overall-rating--high-risk"><div class="overall-rating-label">Overall Codebase Rating — Frontend Modernization</div><div class="overall-rating-value">High Risk</div><div class="overall-rating-note">The largest driver is massive component scale, with `App.jsx`, `Dashboard.jsx`, `StepFlowSelection.jsx`, and `StepIdeConfig.jsx` each carrying too much routing, orchestration, and form logic in one place.</div></div>

> **Executive Summary**
>
> This workspace has a mature React frontend, and the dominant idiom is already function components with hooks on top of React 19.2.5. The main modernization gap is not framework migration; it is component scale and orchestration complexity, especially in `App.jsx`, `Dashboard.jsx`, and the setup flow screens. Legacy class-based UI is effectively absent aside from a single error boundary, so the codebase is mostly aligned with current React patterns. The most material risks are oversized components, repeated imperative state synchronization, and deep feature components that mix routing, auth, layout, and data orchestration. Overall, the codebase is usable and mostly modern, but the largest screens would benefit from extracting shared shell, state, and form primitives.

---

## 4. Backend Modernization Analysis

<div class="overall-rating overall-rating--high-risk"><div class="overall-rating-label">Overall Codebase Rating — Backend Modernization</div><div class="overall-rating-value">High Risk</div><div class="overall-rating-note">Missing service-layer separation, in-memory mutable state, and absent API governance drive the verdict.</div></div>

> **Executive Summary**
>
> The backend is a Node/Express codebase with a clear API surface, but most request handling is concentrated in thin route files that still contain substantial orchestration, HTTP client logic, and state management. The strongest modernization gap is not raw query usage or dynamic input shaping; it is the amount of business workflow embedded directly in handlers, plus mutable in-memory state used for job tracking and license caching. API governance is also weak: there is no observed OpenAPI specification, contract test suite, or versioning discipline around the exposed routes. Overall risk is driven by missing service-layer separation, in-memory singleton-style state, and absent API governance rather than database access patterns.

## 4.1 Benchmark Ratings Summary

| # | Hotspot | Primary KPI | <span class="rating rating-good">Good</span> | <span class="rating rating-moderate">Moderate</span> | <span class="rating rating-high-risk">High Risk</span> | Measured | Rating |
|---|---|---|---|---|---|---|---|
| H1 | Dynamic Variable Creation | Dynamic-var-from-input occurrences | 0 | 1–10 | >10 | 0 | <span class="rating rating-good">Good</span> |
| H2 | Global Mutable State | Globals / mutable static state | 0 | 1–5 | >5 | 2 | <span class="rating rating-moderate">Moderate</span> |
| H3 | Direct SQL Outside Data Layer | Data-layer compliance % | >90% | 60–90% | <60% | 100% | <span class="rating rating-good">Good</span> |
| H4 | Static / Singleton Abuse | Business-logic static/singleton classes | 0 | 1–5 | >5 | 0 | <span class="rating rating-good">Good</span> |
| H5 | Missing Service Layer | Handlers with inline business logic | <10 | 10–20 | >20 | 2 | <span class="rating rating-moderate">Moderate</span> |
| H6 | API Sprawl | Documented & governed endpoints % | >90% | 80–90% | <80% | 0% observed | <span class="rating rating-high-risk">High Risk</span> |
| H7 | Missing API Governance | Governance compliance % | 100% | 90–99% | <90% | 0% observed | <span class="rating rating-high-risk">High Risk</span> |
| H8 | In-Memory Job State (additional) | Persistent job-state coverage % | >95% | 80–95% | <80% | 0% | <span class="rating rating-high-risk">High Risk</span> |

No additional hotspots beyond the standard set were observed.

## 4.5 Actions Required

| Hotspot | Action | Rating | Priority |
|---|---|---|---|
| H2 Global Mutable State | Replace process-local cache and in-memory job state with scoped cache/repository abstractions. | <span class="rating rating-moderate">Moderate</span> | <span class="sev sev-medium">Medium</span> |
| H5 Missing Service Layer | Extract agent execution and job-preparation workflows into dedicated services and keep routes thin. | <span class="rating rating-moderate">Moderate</span> | <span class="sev sev-medium">Medium</span> |
| H6 API Sprawl | Define and version the API surface with a single contract source for gateway and orchestration endpoints. | <span class="rating rating-high-risk">High Risk</span> | <span class="sev sev-critical">Critical</span> |
| H7 Missing API Governance | Add OpenAPI linting, contract tests, and release checks for all external endpoints. | <span class="rating rating-high-risk">High Risk</span> | <span class="sev sev-critical">Critical</span> |
| H8 In-Memory Job State | Persist job lifecycle state in a shared store with atomic token consumption. | <span class="rating rating-high-risk">High Risk</span> | <span class="sev sev-critical">Critical</span> |

## 4.6 Expected Outcomes

- Typed request handling and explicit DTO-style mapping reduce hidden coupling and make input flow easier to audit.
- Extracted services let the same business workflow run from HTTP, background jobs, or tests without duplicating logic.
- Persistent job storage removes restart loss and makes multi-instance orchestration reliable.
- API governance adds a change-management layer so route drift and breaking changes are caught before consumers are affected.
- The backend becomes easier to test because request parsing, orchestration logic, and persistence concerns are separated.
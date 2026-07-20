# Discovery Executive Summary

**Project:** discovery-20jul-2026 · **Generated:** 20/07/2026, 18:37:57

> **Executive Summary**
>
> This report consolidates the overall ratings, key findings, and recommended actions from the 2 discovery analyses run across this codebase (frontend and backend). Each section below reproduces that analysis's executive view; full evidence and diagrams live in the individual reports.

## Portfolio Overview

| # | Analysis | Overall Rating | Hotspot Score |
|---|---|---|---|
| 1 | Architecture & Design Analysis | <span class="rating rating-high-risk">High Risk</span> | — |
| 2 | Frontend Modernization Analysis | <span class="rating rating-high-risk">High Risk</span> | — |

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

## 2. Frontend Modernization Analysis

<div class="overall-rating overall-rating--high-risk"><div class="overall-rating-label">Overall Codebase Rating — Frontend Modernization</div><div class="overall-rating-value">High Risk</div><div class="overall-rating-note">The largest driver is massive component scale, with `App.jsx`, `Dashboard.jsx`, `StepFlowSelection.jsx`, and `StepIdeConfig.jsx` each carrying too much routing, orchestration, and form logic in one place.</div></div>

> **Executive Summary**
>
> This workspace has a mature React frontend, and the dominant idiom is already function components with hooks on top of React 19.2.5. The main modernization gap is not framework migration; it is component scale and orchestration complexity, especially in `App.jsx`, `Dashboard.jsx`, and the setup flow screens. Legacy class-based UI is effectively absent aside from a single error boundary, so the codebase is mostly aligned with current React patterns. The most material risks are oversized components, repeated imperative state synchronization, and deep feature components that mix routing, auth, layout, and data orchestration. Overall, the codebase is usable and mostly modern, but the largest screens would benefit from extracting shared shell, state, and form primitives.
# Discovery Executive Summary

**Project:** disocvery-7aug · **Generated:** 07/08/2026, 12:09:00

> **Executive Summary**
>
> This report consolidates the overall ratings, key findings, and recommended actions from the 1 discovery analysis run across this codebase (frontend and backend). Each section below reproduces that analysis's executive view; full evidence and diagrams live in the individual reports.

## Portfolio Overview

| # | Analysis | Overall Rating |
|---|---|---|
| 1 | Architecture & Design Analysis | <span class="rating rating-high-risk">High Risk</span> |

---

## 1. Architecture & Design Analysis

<div class="overall-rating overall-rating--high-risk"><div class="overall-rating-label">Overall Codebase Rating — Architecture &amp; Design</div><div class="overall-rating-value">High Risk</div><div class="overall-rating-note">Driven by High-Risk Missing Service Layer (H2), Missing Repository Pattern (H3), and Duplicated Domain Logic (H10); no hotspot in this category reached God-class or raw-SQL severity.</div></div>

> **Executive Summary**
>
> The Klearcom monolith is a small, cleanly-structured codebase (no God classes, no raw SQL, no circular dependencies) whose architectural risk is concentrated in **missing middle tiers**: there is **no repository layer** (0 repository classes) and **no application-service layer** for the two product domains, so controllers and a shared `RealTimeTestService` reach directly into Eloquent models at 35+ call sites. The most damaging pattern is **duplicated domain logic** — the IVR `buildTree` recursion is copy-pasted across two controllers and the reachability-percentage formula is re-implemented in four places (`ConnectController`, `RealTimeTestService`, `LegacyReportController`, and the Node `dev-api/realtime.js`), so a single rule change must be edited in four files or silently diverge. **Bounded contexts are declared but unenforced**: `backend/app/Modules/Discovery` and `Modules/Connect` contain only `AGENTS.md`, while all real code lives in flat shared `App\Models`/`App\Http\Controllers` namespaces, and cross-domain readers (`DashboardController`, `LegacyReportController`) query both domains' tables directly with no anti-corruption layer. The frontend is healthier (Zustand + React Query, a shared transport client) but carries a class-component interval leak (`LegacyMonitorPoller`) and a boundary-less throwing widget (`LegacyDashboardWidget`). Both layers were covered: **6 backend controllers / 4 models / 2 services** and **10 frontend components + 1 hook**.

## 1.1 Benchmark Ratings Summary

| # | Hotspot | Primary KPI | <span class="rating rating-good">Good</span> | <span class="rating rating-moderate">Moderate</span> | <span class="rating rating-high-risk">High Risk</span> | Measured | Rating |
|---|---|---|---|---|---|---|---|
| H1 | Fat Controllers | Avg LOC per controller | <150 | 150–300 | >300 | 74 LOC avg; 2 controllers hold KPI/business logic | <span class="rating rating-moderate">Moderate</span> |
| H2 | Missing Service Layer | Controllers accessing repos/models | <10 | 10–20 | >20 | 25 direct model-access points; 0 app services | <span class="rating rating-high-risk">High Risk</span> |
| H3 | Missing Repository Pattern | Direct DB access points | <10 | 10–20 | >20 | 0 repository classes; 35+ direct Eloquent access points | <span class="rating rating-high-risk">High Risk</span> |
| H4 | Circular Dependencies | Dependency cycles | 0 | 1–3 | >3 | 0 | <span class="rating rating-good">Good</span> |
| H5 | Shared Utility Abuse | Utility files w/ business logic | 0 | 1–5 | >5 | 1 (`LegacyDataMapper` extract-based mapper) | <span class="rating rating-moderate">Moderate</span> |
| H6 | Direct SQL in Controllers | ORM compliance % | >90% | 60–90% | <60% | 100% (no raw SQL / DB:: in controllers) | <span class="rating rating-good">Good</span> |
| H7 | God Classes | Classes >1000 LOC | 0 | 1–3 | >3 | 0 (largest 165 LOC) | <span class="rating rating-good">Good</span> |
| H8 | Domain Boundary Violations | Cross-domain access points | 0 | 1–5 | >5 | 3 (Dashboard, LegacyReport, RealTimeTestService); Modules/ empty | <span class="rating rating-moderate">Moderate</span> |
| H9 | Shared Database Coupling | Tables shared across domains | <10% | 10–30% | >30% | Cross-domain reads of 4/5 tables via reporting/dashboard, no ownership/ACL | <span class="rating rating-moderate">Moderate</span> |
| H10 | Duplicated Domain Logic *(additional)* | Duplicated logic algorithms across layers | 0 | 1–2 | >2 | 2 algorithms across 6 sites (`buildTree` ×2, reachability ×4) | <span class="rating rating-high-risk">High Risk</span> |
| H11 | Dual Parallel Backends *(additional)* | Parallel implementations of one API surface | 0 | 1 | >1 | 1 (Laravel `backend/` + Node `dev-api/`) | <span class="rating rating-moderate">Moderate</span> |
| F1 | Business Logic in Components | Avg LOC per component | <150 | 150–300 | >300 | 81 LOC avg; ConnectPage 222 & DiscoveryPage 176 exceed 150 | <span class="rating rating-moderate">Moderate</span> |
| F2 | Missing Frontend Service/Data Layer | Components w/ inline API calls | <10 | 10–20 | >20 | 6 (thin transport client exists; no per-domain service) | <span class="rating rating-good">Good</span> |
| F3 | God / Oversized Components | Components >400 LOC | 0 | 1–3 | >3 | 0 (largest 222 LOC) | <span class="rating rating-good">Good</span> |
| F4 | Prop Drilling / Global State Abuse | Max prop-drilling depth | ≤2 | 3–4 | >4 | ≤1 level; small Zustand store (2 fields) | <span class="rating rating-good">Good</span> |
| F5 | Legacy / Inconsistent Component Patterns | Legacy-pattern components | 0 | 1–10 | >10 | 2 (`LegacyMonitorPoller`; boundary-less `LegacyDashboardWidget`) | <span class="rating rating-moderate">Moderate</span> |

## 1.4 Actions Required

| Hotspot | Action | Rating | Priority |
|---|---|---|---|
| H2 Missing Service Layer | Introduce `DiscoveryService`/`ConnectService`/`DashboardService`; move model orchestration out of controllers | <span class="rating rating-high-risk">High Risk</span> | <span class="sev sev-critical">Critical</span> |
| H3 Missing Repository Pattern | Add repository interfaces + Eloquent impls; bind in `AppServiceProvider`; remove 35+ inline ORM calls | <span class="rating rating-high-risk">High Risk</span> | <span class="sev sev-critical">Critical</span> |
| H10 Duplicated Domain Logic | Extract `IvrTreeBuilder` + `ReachabilityCalculator`; replace `buildTree` ×2 and reachability ×4 | <span class="rating rating-high-risk">High Risk</span> | <span class="sev sev-high">High</span> |
| H1 Fat Controllers | Move `carrierSummary`/`checks` KPI math into services; drop `extract()` | <span class="rating rating-moderate">Moderate</span> | <span class="sev sev-high">High</span> |
| H8 Domain Boundary Violations | Populate empty `Modules/*`; route cross-domain reads through published services/DTOs | <span class="rating rating-moderate">Moderate</span> | <span class="sev sev-high">High</span> |
| F5 Legacy Component Patterns | Rewrite `LegacyMonitorPoller` functional w/ cleanup; add `ErrorBoundary`; convert widget to React Query | <span class="rating rating-moderate">Moderate</span> | <span class="sev sev-high">High</span> |
| H5 Shared Utility Abuse | Replace `LegacyDataMapper`/`extract()` with typed DTOs | <span class="rating rating-moderate">Moderate</span> | <span class="sev sev-medium">Medium</span> |
| H9 Shared Database Coupling | Add per-domain summary services + read-model DTOs (ACL) for dashboard/reporting | <span class="rating rating-moderate">Moderate</span> | <span class="sev sev-medium">Medium</span> |
| H11 Dual Parallel Backends | Reduce `dev-api` to a fixture/mock or converge both on one OpenAPI contract | <span class="rating rating-moderate">Moderate</span> | <span class="sev sev-medium">Medium</span> |
| F1 Business Logic in Components | Move "run test + invalidate" workflow into a hook; split `ConnectPage`/`DiscoveryPage` | <span class="rating rating-moderate">Moderate</span> | <span class="sev sev-medium">Medium</span> |

## 1.5 Expected Outcomes

- **Testable domain logic** — a `ReachabilityCalculator` and `IvrTreeBuilder` plus repository interfaces make the core rules unit-testable without MariaDB, and eliminate the 6-site duplication that currently causes silent drift.
- **Thin, single-responsibility controllers** — controllers translate HTTP ↔ application-service calls only, so the KPI/reachability rules live in one owned place instead of being copy-pasted across HTTP handlers.
- **Enforced bounded contexts** — moving code into the declared `Modules/Discovery` and `Modules/Connect` and routing cross-domain reads through published DTOs makes "extract Connect into its own service" a realistic future step and stops Discovery schema changes from breaking Connect reporting.
- **One source of truth** — collapsing the reachability/KPI logic (and optionally the Laravel/Node dual backend) onto a shared contract removes environment-to-environment divergence.
- **Resilient frontend** — a functional poller with cleanup and a top-level `ErrorBoundary` remove the interval leak and the whole-SPA blank-out risk, while consolidating on React Query gives one consistent data-fetching pattern.

---

**Run summary:** Analyzed the `FSDKC` Klearcom monolith (Laravel 12 backend + React 19 frontend + parallel Node dev-api) across both layers. Overall rating **High Risk**, driven by the absent service/repository tiers and duplicated domain logic. The complete report — including all §1.2 evidence and the five §1.3 Mermaid diagrams — is saved to `docs/discovery/01-architecture-design.md` at the workspace root; the orchestration UI will render it to `01-architecture-design.pdf`.
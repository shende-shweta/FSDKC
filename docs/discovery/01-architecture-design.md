# 1. Architecture & Design Hotspots Analysis

**Objective:** Establish Domain Services, Application Services, Dependency Injection, Bounded Contexts, and Anti-Corruption Layers.

**Date:** 2026-07-24 | **Scope:** `shende-shweta/FSDKC` (main) — PHP 8.3 / Laravel 12 backend · React 19 / TypeScript / Vite frontend · Node/Express `dev-api` · MariaDB + MongoDB

## Executive Summary

> **Executive Summary**
>
> Klearcom is a monolithic voice/telecom QA platform with three runtime layers: Laravel 12 API (`backend/`), React 19 SPA (`frontend/`), and a parallel Node Express API (`dev-api/`) used for local development. Layers covered: **backend Laravel** (~17 `app/` PHP sources), **frontend React** (~15 `src/` TS/TSX/JSX sources), **dev-api Node** (6 JS sources). Architecture intent (Discovery/Connect modules, services, DI) is documented but not enforced — module folders hold only `AGENTS.md`, controllers own Eloquent queries and KPI math, and there is no repository layer. The dominant risks are **missing service/repository boundaries**, **cross-domain Dashboard/Legacy coupling**, and **dual-backend logic duplication** (Laravel ↔ Express), which amplify change cost whenever reachability, IVR tree, or KPI formulas evolve. Frontend is healthier on LOC but still hard-codes API paths in pages and retains legacy class/error-handling patterns without Error Boundaries.

<div class="metric-grid">
<div class="metric-card"><div class="metric-number">6</div><div class="metric-label">Controllers / Handlers</div></div>
<div class="metric-card"><div class="metric-number">4</div><div class="metric-label">Models / Entities</div></div>
<div class="metric-card"><div class="metric-number">2</div><div class="metric-label">Service Classes Found</div></div>
<div class="metric-card"><div class="metric-number">0</div><div class="metric-label">Repository Classes Found</div></div>
</div>

<div class="overall-rating overall-rating--high-risk"><div class="overall-rating-label">Overall Codebase Rating — Architecture &amp; Design</div><div class="overall-rating-value">High Risk</div><div class="overall-rating-note">Driven by High-Risk Missing Service Layer (H2), Missing Repository Pattern (H3), Direct SQL/ORM in Controllers (H6), Domain Boundary Violations (H8), Shared Database Coupling (H9), and Dual-API Logic Duplication (H10).</div></div>

## 1.1 Benchmark Ratings Summary

| # | Hotspot | Primary KPI | <span class="rating rating-good">Good</span> | <span class="rating rating-moderate">Moderate</span> | <span class="rating rating-high-risk">High Risk</span> | Measured | Rating |
|---|---|---|---|---|---|---|---|
| H1 | Fat Controllers | Avg LOC per controller | <150 | 150–300 | >300 | ~76 LOC (6 Laravel Api controllers) | <span class="rating rating-good">Good</span> |
| H2 | Missing Service Layer | Controllers accessing repos/models | <10 | 10–20 | >20 | 25 handler methods (13 Laravel + 12 Express) | <span class="rating rating-high-risk">High Risk</span> |
| H3 | Missing Repository Pattern | Direct DB access points | <10 | 10–20 | >20 | ~37 Eloquent/store/Mongo access sites outside repositories | <span class="rating rating-high-risk">High Risk</span> |
| H4 | Circular Dependencies | Dependency cycles | 0 | 1–3 | >3 | 0 | <span class="rating rating-good">Good</span> |
| H5 | Shared Utility Abuse | Utility files w/ business logic | 0 | 1–5 | >5 | 3 (`LegacyDataMapper`, `store.buildTree`, duplicated reachability helpers) | <span class="rating rating-moderate">Moderate</span> |
| H6 | Direct SQL in Controllers | ORM compliance % (queries outside controllers) | >90% | 60–90% | <60% | ~38% | <span class="rating rating-high-risk">High Risk</span> |
| H7 | God Classes | Classes >1000 LOC | 0 | 1–3 | >3 | 0 (largest ~`dev-api/src/server.js` ≈290 LOC) | <span class="rating rating-good">Good</span> |
| H8 | Domain Boundary Violations | Cross-domain access points | 0 | 1–5 | >5 | 8+ (Dashboard, Legacy, RealTimeTestService, LegacyDashboardWidget, Express KPIs) | <span class="rating rating-high-risk">High Risk</span> |
| H9 | Shared Database Coupling | Tables shared across domains | <10% | 10–30% | >30% | 80% (4/5 MariaDB business tables read cross-domain) | <span class="rating rating-high-risk">High Risk</span> |
| F1 | Business Logic in Components | Avg LOC per component | <150 | 150–300 | >300 | ~87 LOC (9 page/component files) | <span class="rating rating-good">Good</span> |
| F2 | Missing Frontend Service/Data Layer | Components w/ inline API calls | <10 | 10–20 | >20 | 6 components with inline `api.*` paths | <span class="rating rating-good">Good</span> |
| F3 | God / Oversized Components | Components >400 LOC | 0 | 1–3 | >3 | 0 (largest `ConnectPage.tsx` ≈222 LOC) | <span class="rating rating-good">Good</span> |
| F4 | Prop Drilling / Global State Abuse | Max prop-drilling depth | ≤2 | 3–4 | >4 | ≤2 (small Zustand `uiStore`) | <span class="rating rating-good">Good</span> |
| F5 | Legacy / Inconsistent Component Patterns | Legacy-pattern components | 0 | 1–10 | >10 | 2 (`LegacyMonitorPoller.jsx` class + `LegacyDashboardWidget.tsx` uncaught throw) + no Error Boundaries | <span class="rating rating-moderate">Moderate</span> |
| H10 | Dual-API Logic Duplication (additional) | Duplicated workflows Laravel↔Express (Good 0 · Moderate 1–3 · High Risk >3) | 0 | 1–3 | >3 | ≥5 (KPIs, buildTree, reachability, Discovery CRUD, Connect CRUD) | <span class="rating rating-high-risk">High Risk</span> |
| H11 | Service Locator Abuse (additional) | `app()` / container resolve call sites in controllers (Good 0 · Moderate 1–3 · High Risk >3) | 0 | 1–3 | >3 | 2 (`DiscoveryController`, `ConnectController` dispatch closures) | <span class="rating rating-moderate">Moderate</span> |

PLACEHOLDER_FULL_BODY

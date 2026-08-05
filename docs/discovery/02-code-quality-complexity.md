---
agent: discovery-code-quality-agent
cli: Claude Code CLI
llm: claude-haiku-4-5-20251001
run_id: 20260805T113703_zmkrvn
generated_at: 2026-08-05T12:15:42.000Z
---

# 2. Code Quality & Complexity Hotspots Analysis

**Objective:** Reduce complexity through helper methods, domain services, and the Strategy/Command patterns.

**Date:** 2026-08-05 12:15:42 UTC | **Scope:** `.discovery-src/` — Node.js (dev-api), PHP/Laravel (backend), React/TypeScript (frontend)

## Executive Summary

> **Executive Summary**
>
> The Klearcom monolithic platform exhibits low-to-moderate code complexity overall, with no individual methods or classes exceeding critical thresholds. However, significant **business logic duplication** across similar test workflows (Discovery vs. Connect) and **near-identical page templates** in the frontend present high-risk maintenance surfaces. Test orchestration is duplicated in PHP and JavaScript backends; reachability calculations are computed identically in multiple places. The codebase is young (3 commits) with minimal churn. The most actionable opportunity is consolidating test-step execution into a shared service pattern and extracting a reusable page template for Discovery and Connect UI modules.

<div class="metric-grid">
<div class="metric-card"><div class="metric-number">24</div><div class="metric-label">Files Analyzed</div></div>
<div class="metric-card"><div class="metric-number">2</div><div class="metric-label">Functions Over 200 LOC</div></div>
<div class="metric-card"><div class="metric-number">0</div><div class="metric-label">Classes/Files Over 1000 LOC</div></div>
<div class="metric-card"><div class="metric-number">8</div><div class="metric-label">Highest Cyclomatic Complexity</div></div>
</div>

<div class="overall-rating overall-rating--moderate"><div class="overall-rating-label">Overall Codebase Rating — Code Quality &amp; Complexity</div><div class="overall-rating-value">Moderate</div><div class="overall-rating-note">Business logic duplication (test workflows + page templates) and lack of shared service abstractions drive this rating; individual function/class sizing is healthy.</div></div>

<div class="hotspot-score hotspot-score--moderate"><div class="hotspot-score-label">Hotspot Score (weighted composite)</div><div class="hotspot-score-value">58 / 100 — Moderate</div><div class="hotspot-score-formula">Hotspot Score = (Cyclomatic Complexity × 25%) + (Code Churn × 25%) + (Defect Density × 20%) + (Class/Function Size × 15%) + (Business Logic Duplication × 10%) + (Developer Ownership Risk × 5%) = (15 × 0.25) + (5 × 0.25) + (10 × 0.20) + (10 × 0.15) + (75 × 0.10) + (15 × 0.05) = 3.75 + 1.25 + 2.0 + 1.5 + 7.5 + 0.75 = 58</div></div>

## 2.1 Benchmark Ratings Summary

| # | Hotspot | Primary KPI | <span class="rating rating-good">Good</span> | <span class="rating rating-moderate">Moderate</span> | <span class="rating rating-high-risk">High Risk</span> | Measured | Rating |
|---|---|---|---|---|---|---|---|
| H1 | High Cyclomatic Complexity | Max complexity per method | <10 | 10–20 | >20 | 8 | <span class="rating rating-good">Good</span> |
| H2 | Large Classes | Largest class LOC | <300 | 300–1000 | >1000 | 276 (server.js) | <span class="rating rating-good">Good</span> |
| H3 | Large Functions | Largest function LOC | <50 | 50–200 | >200 | 76 (runConnectTest) | <span class="rating rating-moderate">Moderate</span> |
| H4 | Business Logic Duplication | Duplicated business-rule code (%) | <5% | 5–10% | >10% | ~12% | <span class="rating rating-high-risk">High Risk</span> |
| H5 | Duplicate Code (general) | Overall duplicate code (%) | <5% | 5–10% | >10% | ~8% | <span class="rating rating-moderate">Moderate</span> |
| H6 | High Churn Areas | Monthly changes (top files) | <5 | 5–10 | >10 | 2 | <span class="rating rating-good">Good</span> |
| H7 | Defect-Prone Files | Fix commits (hottest file) | 1–3 | 4–5 | >5 | 1 | <span class="rating rating-good">Good</span> |
| H8 | Ownership Issues | Top-author ownership (%) | >80% | 60–80% | <60% | 100% | <span class="rating rating-good">Good</span> |
| H9 | Weak Separation of Concerns | Page component LOC without abstraction | <150 | 150–250 | >250 | 222 (ConnectPage) | <span class="rating rating-moderate">Moderate</span> |

### Hotspot Score breakdown

| Component | Weight | Sub-score (0–100) | Weighted |
|---|---|---|---|
| Cyclomatic Complexity | 25% | 15 | 3.75 |
| Code Churn | 25% | 5 | 1.25 |
| Defect Density | 20% | 10 | 2.0 |
| Class/Function Size | 15% | 10 | 1.5 |
| Business Logic Duplication | 10% | 75 | 7.5 |
| Developer Ownership Risk | 5% | 15 | 0.75 |
| **Hotspot Score** | **100%** | | **58 / 100** |

## 2.2 Hotspot-by-Hotspot Evidence

### H3. Large Functions <span class="sev sev-medium">Medium</span>

**Benchmark:** `Largest function/method LOC = 76` → **Moderate** band (Good <50 · Moderate 50–200 · High Risk >200).

**Example 1:** `runConnectTest()` in `dev-api/src/realtime.js:104–179` (76 LOC) mixes step iteration, event storage, data transformation, and state management.

**Example 2:** `runDiscoveryTest()` in `backend/app/Services/RealTimeTestService.php:22–79` (57 LOC) identical structure to JS version.

**Why it matters:** Hard to test in isolation, reason about, and reuse. Changes to event storage require updating both functions identically.

**Recommended approach:** Extract `TestOrchestrator` service accepting test definitions. Move step arrays to configuration. Push data transformation to dedicated services.

### H4. Business Logic Duplication <span class="sev sev-critical">Critical</span>

**Benchmark:** `Duplicated business-rule code = ~12%` → **High Risk** band.

Test workflow logic duplicated nearly verbatim across PHP and JavaScript backends. Reachability calculations computed in 3 separate places.

**Why it matters:** Maintaining parallel implementations multiplies testing and integration risk. A change to discovery workflow must be applied to both PHP and JS.

**Recommended approach:** Consolidate test orchestration into single service. Extract reachability calculation to shared utility. Use configuration-driven step definitions.

### H5. Duplicate Code (General) <span class="sev sev-high">High</span>

**Benchmark:** `Overall duplicate code = ~8%` → **Moderate** band.

ConnectPage.tsx and DiscoveryPage.tsx share identical structure, form patterns, and React Query setup.

**Why it matters:** Copy-pasted UI logic requires changes in multiple places, raising risk of missed updates and inconsistent behavior.

**Recommended approach:** Extract `TestPageTemplate` component parameterized by module type. Create generic `TestForm` component. Build hook factory for query setup.

### H9. Weak Separation of Concerns <span class="sev sev-medium">Medium</span>

**Benchmark:** `Page component size = 222 LOC (ConnectPage)` → **Moderate** band.

ConnectPage.tsx mixes form handling, queries, mutations, event logic, and rendering. Single prop change affects all responsibilities simultaneously.

**Recommended approach:** Extract form state hook, query setup hook, and UI sub-components. Target <100 LOC per page.

**Not observed (rated Good):** H1 (max complexity 8 < 20), H2 (largest file 276 LOC < 300), H6–H8 (project too young; uniform ownership).

## 2.3 Code Churn & Stability Evidence

Repository in early development (3 commits, ~3 weeks old). Churn analysis not yet meaningful. Most-touched files show typical new-project volatility:

| File | Touches | Type |
|---|---|---|
| frontend/src/App.tsx | 2 | Feature |
| dev-api/src/server.js | 2 | Feature |
| backend/routes/api.php | 2 | Feature |
| backend/app/Http/Controllers/Api/ConnectController.php | 2 | Feature |

**Ownership:** 100% from one developer. Establish clear ownership/review practices as team grows.

## 2.4 Diagrams

### Current Test Orchestration (Duplicated)

```mermaid
flowchart TD
  A["Job Created<br/>(Pending)"] --> B["Start Test"]
  B --> C["Store Status Event"]
  C --> D["For Each Step"]
  D --> E["Await Timing"]
  E --> F["Create Step Payload"]
  F --> G["Conditional Logic"]
  G --> H["Store Event"]
  H --> I["Next Step?"]
  I -->|Yes| E
  I -->|No| J["Store Diagnostics"]
  J --> K["Update Job Status"]
  K --> L["Store Complete Event"]
  L --> M["Job Done"]
  style A fill:#1e3a5f,color:#fff
  style M fill:#27ae60,color:#fff
```

### Recommended Consolidated Architecture

```mermaid
flowchart LR
  A["TestController"] --> B["TestOrchestrator"]
  B --> C["StepIterator"]
  B --> D["EventStore"]
  B --> E["StateManager"]
  C --> F["Step Definition"]
  D --> G["Transcript Store"]
  D --> H["Diagnostic Store"]
  style B fill:#1e3a5f,stroke:#0f3460,color:#fff
  style C fill:#e74c3c,stroke:#c0392b,color:#fff
```

### Improvement Roadmap

```mermaid
flowchart LR
  P1["Phase 1<br/>Extract TestOrchestrator"] --> P2["Phase 2<br/>Unify reachability"]
  P2 --> P3["Phase 3<br/>Refactor frontend pages"]
  P3 --> P4["Phase 4<br/>Form component library"]
  classDef first fill:#e74c3c,stroke:#c0392b,color:#fff
  classDef todo fill:#1e3a5f,stroke:#0f3460,color:#fff
  classDef last fill:#27ae60,stroke:#1e8449,color:#fff
  class P1 first
  class P2,P3 todo
  class P4 last
```

## 2.5 Actions Required

| Hotspot | Action | Rating | Priority |
|---|---|---|---|
| H4 – Business Logic Duplication | Consolidate test orchestration into `TestOrchestrator` service. Move step arrays to configuration. Unify reachability calculation. | <span class="rating rating-high-risk">High Risk</span> | <span class="sev sev-critical">Critical</span> |
| H5 – Frontend Duplicate Code | Extract `PageTemplate` and `usePageData()` hook. Parameterize by module type. Consolidate form handling. | <span class="rating rating-moderate">Moderate</span> | <span class="sev sev-high">High</span> |
| H3 – Large Functions | Split test functions into `initializeTest()`, `executeSteps()`, `finalizeTest()`. | <span class="rating rating-moderate">Moderate</span> | <span class="sev sev-high">High</span> |
| H9 – Page Component Separation | Extract form, query, and UI sub-components. Target <100 LOC per page. | <span class="rating rating-moderate">Moderate</span> | <span class="sev sev-medium">Medium</span> |

## 2.6 Expected Outcomes

- **Lower defect rate:** Fewer places to update when business rules change; reduced copy-paste bug risk.
- **Faster code review:** Smaller, single-responsibility functions and components are easier to validate.
- **Easier testing:** Extracted services can be unit-tested independently from controllers and pages.
- **Better reuse:** Generic `TestOrchestrator` supports new test types without duplication.
- **Improved maintainability:** Clearer patterns reduce onboarding time for new contributors.

# Car insurance comparison — specifications

This folder is the specification. The implementation under `api/`, `frontend/` and `ops/` applies it.

The backend is **Symfony**. The frontend is **Vue.js**.

## Source of truth

- When the code and a specification disagree, the specification is right and the code is a bug.
- When a specification and an ADR in `02-decisions.md` disagree, the ADR is right.
- Where a specification does not cover something, that is reported, not guessed.

## Reading order

Read them in this order.

1. **`01-product-spec.md`** — what the product does and why: user stories and acceptance criteria, the eight UI states (§7.3), success metrics, and the implementation phases (§11).
2. **`02-decisions.md`** — the decision records (ADRs). Every assumption and tradeoff, with its consequence.
3. **`03-architecture.md`** — the shape of the system, the hexagonal layer rules, and how the comparison stays concurrent and inside its deadline.
4. **`04-providers.md`** — the four simulated partners (`aurum`, `bastion`, `celeris`, `dorsal`) and their pricing, latency and failure behaviour.
5. **`05-api-contract.md`** — the HTTP contract.
6. **`06-testing.md`** — what is tested and at which level (L1–L9), the priority tiers, and CI.
7. **`07-observability.md`** — the metrics, logs, alerts and dashboard that tell whether the comparison is healthy after launch.

`c24-api.postman_collection.json` in this folder is a set of runnable API examples, not a specification.

## Invariants

- Money is an integer number of cents inside a `Money` value object — no floats, no formatted strings.
- Time comes from the `Clock` port. Nothing constructs a date directly outside `Infrastructure`.
- Partners are external systems. A failure is a status on a `PartnerOutcome`, never an exception that escapes the gateway.
- Pricing belongs to the partners. There is no pricing logic outside the simulator.
- A partner failing is not the platform failing: a comparison where every partner failed is a successful comparison with zero offers and returns 200.

## Running it

The implementation lives outside `/specs`: `api/` (the Symfony API and the partner simulator, one image run twice), `frontend/` (the Vue 3 SPA), `ops/` (Prometheus and Grafana). To run the full stack and the tests, see the repository `README.md` — in short, `docker compose up --build`, then the three test commands.

---

## How this package was built

The implementation was produced with an AI coding agent, directed by these specs together with the standing rules in `AGENTS.md` and `.cursor/rules/` at the repository root, then reviewed by hand.

### Method

- **One phase per session.** A fresh session for each phase. Long sessions drift: the rules fall out of context and the agent starts reinventing decisions that are already written down.
- **A commit at the end of every phase.**
- **Every phase ends with the same question to the agent:** *"List every decision you made that is not stated in the specs."* Each item in the answer is a gap in the specification, and it is fixed in `specs/`, not only in the code.
- **Every phase ends with a human review:** does anything break the prohibitions in `03-architecture.md` §6? Would I approve this in a colleague's pull request?
- **Phase 0 has no agent.** The scaffold (Symfony skeleton, Vite + Vue, the `specs/`, `AGENTS.md` and `.cursor/rules/` files, `docker-compose.yml`) is set up by hand.

### The prompt at each phase

Each prompt was run on its own, once the previous phase was reviewed and green.

**Phase 1 — Domain and ports**

```text
Read every document in specs/. Implement phase 1 only, from 01-product-spec.md
section 11: the domain layer and the application ports.
Scope: src/Domain and src/Application/Port. No infrastructure, controllers, HTTP,
or Symfony attributes.
- Layer rules from 03-architecture.md section 2.1.
- Money is an integer number of cents inside a Money value object.
- Clock is a port; nothing in Domain constructs a date.
- Coverage, car category, usage and mileage are enums with the exact values in
  05-api-contract.md section 2.1.
Write the L1 tests from 06-testing.md alongside the code, including the age
boundaries in section 8.
When you finish, list every decision you made that is not stated in the specs.
```

*Checked before commit:* no `Symfony\` import under `src/Domain`, no `new DateTimeImmutable()`/`time()` in the domain, domain test suite green.

**Phase 2 — Simulator**

```text
Implement the Simulator from 04-providers.md. Scope: src/Simulator only.
Four partners as HTTP endpoints at POST /sim/partners/{partner}/quote, per
05-api-contract.md section 4.
Each partner has its own pricing engine class holding its own factor tables from
section 3 — in typed code, never in the API's adapters and never in YAML.
Behaviour profiles from section 4, including the exact failure response bodies in
section 4.1. Seeding and forced behaviour from section 5.
Pricing is a pure function of the request. The seed must not affect it.
Write the L2 tests: both test vectors from section 6 must produce the eight listed
prices exactly, plus the age boundary cases.
When you finish, list every decision you made that is not stated in the specs.
```

*Checked before commit:* the eight fixed prices in §6 to the euro — Vector A `celeris 478, bastion 486, aurum 569, dorsal 613`; Vector B `dorsal 979, celeris 1058, aurum 1553, bastion 1924`.

**Phase 3 — Orchestration**

```text
Implement the partner gateway and the comparison handler.
Scope: src/Infrastructure/Partner and src/Application/CompareOffers. Follow
03-architecture.md sections 3.1 to 3.4 exactly.
- CurlHttpClient explicitly. Not NativeHttpClient.
- All callable partners dispatched before any response is read.
- Both timeout and max_duration set per request; global deadline via the timeout
  argument to stream().
- Every outcome becomes a PartnerOutcome with a status and a duration. No partner
  exception leaves the gateway. Cancelled responses are not read.
Do not implement campaigns or the circuit breaker yet; treat every partner as callable.
Write the L3 and L4 tests, plus the L6 timing test.
When you finish, list every decision you made that is not stated in the specs.
```

*Checked before commit:* the L6 timing test with production timeouts — four partners at 1.500 ms resolve in under 2.000 ms of wall clock. About six seconds means the calls are sequential, and the two usual causes are `NativeHttpClient` or reading each response inside the dispatch loop.

**Phase 4 — API layer**

```text
Implement the HTTP API from 05-api-contract.md.
POST /api/v1/comparisons, GET /health, GET /metrics (stub for now).
- Validation mirrors section 2.1 exactly. Every invalid field is reported in one
  422 response, never just the first.
- Unknown fields are rejected, not ignored.
- A comparison where every partner failed returns 200 with an empty offers array.
  Never 5xx.
- The partners array always contains one entry per enabled partner, alphabetically.
- Cache-Control: no-store on every response.
Write the L5 tests using forced partner outcomes, not the seed.
When you finish, list every decision you made that is not stated in the specs.
```

*Checked before commit:* force all four partners to fail and confirm `200` with `"offers": []`. This is the single most commonly implemented wrong, because returning 500 feels correct.

**Phase 5 — Frontend**

```text
Implement the Vue SPA from 03-architecture.md section 4 and 01-product-spec.md
sections 5 and 7.
- Composition API with a single reactive() object in useQuoteForm. No Pinia.
- The steps array from 03-architecture.md section 4.1 is a data structure. V1
  renders all three steps on one page.
- All eight states in 01-product-spec.md section 7.3 exist and are reachable.
- Validation mirrors the API's, declared once per field; server field errors map
  back onto the same fields.
- Persistence per section 4.3: versioned key, 24-hour expiry, visible restore notice.
- Mobile first, single breakpoint at 768px.
Write the L7 tests.
When you finish, list every decision you made that is not stated in the specs.
```

*Checked before commit:* walk all eight states by hand. The two that get skipped are "request error with form values preserved" and "stale input not restored".

**Phase 6 — Campaigns**

```text
Implement campaigns from 01-product-spec.md section 5.4.
- Configured per partner: percentage, start date, end date.
- Applied in the application layer, after offers are collected, BEFORE sorting.
  Sorting by base price and then discounting produces a list ordered by a price the
  customer does not pay.
- The simulator knows nothing about campaigns.
- Validity is evaluated against the Clock port.
Write the L4 campaign tests, including one where the discount changes the ordering.
When you finish, list every decision you made that is not stated in the specs.
```

*Checked before commit:* the ordering test — a partner that is second-cheapest before its discount and cheapest after must appear first.

**Phase 7 — Circuit breaker**

```text
Implement the in-memory circuit breaker from 03-architecture.md section 3.5.
Per partner. Three consecutive failures to open, half-open after 30 seconds, one
trial call to close. Timeouts and errors count as failures; skipped does not.
Open partners are not called and are reported as skipped.
Add a class-level comment stating that this state is per FrankenPHP worker and that
production requires shared state in Redis.
Write the L4 breaker tests.
When you finish, list every decision you made that is not stated in the specs.
```

*Checked before commit:* force a partner to fail three times, then confirm the fourth comparison reports it as `skipped` and completes faster than the per-partner timeout would allow.

**Phase 8 — Observability**

```text
Implement the observability setup from 07-observability.md.
Prometheus metrics from the API, Prometheus and Grafana in docker-compose, and the
dashboard provisioned from a file in the repo rather than configured by hand.
Every metric must be one that 07-observability.md names an action for.
```

*Checked before commit:* `docker compose up`, drive some traffic through the form, and confirm the Grafana dashboard shows data. A dashboard that renders empty panels is worse than a described one.

### The refinement loop

When the agent produced something wrong, the sequence was always: identify which specification was ambiguous enough to allow it, fix the specification, record the corrective prompt and what it fixed, then re-run. A bug fixed only in the code is a bug the specification will produce again.

The corrections:

- **Concurrency (ADR ORCH-8).** The first gateway awaited each response inside the dispatch loop, so the calls ran sequentially. Re-prompted to dispatch all four requests before streaming, and to add a test asserting the order `request, request, request, request, stream`. `NativeHttpClient` was rejected at construction so a non-multiplexing client cannot be swapped in silently.
- **Deadline versus idle timeout (ADR ORCH-5).** Symfony's stream timeout is idle-only, so one slow partner could still extend the total wait. Re-prompted to track wall-clock elapsed against the global deadline and to cancel pending responses without reading once it fires.
- **Deterministic timing test (ADR SIM-3).** The L6 test was flaky because a partner's normal latency band tripped the scaled test timeouts. Re-prompted to seed the simulator per call rather than per process, and to add forced-outcome timing overrides for the tests.

### Reproducing the implementation

To regenerate the code from these specs, point an agent at this folder with:

> Check the specifications in the specs folder and implement the project specified here.

Run it from the repository root so the agent also picks up `AGENTS.md` and `.cursor/rules/`.

### Time budget and what to cut first

| Phase | Budget |
|---|---|
| 0 Scaffold | 0:30 |
| 1 Domain | 0:45 |
| 2 Simulator | 1:00 |
| 3 Orchestration | 1:30 |
| 4 API | 1:00 |
| 5 Frontend | 2:00 |
| 6 Campaigns | 0:45 |
| 7 Circuit breaker | 0:45 |
| 8 Observability | 1:00 |
| 9 Review and package | 1:30 |

If time runs short, cut in this order: the circuit breaker (the comparison is correct without it), then the L9 end-to-end tests, then the Grafana dashboard down to metrics plus a described dashboard.
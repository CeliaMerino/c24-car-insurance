# Architecture Specification

---

## 1. Shape of the System

Three deployable units, one codebase for the two PHP ones.

```
┌──────────────┐      ┌─────────────────┐      ┌──────────────────┐
│  Vue SPA     │─────▶│  Symfony API    │─────▶│  Partner         │
│  (Vite)      │ HTTP │  (FrankenPHP)   │ HTTP │  Simulator       │
└──────────────┘      └─────────────────┘      │  (FrankenPHP)    │
                              │                └──────────────────┘
                              ▼
                      ┌──────────────┐
                      │  Prometheus  │──▶ Grafana
                      └──────────────┘
```

No database. Nothing is persisted between requests.

### 1.1 The partners speak HTTP

The four partners run as a **separate container exposing real HTTP endpoints**, not as in-process PHP classes.

The simulator shares the API's image and codebase, selected by `APP_SIMULATOR_ENABLED`, so this is one build and two services rather than two projects. It must run in its own container: a Symfony app calling itself through a worker pool sized for fewer workers than there are partners will deadlock, and the failure looks like a timeout.

`04-providers.md` describes what each partner does.

---



## 2. Backend Structure

Hexagonal, with the comparison logic depending on ports and never on Symfony or HTTP.

```
src/
├── Domain/
│   ├── Quote/          QuoteRequest, Age, PostalCode, CarCategory,
│   │                   Usage, AnnualMileage, CoverageLevel
│   ├── Offer/          Offer, Money, PartnerId
│   ├── Comparison/     Comparison, PartnerOutcome, PartnerStatus
│   ├── Campaign/       Campaign, Discount
│   ├── Validation/     ValidationException, ValidationError
│   └── Shared/         ReferenceDate
├── Application/
│   ├── CompareOffers/  CompareOffersHandler, CompareOffersQuery
│   └── Port/           PartnerGateway, PartnerRegistry, CircuitBreaker,
│                       CampaignRepository, Clock, MetricsRecorder
├── Infrastructure/
│   ├── Partner/        HttpPartnerGateway, PartnerResponseParser,
│   │                   ConfigPartnerRegistry
│   ├── CircuitBreaker/ InMemoryCircuitBreaker
│   ├── Campaign/       ConfigCampaignRepository
│   ├── Http/           ComparisonController, EventsController,
│   │                   HealthController, MetricsController
│   └── Observability/  PrometheusMetricsRecorder
└── Simulator/          SimulatorController, four pricing engines
```



### 2.1 Layer rules

- `Domain` imports nothing outside `Domain`. No Symfony, no HTTP, no dates from `new DateTimeImmutable()`.
- `Application` imports `Domain` and its own ports. It never imports `Infrastructure`.
- `Infrastructure` implements ports and adapts frameworks. It holds no business rules.
- `Simulator` is not part of the application at all. It is a stand-in for a third party and imports nothing from `Domain` or `Application`.

**The pricing factor tables live in** `Simulator`**, not in** `Infrastructure/Partner`**. Pricing is the partner's business, not the platform's, and the gateway's only job is turning an HTTP response into an `Offer` or a failure status. If pricing logic ever appears in `Infrastructure/Partner`, the boundary has been crossed.

### 2.2 Money

Money is an integer number of cents inside a `Money` value object, everywhere. No floats, no strings, no bare integers passed around. The only rounding in the system happens inside the simulator when it converts its factor product to whole euros, and inside the discount calculation.

### 2.3 Time

`Clock` is a port. Nothing outside `Infrastructure` calls `new DateTimeImmutable()` or `time()`. Age calculation and campaign validity windows both depend on it, and both have test vectors pinned to a fixed date.

---



## 3. The Comparison



### 3.1 Sequence

```
1. Controller validates the request and builds a QuoteRequest.
2. Handler asks the registry for enabled partners.
3. Handler asks the circuit breaker which of them are callable.
   Non-callable partners get status `skipped` immediately.
4. Gateway dispatches all callable partners concurrently.
5. Gateway collects results until the global deadline.
   Each partner resolves to an Offer or a status.
6. Handler reports each outcome to the circuit breaker.
7. Handler applies campaign discounts to the offers that arrived.
8. Handler sorts by FINAL price ascending, tie-broken by partner name.
9. Handler records metrics.
10. Controller maps the Comparison to the response body.
```

### 3.2 Concurrency

Symfony HttpClient, with `CurlHttpClient` **explicitly**. `NativeHttpClient` does not multiplex, and swapping it in turns four concurrent calls into four sequential ones with no error and no failing unit test.

```php
$responses = [];
foreach ($callablePartners as $partner) {
    $responses[$partner->id()] = $this->client->request('POST', $partner->url(), [
        'json'         => $payload,
        'timeout'      => 2.0,   // idle timeout between chunks
        'max_duration' => 2.0,   // hard ceiling on the whole call
        'user_data'    => $partner->id(),
    ]);
}

foreach ($this->client->stream($responses, $remainingDeadlineSeconds) as $response => $chunk) {
    // collect, or mark timeout when $chunk->isTimeout()
}
```

Both options are required. `timeout` alone caps idle time between chunks, so a partner that trickles bytes slowly stays under it indefinitely. `max_duration` is what enforces the 2.000 ms in `04-providers.md`.

### 3.3 Deadlines


| Limit       | Value    | Enforced by                    |
| ----------- | -------- | ------------------------------ |
| Per partner | 2.000 ms | `max_duration` on each request |
| Global      | 3.000 ms | timeout argument to `stream()` |


The global deadline is a backstop, not the primary mechanism. With four partners called concurrently and each capped at 2.000 ms, a healthy comparison resolves in about the time of its slowest partner. The global deadline exists for the case where the client itself misbehaves, and for the case where partner count grows enough that connection setup starts to matter.

When the global deadline fires, outstanding responses are cancelled and recorded as `timeout`. A cancelled response must not be read.

### 3.4 Failure containment

No partner failure leaves the gateway as an exception. Every outcome is a `PartnerOutcome` carrying a status, a duration, and either an `Offer` or nothing. The handler has no try/catch around individual partners because there is nothing to catch.

A comparison in which every partner failed is still a successful comparison with zero offers. It is not an error, and the API does not return an error status for it.

### 3.5 Circuit breaker

Per partner, in memory, three consecutive failures to open, half-open after 30 seconds, one trial call to close. Timeouts and errors both count as failures; `skipped` does not.

**This state is per FrankenPHP worker.** Each worker keeps its own counters, so with N workers a partner opens after roughly 3N failures overall and different workers disagree about its state.

---



## 4. Frontend Structure

```
src/
├── api/
│   ├── client.ts          fetch wrapper, error mapping
│   └── types.ts           request and response types
├── composables/
│   ├── useComparison.ts   submit, loading, results, errors
│   ├── useQuoteForm.ts    form state, validation, step definitions
│   └── useFormStorage.ts  localStorage read/write, expiry
├── components/
│   ├── form/              DriverStep, VehicleStep, CoverageStep, FormField, options
│   ├── results/           OfferCard, OfferList, OfferSkeleton
│   └── states/            EmptyResults, RequestError, RestoreNotice
└── views/
    └── ComparisonView.vue
```

Vue 3, TypeScript, Composition API. 

### 4.1 Steps as data

The form's three logical steps are a data structure, not a layout:

```ts
const steps = [
  { id: 'driver',   fields: ['dateOfBirth', 'postalCode'] },
  { id: 'vehicle',  fields: ['carCategory', 'usage', 'annualMileage', 'garage'] },
  { id: 'coverage', fields: ['coverage'] },
]
```

V1 renders all three on one page. The multi-page funnel renders one at a time behind routes. Validation is already per step, so the funnel is a routing change and a layout change, with no change to state or validation.

### 4.2 Validation

Rules are declared once, per field, and shared by blur validation and submit validation. They mirror the server rules in `05-api-contract.md` exactly. Where the server rejects something the client allowed, the server's field-level errors are mapped back onto the same fields, so both paths produce identical UI.

### 4.3 Persistence

`useFormStorage` writes the form object on change under `c24-comparison-form-v1`, with a stored timestamp. On mount, it restores only if the payload parses, the version matches, and it is under 24 hours old. Anything else is discarded silently. The key is versioned so that a change to the form's shape cannot resurrect an incompatible object.

---



## 5. Configuration and Environments


| Variable                     | Purpose                                                        |
| ---------------------------- | -------------------------------------------------------------- |
| `PARTNER_SIMULATOR_BASE_URL` | Where the API finds the simulator                              |
| `PARTNER_TIMEOUT_MS`         | Per-partner ceiling, default 2000                              |
| `COMPARISON_DEADLINE_MS`     | Global ceiling, default 3000                                   |
| `CIRCUIT_BREAKER_THRESHOLD`  | Consecutive failures to open, default 3                        |
| `CIRCUIT_BREAKER_COOLDOWN_S` | Half-open delay, default 30                                    |
| `PARTNER_SIMULATION_SEED`    | Fixes partner behaviour when set                               |
| `APP_SIMULATOR_ENABLED`      | Exposes the `/sim` routes. Must be false in the API container. |


Every timing value is configuration. None of them is a constant in code, because R3 in the PRD depends on being able to tune the deadline once real latency data exists.

`docker compose up` brings up the SPA, the API, the simulator, Prometheus and Grafana, with the Grafana dashboard provisioned from a file.

---



## 6. What an Implementation Must Not Do

- Call partners sequentially, whether by using `NativeHttpClient` or by awaiting each response inside the dispatch loop.
- Run the simulator with a worker pool smaller than the partner count. Its latency is a plain sleep, which `04-providers.md` section 5.3 permits because each partner call is a separate request served by its own worker; an undersized pool turns those sleeps into a queue, and the symptom is a timeout.
- Put pricing logic anywhere in `Infrastructure/Partner`.
- Sort offers before applying campaign discounts.
- Return a non-200 status when zero partners produced an offer.
- Let a partner exception reach the controller.
- Read from a cancelled response.
- Call `new DateTimeImmutable()` outside `Infrastructure`.
- Hard-code `aurum` as a fallback when other partners fail.

---


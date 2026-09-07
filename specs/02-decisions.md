# Decision records

Hand-curated decisions that the product notes and the other spec files leave open. Each entry
states the decision as a rule and, where it changes observable behaviour, its testable
consequence. Decisions are referenced elsewhere in the repo by their code (e.g. "per ORCH-5").

An implementation built from `specs/` should reproduce these choices. Where the code and the
architecture diagram disagree, the decision recorded here is the source of truth and the
divergence is called out explicitly rather than left implicit.

## Contents

- [Cross-cutting conventions](#cross-cutting-conventions)
- [1. Domain and validation](#1-domain-and-validation)
- [2. Pricing and campaigns](#2-pricing-and-campaigns)
- [3. Provider simulation](#3-provider-simulation)
- [4. Orchestration and resilience](#4-orchestration-and-resilience)
- [5. API contract](#5-api-contract)
- [6. Frontend](#6-frontend)
- [7. Observability](#7-observability)
- [8. Testing and known gaps](#8-testing-and-known-gaps)
- [Status](#status)

## Cross-cutting conventions

These hold across every section below unless a specific decision overrides them.

- **Timezone.** All server-side date logic is UTC: `SystemClock`, the simulator clock,
  campaign dates, and date-of-birth checks. The frontend date picker is local, which can
  disagree by one day near midnight — accepted (see FE-3).
- **Time access.** Domain and Application never call `time()` or construct a date. Wall clock
  comes from the `Clock` port; elapsed time from `Clock::monotonicMs()`. Only Infrastructure
  constructs dates or calls `hrtime()`.
- **Validity windows** are inclusive on both ends everywhere (campaigns and any date range).
- **Error codes.** Every validation or error response carries a stable machine `code`.
  Human-readable messages may change; clients and tests key on the code, never the text.
- **Money** is integer cents, currency EUR only (`Money::CURRENCY = 'EUR'`); prices never use
  floats.
- **Error envelope.** Validation failures return HTTP 422 and malformed requests HTTP 400,
  both as RFC 9457 `problem+json` (see API-1).

## 1. Domain and validation

Value objects, layer boundaries, and the field-level validation codes and messages the brief
does not spell out.

**DOM-1 Reference date as integers.** Domain time is a `ReferenceDate` value object holding
year/month/day integers passed from the `Clock` port — never a `DateTimeImmutable`, to keep
the "no date construction outside Infrastructure" rule. → Age logic stays pure and unit
testable, and the frozen-clock tests need no access to real time.

**DOM-2 `PartnerId` is a value object, not an enum.** An enum blocked the "fifth partner"
extensibility case; a value object keeps the partner set config-driven. `Offer::withCampaign`
compares partners with `equals()`. → A new partner is a configuration change, not a code
change.

**DOM-3 `ValidationException` lives in `Domain/Validation/`.** Kept in the domain so
validation is testable at L1. Mapping validation errors to HTTP 422 is done in the API layer
(see API-1, API-5), not in the domain.

**DOM-4 `DateOfBirth::fromParts()` is an unchecked factory.** It bypasses minimum-age
validation so age-boundary and age-calculation tests can construct any date directly. It is
not used on the request path, where the validated constructor runs.

**DOM-5 Field validation codes and messages.** Codes are the contract; the following messages
are ours because the brief only exemplifies the postal-code format message:

- `future_date` → `Date of birth cannot be in the future.`
- date-of-birth `format` → `Enter a valid date in YYYY-MM-DD format.`
- postal code — a value with the wrong shape **and** a well-shaped but out-of-range value both
  return the single `format` code with the five-digit message. There is no separate
  out-of-range code.

Request-envelope codes (`required`, `unknown_value`, `unknown_field`, type mismatch) are
defined in API-5.

**DOM-6 Age brackets are tested at both edges.** Each bracket is tested the day before the
birthday (which lands in the lower age) and on the birthday (the upper age), relative to the
frozen reference date `2026-08-31`.

## 2. Pricing and campaigns

Money representation, discount arithmetic, and how a marketing campaign is configured and
applied.

**PRC-1 Money is integer cents, EUR only.** `Money::CURRENCY = 'EUR'`. Prices never use floats
anywhere in the stack.

**PRC-2 Discount rounding is `PHP_ROUND_HALF_UP`** to the nearest cent. The brief says
rounding happens in the discount calculation but not which mode.

**PRC-3 A campaign's validity window is inclusive on both the start and the end calendar day**,
evaluated in UTC.

**PRC-4 A campaign that has not started yet is treated exactly like an expired one** — not
applied. The brief only names the expired case; this closes the "not yet live" case the same
way.

**PRC-5 The campaign label is derived, not configured.** Config carries only percentage and
dates. The label is `CHECK24 pays {n}%`, taken from the API contract example. → Config cannot
drift from the displayed label.

**PRC-6 Campaign config dates are quoted `YYYY-MM-DD` strings**, parsed as UTC calendar dates
(same timezone as `SystemClock`).

**PRC-7 Percentage is an integer 0–100.** A percentage outside that range, or a window whose
end is before its start, fails at container boot rather than at request time. → A misconfigured
campaign cannot reach production silently.

**PRC-8 No campaign is configured in the default environment.** The brief describes the shape,
not a live campaign; a live campaign is per-environment configuration. → Default responses
carry `campaign: null`, and existing happy-path tests stay valid.

**PRC-9 Only `Comparison.offers` are discounted.** `PartnerOutcome` keeps the partner's
original quoted price, so observability and debugging can still see what the partner actually
returned before any CHECK24 discount.

**PRC-10 `Offer::withCampaign` on a partner the campaign does not target returns the offer
unchanged** — final price equals base price, no discount.

## 3. Provider simulation

The simulated partners are treated as external systems. These decisions define how they
behave, how their failure and latency are configured, and how the behaviour is made
reproducible for tests.

**SIM-1 Seeding is per call, not per process.** Each partner call seeds Mt19937 from the seed,
the partner id, and a fingerprint of the request. A single stateful generator held in a service
cannot work here: the simulator serves each partner call as a separate request, so a
service-held generator is rebuilt from the seed on every request and every call would draw the
same outcome. → A given comparison is reproducible regardless of which worker serves it or the
order the four partners are dispatched in. Trade-off: with a seed set, the same request always
gets the same behaviour from a partner rather than varying call to call — a narrower reading of
the brief, chosen for reproducibility. With the seed unset, behaviour is unpredictable per call
as specified.

**SIM-2 `connection_error` is a truncated response, not a refused connection.** A connection
cannot be refused once it has been accepted, so the simulator returns HTTP 200 declaring
`Content-Length: 1024` with an empty body.

**SIM-3 Forced-outcome timings, via two environment variables.** `PARTNER_FORCED_SLOW_MS`
(1500, the value the timing test implies) and `PARTNER_FORCED_TIMEOUT_MS` (2500). Forced `ok`
and the forced response-shape outcomes sleep zero. Without this, bastion's normal 1200–1900 ms
band would time out under the scaled test timeouts and break the "all four forced to ok"
acceptance test.

**SIM-4 Force overrides are available only in `test` and `dev`.** They are rejected in every
other environment, not only in prod. Enforced twice: a compiler pass (startup failure) and the
reader itself, because a container compiled before the variable was set would otherwise honour
it.

**SIM-5 Dorsal's three failure modes are mutually exclusive.** They are drawn from one number
in declaration order (8% + 6% + 2% = 16% total). The brief only requires latency and failure to
be independent of each other.

**SIM-6 Two invented config shapes.** A per-partner `http_error_status` (aurum 500, the others
503, default 503), and the spike shape `spike: {probability, latency_ms}`, since the brief only
ever shows `spike: null`.

**SIM-7 The simulator clock is split across two locations.** The simulator cannot import
`Application\Port\Clock`, but nothing outside Infrastructure may construct a date. So the
interface is `src/Simulator/Clock` and `SystemSimulatorClock` is in
`src/Infrastructure/Simulator`, using UTC. This and `Kernel::build()` are the only files added
outside `src/Simulator`.

**SIM-8 Error responses name the partner actually being called** (not a hard-coded example
partner). A request body the simulator cannot parse returns 400; an unknown partner returns
404. Neither status is specified by the brief.

**SIM-9 Environment reading falls back to `getenv()`.** Symfony's Dotenv deliberately does not
populate the process environment, and under a web SAPI `$_SERVER` carries no process
environment, so `PARTNER_{ID}_FORCE` would be invisible in a container without this fallback.
`.env.test` sets `APP_SIMULATOR_ENABLED=1` so the contract test can reach the endpoint.

**SIM-10 The simulator route is declared in `config/routes/simulator.php`** rather than as a
route condition, which would have meant adding `symfony/expression-language` for one boolean.

## 4. Orchestration and resilience

How the comparison fans out to the partners, parses their responses, and stays responsive when
a partner is slow or failing. The goal the brief sets is that partner problems must not make
the product feel broken.

**ORCH-1 Duration is measured with `Clock::monotonicMs()`** so Application never calls
`time()`. The gateway measures its own timings with `hrtime()` directly, since it is
Infrastructure.

**ORCH-2 `comparison_id` is supplied on `CompareOffersQuery`.** ULID generation needs time and
Symfony, so it stays out of Application; the controller mints the id (see API-7).

**ORCH-3 The response parser rejects** a `partner` field that does not match the called
partner, a `coverage` that does not match the request, a non-EUR currency, and a non-integer or
negative `annual_premium_cents`. Extra JSON fields are ignored, and `retry_after` is ignored.

**ORCH-4 Transport-error classification.** A transport error whose message contains "timeout"
or "timed out" maps to `timeout`; any other transport failure maps to `error`. curl's
`max_duration` surfaces as a `TransportException`, not always a `TimeoutException`, so message
inspection is needed.

**ORCH-5 Idle timeout versus wall-clock deadline.** Symfony's stream timeout is idle-only, so
the gateway also tracks elapsed wall clock. If the overall deadline has fired, remaining
responses are cancelled as `timeout` and not read; if it has not, only the idle partner is
timed out. → One slow partner cannot extend the total wait past the deadline.

**ORCH-6 A disabled partner is omitted** from `enabledPartners()` and therefore from the
comparison, following `04-providers.md §7`.

**ORCH-7 Per-partner `timeout_ms` is `%env(int:PARTNER_TIMEOUT_MS)%`**, not a literal, so the
timeout is configurable per environment.

**ORCH-8 `NativeHttpClient` is rejected at gateway construction**, so a non-concurrent client
cannot be swapped in silently and quietly serialise the partner calls.

### Circuit breaker

**ORCH-9 Half-open is per partner, not global.** If several partners' cooldowns have elapsed,
each gets its own trial call in the same comparison.

**ORCH-10 The trial is allowed at exactly the cooldown** (elapsed ≥ 30s), measured with
`Clock::monotonicMs()`. `CIRCUIT_BREAKER_COOLDOWN_S` is converted to milliseconds (× 1000).

**ORCH-11 A failed half-open trial reopens the breaker immediately and starts a new cooldown.**
The brief only says one successful trial closes the breaker; staying half-open would allow
another trial on the next comparison without waiting, so a failed trial re-opens instead.

**ORCH-12 While closed, an `ok` resets the consecutive-failure count.** This follows from
"consecutive", though the reset rule is not written down.

**ORCH-13 `skipped` outcomes are a breaker no-op.** The handler reports them to the breaker,
which neither opens, closes, nor resets a cooldown on them. A skipped partner's `duration_ms`
is `0`.

**ORCH-14 Breaker state lives on the service instance, not in statics.** Caveat: under PHP-FPM
each request builds a new container, so counters do not actually survive across HTTP requests
today — a shared store (Redis) is required for real cross-request breaking. This is recorded in
the class comment; the L4 tests reuse a single instance to exercise the state machine.

## 5. API contract

The HTTP envelope: how malformed requests and validation failures are shaped, how errors are
ordered, and what the health and metrics endpoints return. Validation *codes* for individual
fields are in DOM-5; this section covers the request envelope and the response shapes.

**API-1 Malformed requests are RFC 9457 `problem+json`** with
`type: https://check24.example/problems/malformed-request`, `title: Malformed request`,
`status: 400`, and a `detail` string. Validation failures use HTTP 422 with the same
`problem+json` envelope. The contract describes *when* 400 happens but not the body.

**API-2 A JSON body that is not an object** (`[]`, `"x"`, `true`) is a 400, not a 422.

**API-3 Content-Type is checked first** when both the header and the body are wrong.

**API-4 `application/json; charset=utf-8` is accepted** — only the media type before the `;` is
compared.

**API-5 Request-envelope validation codes and messages** (invented English; the frontend keys
on the code):

- `required` fires only when a field is absent or null. An empty string is **not** `required`.
- An empty string is `format`.
- A wrong JSON type (a number where a string is required, a string where a boolean is required)
  is `format`, not `unknown_value`.
- `unknown_value` and `unknown_field` cover an out-of-domain value and an unexpected field.

**API-6 Error ordering.** Known fields appear first in contract order, then unknown fields in
payload order. → Error output is deterministic for tests.

**API-7 `comparison_id` is a Symfony ULID.** Its timestamp comes from the system clock, not the
`Clock` port. It is omitted from 400 and 422 bodies.

**API-8 Health.** `GET /health` returns `{"status":"ok"}` — the "minimal body" the spec asks
for.

**API-9 `GET /metrics` has no auth.** "Not exposed publicly" is treated as a deployment concern
(network / ingress), not an application concern. The metric payload itself is defined in
section 7.

**API-10 `Cache-Control` on comparison responses is `no-store, private`.** Symfony's
`ResponseHeaderBag` appends `private` unless `public`/`private` is already set; the `no-store`
directive is present.

## 6. Frontend

Serving, how the form survives a reload, how results and errors are presented, and the copy the
brief leaves open. The form is kept simple but must not block a later multi-page funnel.

**FE-1 Served by Vite on port 5173.** This matches the existing compose mapping; the
architecture diagram's nginx is not used for the exercise. (Divergence recorded above.)

**FE-2 The browser calls `/api/v1`; Vite proxies to the API** (`API_PROXY_TARGET`).
`VITE_API_BASE_URL` is `/api/v1` (changed from an absolute `localhost:8000`) so CORS does not
have to be added to the API, which the brief does not specify.

**FE-3 Date-of-birth / "today" checks use UTC**, matching the API clock. This can disagree with
the date picker's local day near midnight — accepted.

**FE-4 Form-persistence payload** is `{ version: 1, savedAt, values }` under the key
`c24-comparison-form-v1`. The brief asks for a versioned key and a timestamp; the inner
`version` field is ours.

**FE-5 Restore window and empty handling.** Stored input is restored only while under 24h old;
at ≥ 24h it is stale. All-empty payloads are never restored and show no notice. A change that
leaves every field empty clears storage instead of writing blanks. Hydrating from storage does
not refresh `savedAt`. → The customer's input surviving a reload never surprises them with
stale or empty state.

**FE-6 Prices render as `€1,234.00 / year`** from integer cents, no float. Locale and grouping
were unspecified; this is the chosen format.

**FE-7 Results versus form.** A "Change details" control returns to the form (the brief says
the customer goes back but does not name the control). Whether results are full or partial is a
data-state derived from the partner statuses; the offer list looks the same either way
(ADR-007).

**FE-8 Request errors keep the form visible** with its values, showing an error and a retry
below. A 400 and any non-422 failure are handled like a 5xx or network error.

**FE-9 The commercial-use / garage field's client rule is "required" only.** The API's `format`
code for this field exists for a non-boolean JSON type that the form cannot produce.

**FE-10 Campaign UI is rendered on the offer cards** (brief §5.4) even though applying
campaigns is a later phase. The client only renders `campaign` when it is present in the
response.

**FE-11 "Clear saved details" resets both the form and storage**, not storage alone.

**FE-12 The loading state shows four skeleton cards**, one per partner. The count was
unspecified.

**FE-13 A frozen `now()` is injected into `useQuoteForm` and `useFormStorage`** for the L7
clock test. The architecture tree has no frontend Clock, so this is an injected function rather
than a port.

**FE-14 UI copy is ours** — the heading, the restore notice, the empty and error states, the
buttons, and the field labels. The brief gives only coverage meanings and mileage ranges.

**FE-15 Files and layout beyond the architecture tree.** `options.ts` and `FormField.vue` are
extra files relative to the tree. Coverage cards are three columns above 768px (the spec only
says the form may use two columns). A misplaced `api/frontend` Vite template was removed so the
API image does not ship a second SPA.

## 7. Observability

The senior-profile observability layer: the events endpoint, how metrics are labelled, when the
no-traffic alert fires, and how faults are logged. The point is to tell whether the comparison
is healthy after launch, not to measure for its own sake.

**OBS-1 `POST /api/v1/events` returns 204 on success.** The status was not specified.

**OBS-2 An unknown event type returns `problem+json` 422**, the same envelope as comparison
validation (API-1).

**OBS-3 The partner-duration histogram skips `skipped` partners.** There was no real call to
time, so recording a duration would pollute the latency distribution.

**OBS-4 Unmatched routes are labelled `unmatched`** on `c24_http_responses_total`. → Route label
cardinality stays bounded instead of growing with every stray path.

**OBS-5 The "no traffic" alert fires only on weekdays, 09:00–18:00 UTC** (`hour()` /
`day_of_week()`). → Silence outside business hours does not page anyone.

**OBS-6 Platform-fault logs use `comparison_id: "unknown"`** when the fault happens before an id
exists. → Every fault line still has the field, so log queries never break on its absence.

**OBS-7 `PartnerOutcome.httpStatus` was added** so a partner-failure log can include
`http_status` alongside the outcome.

## 8. Testing and known gaps

How the test harness is wired, and which scenarios are deliberately not covered yet and why.
See `06-testing.md` for the level definitions (L1–L7).

### Test-harness decisions

**TEST-1 Clocks are frozen at `2026-08-31`** for both the API and the simulator in tests;
`duration_ms` still uses `hrtime`, since it measures elapsed real time rather than wall-clock
date.

**TEST-2 L5 talks to the simulator in-process** (`KernelHttpClient`) so `PARTNER_{ID}_FORCE` is
visible and PHPUnit needs no listening TCP port. A forced timeout is turned into a client
timeout by honouring `max_duration` after the simulator sleep. This is not concurrent HTTP.

**TEST-3 The L5 degradation test uses a `malformed` response, not `http_error`.** A mixed
`http_error` + `ok` batch collapses the concurrent `KernelHttpClient` harness when run
in-process, so `malformed` is used to exercise the same degradation path.

**TEST-4 The in-test metrics registry is public and shared** so Symfony does not inline separate
in-memory instances that the assertions could not then read.

### Known gaps

Deliberately out of scope for now, each with its reason so a reviewer (or an implementation
agent) does not read them as oversights.

**GAP-1 No L5 for an active campaign being applied.** The testing map assigns that scenario to
both L4 (covered) and L5. An L5 would need either a default campaign — which would change the
existing happy-path assertions (see PRC-8) — or a per-test config override, neither of which is
specified.

**GAP-2 No L5 for a partner `skipped` by the circuit breaker.** Covered at L4; the L5 version
maps to a "partner known to be down" scenario in `06-testing.md` and is left for that work.

**GAP-3 Observability replaced an earlier stub.** Metrics were a stub through the API phase and
became real in the observability phase; any earlier stub-era detail is superseded by section 7,
which is the current contract.

## Status

Implemented: domain and pricing, the four simulated partners, concurrent orchestration with
per-partner timeouts and a per-partner circuit breaker, the comparison API, the Vue frontend,
campaign application, and the observability layer. Deliberate gaps and their reasons are in
section 8.

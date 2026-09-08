# Partner Specification

---

## 1. Scope

The four insurance partners: how each one prices a quote request, how each one misbehaves, and how both are made deterministic for testing.

Partners are simulated inside the project, but they are real HTTP services in their own container (`03-architecture.md`, section 1.1). Every failure mode below is an actual HTTP response rather than a simulated one, and replacing a simulated partner with a real insurer is a change of URL and response mapping, with no effect on the comparison logic.

Two rules:

- **Pricing is deterministic.** The same quote request always yields the same price from the same partner. Nothing about the price is random.
- **Behaviour is random but reproducible.** Latency and failure vary from call to call in normal operation, and repeat exactly under a fixed seed.

---



## 2. The Partner Port



### 2.1 Request

Every partner receives the same validated quote request:


| Field            | Type   | Values                                             |
| ---------------- | ------ | -------------------------------------------------- |
| `date_of_birth`  | date   | Customer is 18+ at request time                    |
| `postal_code`    | string | Five digits                                        |
| `car_category`   | enum   | `compact`, `sedan`, `suv`, `van`                   |
| `usage`          | enum   | `private`, `commercial`                            |
| `annual_mileage` | enum   | `under_5k`, `5k_15k`, `15k_30k`, `over_30k`        |
| `garage`         | bool   |                                                    |
| `coverage`       | enum   | `third_party`, `third_party_plus`, `comprehensive` |




### 2.2 Successful response

```json
{
  "partner": "aurum",
  "coverage": "third_party_plus",
  "annual_premium_cents": 56900,
  "currency": "EUR"
}
```

Money is transported as an integer number of cents. No floating point crosses the boundary.

### 2.3 Outcome mapping

The HTTP gateway maps every partner outcome to exactly one status, which the API returns per partner and the observability layer counts.


| Status    | Cause                                                                                    |
| --------- | ---------------------------------------------------------------------------------------- |
| `ok`      | HTTP 200 and a schema-valid body                                                         |
| `timeout` | Exceeded the 2.000 ms per-partner timeout, or the 3.000 ms global deadline elapsed first |
| `error`   | Non-200 status, schema-invalid body, non-JSON body, or connection failure                |
| `skipped` | Circuit breaker open for this partner                                                    |


A partner failure is never allowed to propagate as an exception into the comparison. The orchestrator receives a status, never a throw.

---



## 3. Pricing Model

Every partner computes its price the same structural way and disagrees only on the numbers:

```
premium = base × age × category × usage × mileage × garage × region × coverage
```

The result is rounded half-up to whole euros, then expressed in cents.

**Each partner ignores some factors**, by fixing them at `1.00`. This is what makes the comparison worth doing: no single partner is cheapest for every customer, and the ignored factors are where the orderings cross over.

`region` derives from the first two digits of the postal code. Provinces `28`, `08`, `41` and `46` are high-density; everything else is standard. No other geographic meaning exists in the system.

Age is calculated from `date_of_birth` against the request date. The clock must be injectable, or the test vectors in section 6 expire on the customer's next birthday.

### 3.1 Aurum Direct — `aurum`

The complete underwriter. Uses every factor, weights nothing extremely, fails rarely.


| Factor   | Values                                                            |
| -------- | ----------------------------------------------------------------- |
| Base     | €420                                                              |
| Age      | 18–24: 1.55 · 25–34: 1.20 · 35–54: 1.00 · 55–69: 1.10 · 70+: 1.35 |
| Category | compact 0.95 · sedan 1.00 · suv 1.15 · van 1.25                   |
| Usage    | private 1.00 · commercial 1.30                                    |
| Mileage  | under_5k 0.90 · 5k_15k 1.00 · 15k_30k 1.15 · over_30k 1.30        |
| Garage   | yes 0.93 · no 1.00                                                |
| Region   | high-density 1.12 · standard 1.00                                 |
| Coverage | third_party 1.00 · plus 1.30 · comprehensive 1.75                 |




### 3.2 Bastion Insurance — `bastion`

The cheap and blunt one. Lowest base premium, but **ignores garage and region**, and loads heavily on vehicle and mileage. Cheapest for urban drivers with no garage; expensive for anyone who drives a lot. Steepest coverage ladder of the four, so its advantage evaporates at comprehensive.


| Factor   | Values                                                     |
| -------- | ---------------------------------------------------------- |
| Base     | €360                                                       |
| Age      | 18–24: 1.40 · 25–34: 1.15 · 35+: 1.00                      |
| Category | compact 0.90 · sedan 1.00 · suv 1.20 · van 1.35            |
| Usage    | private 1.00 · commercial 1.45                             |
| Mileage  | under_5k 0.85 · 5k_15k 1.00 · 15k_30k 1.20 · over_30k 1.40 |
| Garage   | **ignored** (1.00)                                         |
| Region   | **ignored** (1.00)                                         |
| Coverage | third_party 1.00 · plus 1.35 · comprehensive 1.95          |




### 3.3 Celeris Seguros — `celeris`

The age specialist. Most aggressive age curve of the four and the only partner that discounts below its base for the 35–54 band, so it wins the middle of the market and prices young drivers out. Also the only one that discounts standard regions.


| Factor   | Values                                                            |
| -------- | ----------------------------------------------------------------- |
| Base     | €400                                                              |
| Age      | 18–24: 1.90 · 25–34: 1.30 · 35–54: 0.90 · 55–69: 1.05 · 70+: 1.50 |
| Category | compact 0.95 · sedan 1.00 · suv 1.10 · van 1.20                   |
| Usage    | private 1.00 · commercial 1.25                                    |
| Mileage  | under_5k 0.92 · 5k_15k 1.00 · 15k_30k 1.12 · over_30k 1.25        |
| Garage   | yes 0.90 · no 1.00                                                |
| Region   | high-density 1.18 · standard 0.98                                 |
| Coverage | third_party 1.00 · plus 1.25 · comprehensive 1.60                 |




### 3.4 Dorsal Mutual — `dorsal`

The flat-rate insurer. Highest base premium, but **ignores category and mileage** and barely loads on age. Uncompetitive for a low-mileage compact and the cheapest option by a wide margin for a high-mileage van.


| Factor   | Values                                            |
| -------- | ------------------------------------------------- |
| Base     | €480                                              |
| Age      | 18–24: 1.25 · 25+: 1.00                           |
| Category | **ignored** (1.00)                                |
| Usage    | private 1.00 · commercial 1.20                    |
| Mileage  | **ignored** (1.00)                                |
| Garage   | yes 0.95 · no 1.00                                |
| Region   | high-density 1.05 · standard 1.00                 |
| Coverage | third_party 1.00 · plus 1.28 · comprehensive 1.70 |


---



## 4. Behaviour Profiles

Latency and failure are drawn per call. Probabilities are independent: a call may be slow and then fail.


| Partner   | Normal latency | Spike                          | Failure modes                                      |
| --------- | -------------- | ------------------------------ | -------------------------------------------------- |
| `aurum`   | 80–250 ms      | none                           | 1% HTTP 500                                        |
| `bastion` | 1.200–1.900 ms | none                           | 2% connection error (truncated response, SIM-2)    |
| `celeris` | 100–300 ms     | 15% of calls at 2.500–4.500 ms | 3% HTTP 503                                        |
| `dorsal`  | 200–600 ms     | none                           | 8% HTTP 503 · 6% malformed body · 2% non-JSON body |


Each partner exercises a different failure shape, so that no single defensive mechanism covers all four:

- `bastion` **is always slow.** It never times out on its own, but it sits close enough to the 2.000 ms cut that any added load drops it out of the comparison. It is also the cheapest partner at third-party level, which makes its absence commercially visible rather than merely technical.
- `celeris` **is fast until it is not.** Its spikes exceed the per-partner timeout, so it exercises the timeout path without ever being reliably broken. A partner that fails only 15% of the time is harder to detect than one that fails always.
- `dorsal` **breaks the contract rather than the connection.** Its malformed and non-JSON responses exercise the parsing and schema-validation path.
- `aurum` **fails rarely.** A partner that never fails would let an implementation hard-code it as a fallback.



### 4.1 Failure response bodies

Adapters must be tested against these exact shapes.

**HTTP 500 / 503**

```json
{ "error": "service_unavailable", "retry_after": 30 }
```

**Malformed body** — HTTP 200, valid JSON, wrong shape:

```json
{ "partner": "dorsal", "premium": "unavailable" }
```

**Non-JSON body** — HTTP 200, HTML:

```html
<html><body><h1>502 Bad Gateway</h1></body></html>
```

**Connection error** — HTTP 200 declaring `Content-Length: 1024` with an empty body. A connection cannot be refused once accepted, so this truncated transfer is the failure shape (SIM-2). The client sees a transport error rather than an HTTP response.

The `retry_after` field is present and is **ignored**. The system does not retry (ADR-005).

---



## 5. Determinism and Test Control

Acceptance criteria need a partner to time out, or return a malformed body, on demand. The behaviour profiles in section 4 are probabilistic, so two control mechanisms sit on top of them.

### 5.1 Seeding

A `PARTNER_SIMULATION_SEED` configuration value seeds the generator used for latency and failure.

- **Set:** behaviour is fully reproducible. The same seed, partner, and request produce the same outcome (SIM-1: seeding is per call, not per process).
- **Unset:** behaviour is unpredictable per call. This is the dev and demo mode, where partner problems should be surprising.

The seed never touches pricing. A seed change must not alter a single price.

### 5.2 Forced behaviour

Each partner accepts an override that pins its next outcome, bypassing all probabilities:

```
PARTNER_AURUM_FORCE=ok|slow|timeout|http_error|malformed|non_json|connection_error
```

This is what acceptance tests use. It is a test seam, not a feature: it is available only when the environment is `test` or `dev`, and an override present in `prod` must fail startup rather than be silently ignored.

### 5.3 Concurrency constraint

The simulator may implement latency as a plain sleep in its request handler, because each partner call is a separate HTTP request served by its own worker process. Two constraints follow from that.

The simulator's worker pool must be sized for at least as many concurrent requests as there are partners. A pool smaller than four turns concurrent calls into a queue, and the symptom is a timeout.

The API must dispatch concurrently. The mechanism is in `03-architecture.md`. Four partners at 1.500 ms each must resolve in under 2.000 ms of wall clock.

---



## 6. Test Vectors

Pinned to a reference date of **2026-08-31**. Prices are in euros, before any campaign discount.

### 6.1 Vector A — urban low-mileage sedan


| Input         | Value                |
| ------------- | -------------------- |
| Date of birth | 1990-05-14 (age 36)  |
| Postal code   | 28013 (high-density) |
| Category      | sedan                |
| Usage         | private              |
| Mileage       | 5k_15k               |
| Garage        | yes                  |
| Coverage      | third_party_plus     |



| Partner   | Calculation                                          | Price    |
| --------- | ---------------------------------------------------- | -------- |
| `celeris` | 400 × 0.90 × 1.00 × 1.00 × 1.00 × 0.90 × 1.18 × 1.25 | **€478** |
| `bastion` | 360 × 1.00 × 1.00 × 1.00 × 1.00 × 1.00 × 1.00 × 1.35 | **€486** |
| `aurum`   | 420 × 1.00 × 1.00 × 1.00 × 1.00 × 0.93 × 1.12 × 1.30 | **€569** |
| `dorsal`  | 480 × 1.00 × 1.00 × 1.00 × 1.00 × 0.95 × 1.05 × 1.28 | **€613** |




### 6.2 Vector B — rural high-mileage commercial van


| Input         | Value               |
| ------------- | ------------------- |
| Date of birth | 1981-03-02 (age 45) |
| Postal code   | 15001 (standard)    |
| Category      | van                 |
| Usage         | commercial          |
| Mileage       | over_30k            |
| Garage        | no                  |
| Coverage      | comprehensive       |



| Partner   | Calculation                                          | Price      |
| --------- | ---------------------------------------------------- | ---------- |
| `dorsal`  | 480 × 1.00 × 1.00 × 1.20 × 1.00 × 1.00 × 1.00 × 1.70 | **€979**   |
| `celeris` | 400 × 0.90 × 1.20 × 1.25 × 1.25 × 1.00 × 0.98 × 1.60 | **€1.058** |
| `aurum`   | 420 × 1.00 × 1.25 × 1.30 × 1.30 × 1.00 × 1.00 × 1.75 | **€1.553** |
| `bastion` | 360 × 1.00 × 1.35 × 1.45 × 1.40 × 1.00 × 1.00 × 1.95 | **€1.924** |


**The ordering fully inverts between the two vectors.** `bastion` moves from second-cheapest to most expensive and `dorsal` from most expensive to cheapest. Both vectors are regression tests: if a factor table changes, this inversion is what breaks first.

---



## 7. Configuration

Partners are declared in configuration, not in code. Adding a partner must not require editing the comparison.

Configuration splits across the two containers, along the same boundary as the code.

**API side** — who exists and how to reach them. Nothing about pricing.

```yaml
partners:
    aurum:
        display_name: 'Aurum Direct'
        enabled: true
        path: '/sim/partners/aurum/quote'
        timeout_ms: 2000
    bastion:
        display_name: 'Bastion Insurance'
        enabled: true
        path: '/sim/partners/bastion/quote'
        timeout_ms: 2000
```

**Simulator side** — how each partner behaves. Base premium sits here with the factor tables it multiplies.

```yaml
simulated_partners:
    aurum:
        latency_ms: [80, 250]
        spike: null
        failures:
            http_error: 0.01
    bastion:
        latency_ms: [1200, 1900]
        spike: null
        failures:
            connection_error: 0.02
```

Factor tables live in the simulator, in typed code, one class per partner. They are the partner's underwriting logic and belong on the partner's side of the boundary, never in `Infrastructure/Partner`. They also change as a set, and a typo in a YAML factor table is much harder to catch than one in typed code.

`enabled: false` removes a partner from the comparison entirely. It does not appear in the per-partner status list and is not counted as a failure.

---



## 8. Adding a Partner

The acceptance criterion in US-07 is that a fifth partner requires no change to existing behaviour. Concretely:

1. Add a pricing engine class in the simulator with its factor table.
2. Add a simulator-side configuration entry.
3. Add an API-side configuration entry (display name, path, timeout). The registry reads this list from configuration; there is no per-partner adapter class and no partner tag.
4. Add its factor table to this document, and one test vector.

No orchestration code, no API code and no frontend code changes. No existing test changes.

---


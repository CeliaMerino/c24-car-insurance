# API Contract

**Status:** Draft
**Related documents:** `01-product-spec.md`, `03-architecture.md`, `04-providers.md`

---

## 1. Conventions

- Base path `/api/v1`. JSON in, JSON out, UTF-8.
- Field names are `snake_case`.
- Money is always an integer number of cents, never a decimal or a formatted string. Currency is stated separately.
- Enum values are lowercase identifiers, never display text. Display text is the frontend's job.
- Errors use `application/problem+json` (RFC 9457).
- Every response carries `Cache-Control: no-store`. Comparisons contain personal input and are never cacheable.

---

## 2. `POST /api/v1/comparisons`

Runs one comparison. POST rather than GET because the request carries a date of birth and a postal code, which have no business appearing in a URL, an access log, or a browser history entry.

### 2.1 Request

```json
{
  "date_of_birth": "1990-05-14",
  "postal_code": "28013",
  "car_category": "sedan",
  "usage": "private",
  "annual_mileage": "5k_15k",
  "garage": true,
  "coverage": "third_party_plus"
}
```

| Field | Type | Rule |
|---|---|---|
| `date_of_birth` | string, `YYYY-MM-DD` | Required. Age ≥ 18 at request time. Not in the future. |
| `postal_code` | string | Required. Exactly five digits, `01000`–`52999`. |
| `car_category` | enum | Required. `compact`, `sedan`, `suv`, `van`. |
| `usage` | enum | Required. `private`, `commercial`. |
| `annual_mileage` | enum | Required. `under_5k`, `5k_15k`, `15k_30k`, `over_30k`. |
| `garage` | boolean | Required. |
| `coverage` | enum | Required. `third_party`, `third_party_plus`, `comprehensive`. |

Unknown fields are rejected rather than ignored, so that a client sending a misspelled field learns about it instead of silently getting a default.

### 2.2 Response — 200

```json
{
  "comparison_id": "01K3XQ7YB4ZC8N2M5V9R1T6WGD",
  "coverage": "third_party_plus",
  "currency": "EUR",
  "duration_ms": 2014,
  "offers": [
    {
      "partner": "celeris",
      "partner_display_name": "Celeris Seguros",
      "base_annual_premium_cents": 47800,
      "final_annual_premium_cents": 47800,
      "campaign": null
    },
    {
      "partner": "aurum",
      "partner_display_name": "Aurum Direct",
      "base_annual_premium_cents": 56900,
      "final_annual_premium_cents": 48365,
      "campaign": {
        "label": "CHECK24 pays 15%",
        "percentage": 15
      }
    }
  ],
  "partners": [
    { "partner": "aurum",   "status": "ok",      "duration_ms": 210 },
    { "partner": "bastion", "status": "timeout", "duration_ms": 2000 },
    { "partner": "celeris", "status": "ok",      "duration_ms": 143 },
    { "partner": "dorsal",  "status": "error",   "duration_ms": 312 }
  ]
}
```

**`offers`** contains only partners that returned a usable price, ordered by `final_annual_premium_cents` ascending, tie-broken by `partner` alphabetically.

**`partners`** always contains one entry per enabled partner, ordered by `partner` alphabetically so the array is stable across requests. Status is one of `ok`, `timeout`, `error`, `skipped`, as defined in `04-providers.md`. The frontend does not render this in v1 (ADR-007); it exists so the decision stays reversible without a backend change, and it is the source for the per-partner metrics.

**`base_annual_premium_cents`** is what the partner quoted. **`final_annual_premium_cents`** is what the customer pays. They are equal when no campaign applies. `campaign` is `null` or an object; it is never an empty object.

**`comparison_id`** is a ULID. It appears in every log line for this comparison and is what ties a customer report to a trace.

### 2.3 Response — 200 with no offers

A comparison in which every partner failed is a **successful comparison with an empty result**, not an error.

```json
{
  "comparison_id": "01K3XQ8M2E5F7H9J1K3L5N7P9R",
  "coverage": "comprehensive",
  "currency": "EUR",
  "duration_ms": 3002,
  "offers": [],
  "partners": [
    { "partner": "aurum",   "status": "error",   "duration_ms": 88 },
    { "partner": "bastion", "status": "timeout", "duration_ms": 2000 },
    { "partner": "celeris", "status": "timeout", "duration_ms": 2000 },
    { "partner": "dorsal",  "status": "error",   "duration_ms": 145 }
  ]
}
```

Returning 5xx here would be wrong twice over: the API did its job, and it would poison the backend error rate with partner failures, which is exactly the confusion the per-partner status exists to prevent.

### 2.4 Response — 422, validation failed

```json
{
  "type": "https://check24.example/problems/validation-error",
  "title": "Validation failed",
  "status": 422,
  "errors": [
    {
      "field": "date_of_birth",
      "code": "min_age",
      "message": "You must be at least 18 years old."
    },
    {
      "field": "postal_code",
      "code": "format",
      "message": "Enter a five-digit postal code."
    }
  ]
}
```

Every invalid field is reported in one response. The API never stops at the first error, because the frontend reveals all errors at once and cannot do that from a partial list.

`code` is stable and machine-readable; `message` is human text that may change. The frontend keys its behaviour on `code`.

| `code` | Meaning |
|---|---|
| `required` | Field absent or null |
| `format` | Wrong shape |
| `min_age` | Under 18 |
| `future_date` | Date of birth in the future |
| `unknown_value` | Not a member of the enum |
| `unknown_field` | Field not in the contract |

### 2.5 Response — 400, malformed request

Body is not valid JSON, or `Content-Type` is not `application/json`. Distinct from 422: 400 means the request could not be read, 422 means it was read and its contents are wrong.

### 2.6 Response — 500

The API itself failed. This status is reserved for platform faults and must never be produced by a partner failure. The distinction is load-bearing for the `Backend 5xx rate` alert in `07-observability.md`.

---

## 3. Operational Endpoints

| Endpoint | Purpose |
|---|---|
| `GET /health` | Liveness. 200 and a minimal body. Does not call partners. |
| `GET /metrics` | Prometheus exposition format. Not exposed publicly. |

`GET /health` deliberately does not check partner reachability. A health check that fails because a third party is down will get the API restarted for someone else's outage.

---

## 4. Simulator Endpoints

Internal, served only by the simulator container, never reachable from the SPA.

### `POST /sim/partners/{partner}/quote`

Takes the same body as section 2.1. Returns one of the shapes in `04-providers.md` section 2.2 or 4.1, depending on that partner's behaviour profile and the outcome drawn for this call.

```json
{
  "partner": "aurum",
  "coverage": "third_party_plus",
  "annual_premium_cents": 56900,
  "currency": "EUR"
}
```

The simulator applies no campaign discounts. Campaigns are CHECK24's, not the partner's, and a simulator that knew about them would have leaked platform logic into a third party.

---

## 5. Contract Stability

The `v1` prefix exists so the multi-page funnel can arrive without breaking anything. Two changes are already anticipated and neither needs a new version:

- **More fields in the request.** New optional fields are additive. The funnel adds screens, not necessarily fields.
- **Streaming results.** ADR-011 defers server-sent events. When it arrives it is a new endpoint alongside this one, not a change to this one.

A change that removes a field, renames one, or changes the meaning of a status value requires `v2`.

---

## 6. Open Decisions

| Question | Assumption taken |
|---|---|
| Should the response include a partner's coverage details or policy terms? | No. Coverage is chosen by the customer and identical across partners, so per-offer terms would be noise. |
| Should `partners` be omitted when every partner returned `ok`? | No. A field that disappears on the happy path is harder to consume than one that is always present. |
| Should the API rate-limit comparisons? | Not in v1. Noted as a production requirement, since the endpoint fans out to four external calls and is trivially abusable. |
| Should validation errors be localised? | No. English only (PRD non-goals). |

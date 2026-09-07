# Observability Specification

**Status:** Draft
**Related documents:** `01-product-spec.md` section 9, `03-architecture.md`, `05-api-contract.md`, `06-testing.md`, [`c24-api.postman_collection.json`](c24-api.postman_collection.json)

---

## 1. What This Is For

After launch the team needs to answer four questions in under a minute:

1. Is the comparison working?
2. Is it fast enough?
3. Which partner is the problem?
4. Are customers getting through the form?

Everything specified below exists to answer one of those. A metric that answers none of them is not implemented, however easy it would be to emit.

Each metric in section 3 states the action it triggers. That column is the point of the document. A metric with no action attached is a number nobody reads twice.

---

## 2. The PHP Constraint

**Prometheus metrics in PHP do not work the way they do in a long-lived process.** Each FrankenPHP worker has its own memory. A counter incremented in worker 3 is invisible to a scrape answered by worker 1, so an in-memory registry produces numbers that jump around and undercount by a factor of the worker count.

The client library must therefore use a shared storage adapter. `promphp/prometheus_client_php` with the **APCu** adapter is sufficient here: shared across workers in one container, no extra infrastructure, and lost on restart, which is acceptable because Prometheus is the durable store.

This is the same class of problem as the circuit breaker's per-worker state (ADR-006), and it has the same shape of consequence: the naive implementation looks correct in a single-worker test environment and is wrong under load.

An implementation that registers metrics in a plain in-memory registry has a bug, not a simplification.

---

## 3. Metrics

Namespace `c24`. Durations in seconds, per Prometheus convention, formatted as milliseconds only in the dashboard.

### 3.1 API-emitted

| Metric | Type | Labels | Answers | Action when it moves |
|---|---|---|---|---|
| `c24_comparisons_total` | Counter | `coverage` | Usage volume, coverage mix | A tier nobody selects is priced wrong or explained badly. Tell product and marketing. |
| `c24_comparison_duration_seconds` | Histogram | — | Is it fast enough | p95 over 3.1s: look at per-partner p95 before touching platform code. |
| `c24_comparison_offers` | Histogram | — | Result completeness | The ≥3-offer share dropping means a partner is regularly absent. Find which, escalate to partner management. |
| `c24_partner_requests_total` | Counter | `partner`, `status` | Which partner is the problem | Success rate under 90% for one partner: notify partner management, expect the breaker. |
| `c24_partner_request_duration_seconds` | Histogram | `partner` | Whose latency is eating the deadline | A partner whose p95 approaches 2s is about to start timing out. Act before it does. |
| `c24_circuit_breaker_opened_total` | Counter | `partner` | Sustained partner failure | Repeated opening for one partner is a commercial conversation, not a code change. |
| `c24_validation_errors_total` | Counter | `field`, `code` | Where the form loses people | One field dominating means the field is confusing, not that customers are careless. Rewrite the label or the input. |
| `c24_campaign_applied_total` | Counter | `partner` | Did marketing's campaign reach anyone | Zero while a campaign is configured means the validity window or the config is wrong. |
| `c24_http_responses_total` | Counter | `route`, `status` | Platform health | 5xx rate over 0.5%: page on-call. Partner failures never appear here (`05-api-contract.md` section 2.6). |

### 3.2 Frontend-emitted

Three things only the browser knows. They arrive through one endpoint.

**`POST /api/v1/events`** — example requests are in [`c24-api.postman_collection.json`](c24-api.postman_collection.json).

```json
{ "event": "form_started" }
```

Accepted values: `form_started`, `form_restored`, `results_viewed`. Anything else is a 422. The endpoint carries no identifiers, no payload, and no free text, so it cannot become an accidental analytics pipeline or a personal-data path.

It increments `c24_frontend_events_total{event}`.

| Derived metric | From | Action |
|---|---|---|
| Form completion rate | `form_started` → `c24_comparisons_total` | Under 60%: read `c24_validation_errors_total` by field to find what is blocking. |
| Results view rate | `c24_comparisons_total` → `results_viewed` | A gap means people are leaving during the wait. Latency problem, not a form problem. |
| Restore rate | `form_restored` / `form_started` | Rising means people are reloading, which is worth understanding rather than celebrating. |

### 3.3 Histogram buckets

Buckets are chosen around the limits that matter, not on a round-number scale. The interesting region is 1.5s to 3s, because that is where the per-partner timeout and the global deadline sit.

```
c24_comparison_duration_seconds:
  0.1, 0.25, 0.5, 1.0, 1.5, 2.0, 2.5, 3.0, 3.5, 5.0

c24_partner_request_duration_seconds:
  0.05, 0.1, 0.25, 0.5, 1.0, 1.5, 2.0, 2.5, 3.0

c24_comparison_offers:
  0, 1, 2, 3, 4
```

The 2.0 and 3.0 boundaries are deliberate. They make "how many comparisons hit the per-partner timeout" and "how many hit the global deadline" single bucket queries rather than estimates.

### 3.4 Cardinality

Never a label: `comparison_id`, postal code, date of birth, age, any free text, or any value the customer supplies that is not a closed enum. `partner`, `status`, `coverage`, `field` and `code` are all bounded and small.

---

## 4. Logs

Structured JSON through Monolog. One line is not prose with numbers in it; it is fields.

**Every line carries `comparison_id`** — the ULID from `05-api-contract.md` section 2.2. That is what turns a customer saying "it showed me two offers this morning" into a specific trace.

| Level | When | Fields beyond the standard ones |
|---|---|---|
| INFO | One line per completed comparison | `comparison_id`, `duration_ms`, `offer_count`, `coverage`, `partner_statuses` |
| WARNING | Per partner failure | `comparison_id`, `partner`, `status`, `duration_ms`, `http_status` |
| WARNING | Circuit breaker opens or closes | `comparison_id`, `partner`, `new_state` |
| ERROR | Platform fault only | `comparison_id`, exception, stack trace |

**Never logged:** date of birth, postal code, or any other field of the quote request. Coverage level is fine, since it is not personal. This is not incidental tidiness — the same reasoning is in R2 and ADR-009, and a log aggregator is a much longer-lived store than `localStorage`.

A partner failure is a WARNING and never an ERROR. If partner failures log at ERROR, the error log stops being a signal within a day.

---

## 5. Alerts

Five. Each one names what the person receiving it should do, because an alert that does not is a notification.

| Alert | Condition | For | Severity | Do this |
|---|---|---|---|---|
| Partner degraded | Success rate for one partner < 90% | 10m | warning | Notify partner management. Confirm the breaker opened. Do not change platform code. |
| Comparison slow | p95 of `c24_comparison_duration_seconds` > 3.1s | 5m | warning | Check per-partner p95 first. It is almost always one partner. |
| Empty results | Share of comparisons with 0 offers > 1% | 10m | critical | Several partners are down at once, or the simulator is unreachable. Customer-visible. |
| Platform errors | 5xx rate > 0.5% | 5m | critical | Page on-call. This is ours, not a partner's. |
| No traffic | `c24_comparisons_total` flat during business hours | 15m | critical | Nothing is failing, which is the point. A deploy has broken the form or the SPA cannot reach the API. |

The last one is the one that gets left out and the one that catches the worst outage. Every other alert fires because something is going wrong; this one fires because nothing at all is happening, which no error-rate metric can detect.

Alert rules live in `ops/prometheus/rules.yml`, in the repository, reviewed like code.

---

## 6. Setup

### 6.1 Scrape configuration

`ops/prometheus/prometheus.yml`:

```yaml
global:
  scrape_interval: 15s
  evaluation_interval: 15s

rule_files:
  - /etc/prometheus/rules.yml

scrape_configs:
  - job_name: 'comparison-api'
    metrics_path: /metrics
    static_configs:
      - targets: ['api:80']
```

The simulator is not scraped. It stands in for third parties, and a real partner would not expose metrics to us. Partner behaviour is measured from our side, by `c24_partner_requests_total`, which is the only view we would actually have in production.

### 6.2 Grafana

Provisioned from files in the repository, never configured by hand:

```
ops/grafana/provisioning/datasources/prometheus.yml
ops/grafana/provisioning/dashboards/dashboards.yml
ops/grafana/dashboards/comparison.json
```

Anonymous access with Admin role is enabled in `docker-compose.yml` so a reviewer can open the dashboard without credentials. That is a deliberate choice for an evaluation environment and is called out in the README as unsuitable for production.

### 6.3 Dashboard layout

One dashboard, four rows, in the order the questions get asked.

**Row 1 — Is it working?**
- Comparisons per minute (`rate(c24_comparisons_total[5m])`)
- Share of comparisons by offer count, stacked (0 / 1–2 / 3 / 4)
- Empty result rate, as a single stat with a threshold at 1%

**Row 2 — Is it fast?**
- p50, p95 and p99 of comparison duration, one graph, three lines
- Per-partner p95 latency, one line per partner, with a marker at 2s

**Row 3 — Which partner?**
- Success rate per partner, one line each, threshold at 90%
- Partner outcomes stacked by status (`ok`, `timeout`, `error`, `skipped`)
- Circuit breaker opens, per partner

**Row 4 — Are customers getting through?**
- Funnel: form started → comparison run → results viewed
- Validation errors by field, top 5
- Coverage tier distribution

Row 2 shows percentiles and no average. An average of 1.1s can hide that one comparison in twenty takes the full three seconds, and the tail is the thing this product was built to survive. Average result loading time is worth watching only as a trend line next to p95, never on its own.

---

## 7. Using It Daily

The metrics exist to be acted on, so the routine is part of the specification.

**Every morning, three questions, five minutes.** Row 1 for whether yesterday looked like the day before. Row 3 for whether any partner's success rate is drifting down rather than falling off a cliff, since drift is what nobody notices. Row 4 for whether one validation field has started dominating, which usually means a copy change went out.

**Every week, one question.** Is the 3-second deadline still the right number? Row 2's per-partner p95 answers it. If a partner sits reliably at 2.4s, the deadline is excluding a healthy partner and R3 in the PRD has become real. If every partner is under 800ms, the deadline is looser than it needs to be and the customer is waiting for a ceiling nobody reaches.

**Whenever a campaign launches.** `c24_campaign_applied_total` should move within minutes. If it does not, the campaign is configured wrong and marketing is spending nothing rather than something.

---

## 8. Deliberately Not Included

- **Distributed tracing.** Four HTTP calls from one service. `comparison_id` in the logs answers the same questions at a fraction of the setup cost. Worth adding when a second service enters the request path.
- **Real user monitoring.** Page load and Core Web Vitals matter for a comparison funnel, but they need a separate collector and answer a different question from the four in section 1.
- **Business dashboards.** Conversion to purchase is out of scope because purchase is out of scope (PRD non-goals).
- **Log-based metrics.** Everything countable is already a counter. Deriving metrics from log parsing would give two sources of truth that disagree.

---

## 9. Open Decisions

| Question | Assumption taken |
|---|---|
| APCu or Redis for the metrics adapter? | APCu. Redis is the right answer when the API runs in more than one container, and that is the same trigger that forces the circuit breaker to Redis. |
| Should the frontend send timing data as well as events? | Not in v1. Server-side duration is measured accurately; browser-side timing needs RUM to be meaningful. |
| Should alerts route anywhere in this exercise? | No. Rules are defined and visible in Prometheus; no Alertmanager receiver is configured, since there is nobody to page. |
| Retention? | Prometheus default. Nothing here needs long history to be useful. |

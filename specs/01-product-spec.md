# Product Requirements Document — Car Insurance Comparison

**Status:** Draft
**Owner:** Celia Merino Valladolid
**Related documents:** `02-decisions/` (ADRs), `03-architecture.md`, `04-providers.md`, `05-api-contract.md`, `06-testing.md`, `07-observability.md`

---

## 1. Introduction and Objectives

### 1.1 Context

A customer looking for car insurance today has to visit each insurer separately, fill in the same details repeatedly, and manually line up prices that are presented in different formats. The comparison never happens at a single point in time, so the customer is never certain they are comparing like with like.

This product gives the customer one short form and one screen of comparable offers, retrieved from several insurance partners at once.

### 1.2 Purpose of this document

This PRD defines **what** the product does and **why**. It is written to be read in two ways:

- By a human reviewer, to understand the product decisions and the tradeoffs behind them.
- By an AI implementation agent, as the source of functional truth. Where a decision is not stated here, it is stated in an ADR under `02-decisions/` and linked from the relevant section.

### 1.3 Objectives


| #   | Objective                                                                         | How we know we met it                                                                                                                             |
| --- | --------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------- |
| O1  | The customer gets comparable offers from several partners in a single interaction | A submitted form returns offers from multiple partners on one screen, in one price format, all quoted at the coverage level the customer selected |
| O2  | The experience stays usable when partners misbehave                               | A comparison in which some partners fail still returns offers, within the global deadline                                                         |
| O3  | The customer never loses form input to a page reload                              | Input is restored after a reload, with the customer aware it was restored                                                                         |
| O4  | Adding a new partner does not require changing the comparison logic               | A new partner is added by adding one adapter and one configuration entry                                                                          |
| O5  | The team can tell whether the comparison is healthy after launch                  | Latency, partner health and funnel metrics are visible on a dashboard from day one                                                                |




### 1.4 Non-goals

Explicit exclusions. An implementation agent must not add these, and must not add plausible neighbours of these.

- **Real tariffs.** Prices are simulated. No actuarial model, no real risk calculation.
- **Legal and regulatory compliance.** No IDD requirements, no pre-contractual information, no policy documents.
- **Purchase.** No checkout, no policy issuing, no payment, no partner handover.
- **Accounts.** No registration, no login, no saved comparisons across devices.
- **Real partner integrations.** Partners are simulated inside the project and treated as external systems.
- **Persistence of comparisons.** Nothing is written to a database. The system is stateless between requests.
- **Internationalisation.** English only, EUR only, Spanish postal codes only.
- **Admin interface for campaigns.** Campaigns are configuration, not a managed feature.

---



## 2. Stakeholders

Some of these are notional for the exercise.


| Stakeholder         | Interest                                                                 | Consequence in this product                                                   |
| ------------------- | ------------------------------------------------------------------------ | ----------------------------------------------------------------------------- |
| Customer            | Get a trustworthy price comparison quickly, without repeating work       | Short form, input restored on reload, results within a predictable deadline   |
| Product / Marketing | Run campaigns where CHECK24 subsidises part of the price                 | Campaign discounts are configuration, per partner, with a validity window     |
| Partner management  | Know which partners are slow, failing, or degrading the funnel           | Per-partner status returned by the API and exposed as metrics                 |
| Engineering         | Add and change partners without destabilising the comparison             | Partner behaviour isolated behind an adapter boundary                         |
| On-call / SRE       | Know quickly whether the comparison is degraded and why                  | Latency percentiles and per-partner health with defined alert thresholds      |
| Insurance partners  | Be represented accurately, and not be blamed for the platform's failures | Partner errors are attributed per partner, never surfaced as a platform error |


---



## 3. Users and Scenario



### 3.1 Primary persona

**The price-checking driver.** An adult with a car, comparing insurance because renewal is coming up or they have just bought a vehicle. They are price-sensitive, in a hurry, and often on a phone. They will abandon a form that asks for more than they expected to give.

### 3.2 Secondary users

The partner management and on-call teams are users of the observability surface, not of the customer interface. Their needs are specified in `07-observability.md`.

### 3.3 Scenario

The customer opens the comparison page on a phone. They enter their date of birth, postal code, four details about the car, and the level of coverage they want. They submit and wait a couple of seconds. They see a list of annual prices from different insurers, cheapest first, with any campaign discount clearly shown. They compare, and they leave.

---



## 4. Main Components and Sitemap



### 4.1 Sitemap

Version 1 is a single route with three view states.

```
/  Comparison
   ├── State A: Form          (default, and after a validation failure)
   ├── State B: Loading       (from submit until the response resolves)
   └── State C: Results       (offers, or the empty state)
```

The form is authored as **two logical steps rendered on one page**, so that the later multi-page funnel is a routing and layout change rather than a rewrite. See ADR-008.

```
Step 1 — Driver      date of birth, postal code
Step 2 — Vehicle     category, usage, annual mileage, garage
Step 3 — Coverage    coverage level
```



### 4.2 Domain concepts


| Concept            | Definition                                                                                          |
| ------------------ | --------------------------------------------------------------------------------------------------- |
| **Quote request**  | The set of customer inputs, validated, that is sent to every partner                                |
| **Partner**        | A simulated external insurer that returns a price for a quote request                               |
| **Offer**          | One partner's response: a partner identity and a base annual price for the requested coverage level |
| **Coverage level** | The protection tier the customer selects. Every partner quotes the same requested level.            |
| **Campaign**       | A configured discount that CHECK24 applies on top of a partner's price                              |
| **Comparison**     | The result of one quote request: a set of offers plus a per-partner status                          |
| **Deadline**       | The maximum wall-clock time the customer waits for the whole comparison                             |




### 4.3 Ownership of pricing

**The platform does not calculate prices.** Each partner computes its own price from the quote request, using its own weighting of the inputs. The platform validates input, dispatches, collects, applies campaign discounts, sorts, and returns. See ADR-002.

---



## 5. Features and Functional Requirements



### 5.1 The form


| ID  | Field          | Type   | Validation                                                                           |
| --- | -------------- | ------ | ------------------------------------------------------------------------------------ |
| F1  | Date of birth  | Date   | Required. Customer must be 18 or older on the date of the request. No upper limit.   |
| F2  | Postal code    | Text   | Required. Five digits, valid Spanish range.                                          |
| F3  | Car category   | Select | Required. One of: compact, sedan, SUV, van.                                          |
| F4  | Usage          | Radio  | Required. Private or commercial.                                                     |
| F5  | Annual mileage | Select | Required. One of: under 5.000 km, 5.000–15.000 km, 15.000–30.000 km, over 30.000 km. |
| F6  | Private garage | Radio  | Required. Yes or no.                                                                 |
| F7  | Coverage level | Radio  | Required. One of: third party, third party plus, comprehensive.                      |


**Coverage levels.** Three tiers, fixed for v1:


| Value              | Meaning shown to the customer                               |
| ------------------ | ----------------------------------------------------------- |
| `third_party`      | Damage caused to others                                     |
| `third_party_plus` | Damage caused to others, plus theft, fire and glass         |
| `comprehensive`    | All of the above, plus damage to the customer's own vehicle |


Coverage is presented as selectable cards rather than a dropdown, each with its one-line meaning, because it is the only field where the customer is choosing a product rather than describing a fact.

Mileage is a range rather than a free number so that validation stays trivial and the test matrix stays finite. See ADR-004.

**Validation behaviour.** Fields validate on blur and again on submit. The submit button is always enabled; pressing it with invalid input reveals all errors at once and moves focus to the first invalid field. A disabled submit button hides from the customer what is wrong.

Server-side validation mirrors client-side validation exactly and is authoritative.

### 5.2 The comparison

Every valid submission is dispatched to all configured partners concurrently. Every partner quotes the coverage level the customer requested, so prices on the results screen are always like for like. The results screen names that level above the list.

All partners offer all three levels in v1. A partner declining a level would turn a product mismatch into something the customer sees as a missing offer, and would blur the partner-failure metrics. See ADR-013.


| Rule                | Value                                                                | Rationale                                                              |
| ------------------- | -------------------------------------------------------------------- | ---------------------------------------------------------------------- |
| Per-partner timeout | 2.000 ms                                                             | A partner slower than this is not worth the customer's wait            |
| Global deadline     | 3.000 ms                                                             | The whole comparison resolves within this, whatever the partners do    |
| Retries             | None                                                                 | A retry rarely fits inside the remaining budget. See ADR-005.          |
| Circuit breaker     | Opens after 3 consecutive failures per partner, half-open after 30 s | A partner known to be down must not consume the deadline. See ADR-006. |
| Sorting             | Final price ascending                                                | The customer's question is which is cheapest                           |
| Tie-break           | Partner name, alphabetically                                         | Deterministic order, so results are stable and testable                |




### 5.3 Partial and empty results

If some partners fail or time out, the customer sees the offers that arrived and **nothing else** — no error banner, no partner count, no explanation. See ADR-007.

The API nonetheless always returns the status of every partner (`ok`, `timeout`, `error`, `skipped`). The client chooses not to display it in v1. This keeps the decision reversible without a backend change, and the same field feeds the observability metrics.

If **no** partner returns an offer, the customer sees an empty state with a clear message and a retry action. Showing an empty screen without explanation would be worse than naming the failure.

### 5.4 Campaign discounts

- A campaign is configured **per partner**, as a percentage, with a start and end date.
- The discount is applied by the backend, after the partner's price is received. The partner's price is never modified.
- The response carries both the base price and the final price, plus a campaign label.
- The client renders discount presentation only. It never calculates a discount.

An offer with an active campaign shows the base price struck through, the final price prominently, and a label naming the campaign.

### 5.5 Input persistence

Form input is written to `localStorage` on change, under a versioned key, with a 24-hour expiry.

On load, if valid unexpired input is found, the form is repopulated and a dismissible notice tells the customer their previous input was restored. Restoring silently would be worse than not restoring: a customer who does not know why the fields are filled does not trust them.

Stored data includes date of birth and postal code, which are personal data. The expiry, the versioned key, and an explicit clearing action exist for that reason, and the tradeoff is recorded in ADR-009.

### 5.6 Responsive behaviour

Mobile is the primary layout and is built first. The breakpoint is single, at 768 px.

- Below the breakpoint: single column, offers stack vertically, form fields full width.
- Above: the form may use two columns, offers remain a vertical list so prices stay aligned for scanning.

---



## 6. User Stories and Acceptance Criteria



### US-01 — Get a comparison

> As a driver, I want to enter my details once and see prices from several insurers, so that I do not have to visit each insurer myself.

```gherkin
Scenario: A valid submission returns offers from all healthy partners
  Given all four partners are healthy
  When I submit a valid form
  Then I see one offer per partner
  And every offer shows an annual price in EUR
  And offers are ordered by final price ascending
```

```gherkin
Scenario: Coverage level changes the prices quoted
  Given all four partners are healthy
  When I submit the same form twice, once at third party and once at comprehensive
  Then every partner's comprehensive price is higher than its third party price
```

```gherkin
Scenario: Two partners return the same final price
  Given two partners return an identical final price
  When the results are displayed
  Then those two offers are ordered alphabetically by partner name
```



### US-02 — Be protected from partner problems

> As a driver, I want to see the prices that are available, so that a broken insurer does not waste my time.

```gherkin
Scenario: A slow partner does not delay the comparison
  Given one partner responds after 5 seconds
  And the other three respond within 500 ms
  When I submit a valid form
  Then I receive a response in under 3.100 ms
  And I see three offers
  And no error message is displayed
```

```gherkin
Scenario: A partner returning a malformed payload is excluded
  Given one partner returns a response that does not match the expected schema
  When I submit a valid form
  Then that partner produces no offer
  And the remaining offers are displayed normally
  And the API reports that partner's status as "error"
```

```gherkin
Scenario: No partner responds
  Given all partners fail or time out
  When I submit a valid form
  Then I see an empty state explaining that no offers could be retrieved
  And I see a retry action
```

```gherkin
Scenario: A partner known to be down does not consume the deadline
  Given a partner has failed 3 consecutive times
  When I submit a valid form
  Then that partner is not called
  And its status is reported as "skipped"
  And the response completes faster than the per-partner timeout would allow
```



### US-03 — Keep my input on reload

> As a driver, I want my input to survive a reload, so that I do not have to type it again.

```gherkin
Scenario: Input is restored after a reload
  Given I have filled in five of the seven fields
  When I reload the page
  Then those four fields contain my previous values
  And I see a notice that my input was restored
```

```gherkin
Scenario: Stale input is not restored
  Given stored input is older than 24 hours
  When I open the page
  Then the form is empty
  And no restore notice is displayed
```



### US-04 — Understand the discount

> As a driver, I want to see clearly when part of the price is subsidised, so that I can trust the price I am shown.

```gherkin
Scenario: An active campaign is applied
  Given partner A has an active 15% campaign
  When I submit a valid form
  Then partner A's offer shows the base price struck through
  And it shows the final price as 85% of the base price
  And it shows the campaign label
```

```gherkin
Scenario: An expired campaign is not applied
  Given partner A has a campaign whose end date has passed
  When I submit a valid form
  Then partner A's offer shows only one price
  And no campaign label is displayed
```



### US-05 — Be told what is wrong

> As a driver, I want to know exactly which field is wrong, so that I can fix it without guessing.

```gherkin
Scenario: An underage customer is rejected
  Given I enter a date of birth that makes me 17 years old today
  When I submit the form
  Then I see a message on the date of birth field stating the minimum age is 18
  And no comparison is performed
```

```gherkin
Scenario: All errors are revealed at once
  Given three fields are invalid
  When I press submit
  Then all three errors are displayed
  And focus moves to the first invalid field
```



### US-06 — Use it on a phone

> As a driver, I want to compare on my phone, because that is where I am.

```gherkin
Scenario: The results are usable on a narrow viewport
  Given a viewport 375 px wide
  When results are displayed
  Then offers are stacked in a single column
  And no horizontal scrolling is required
```



### US-07 — Add a partner without touching the core

> As an engineer, I want to add a partner by adding an adapter, so that partner growth does not destabilise the comparison.

```gherkin
Scenario: A fifth partner is added
  Given I add one adapter class and one configuration entry
  When I run the full test suite
  Then no existing test requires modification
  And the new partner appears in the comparison
```



### US-08 — See whether the comparison is healthy

> As an on-call engineer, I want per-partner and end-to-end visibility, so that I can tell within minutes whether a degradation is ours or a partner's.

```gherkin
Scenario: Partner degradation is attributable
  Given one partner's error rate rises above the alert threshold
  When I open the dashboard
  Then I can identify which partner is failing
  And I can see that overall comparison success is unaffected
```

Full metric definitions and alert thresholds are in `07-observability.md`.

---



## 7. Design and User Experience



### 7.1 Principles

1. **Price legibility above all.** The final price is the largest element in an offer card. Everything else is secondary.
2. **Degradation is silent, absence is not.** Missing partners are invisible; zero results is stated plainly.
3. **Never a dead end.** Every failure state offers a next action.
4. **No dark patterns.** The base price is always visible when a discount applies.



### 7.2 Loading state

The wait can reach three seconds, which is long enough to need feedback. A skeleton list of offer cards is shown rather than a spinner, because it communicates what is coming and reduces layout shift on arrival.

### 7.3 States to implement

Every one of these must exist.


| State           | Trigger                        | What the customer sees                          |
| --------------- | ------------------------------ | ----------------------------------------------- |
| Empty form      | First visit                    | Form, no notice                                 |
| Restored form   | Valid stored input             | Form with values, restore notice                |
| Invalid form    | Submit with errors             | Inline field errors, focus on first             |
| Loading         | Valid submit                   | Skeleton offer cards                            |
| Full results    | All partners returned          | All offers, sorted                              |
| Partial results | Some partners returned         | Available offers only, no explanation           |
| No results      | No partner returned            | Message and retry action                        |
| Request error   | Backend 5xx or network failure | Message and retry action, form values preserved |


---



## 8. Technical Requirements

Summary only. The binding detail is in `03-architecture.md` and `05-api-contract.md`.


| Area              | Requirement                                                              |
| ----------------- | ------------------------------------------------------------------------ |
| Backend           | Symfony, PHP 8.3+                                                        |
| Frontend          | Vue 3, TypeScript, Composition API, separate SPA                         |
| Frontend state    | Composable with `reactive()`. Not Pinia in v1. See ADR-010.              |
| Partner isolation | One port, one adapter per partner, resolved from configuration           |
| Concurrency       | Partners are called concurrently, not sequentially                       |
| Determinism       | Partner failure and latency are seedable, so tests are repeatable        |
| API               | REST, JSON, single comparison endpoint                                   |
| Observability     | Prometheus metrics endpoint, Grafana dashboard, both in `docker-compose` |
| Persistence       | None. No database.                                                       |
| Environment       | `docker compose up` brings up backend, frontend, Prometheus and Grafana  |


---



## 9. Success Metrics

The point of each metric is the decision it triggers.

### 9.1 Product


| Metric                                                  | Target               | Action if breached                                                                                                |
| ------------------------------------------------------- | -------------------- | ----------------------------------------------------------------------------------------------------------------- |
| Form completion rate (started → submitted)              | ≥ 60%                | Inspect per-field validation failure rate to find the blocking field                                              |
| Comparison success rate (submitted → ≥1 offer)          | ≥ 99%                | Treat as an incident; check partner health before platform health                                                 |
| Result completeness (comparisons with ≥3 of 4 partners) | ≥ 95%                | Identify the recurring absentee and escalate to partner management                                                |
| Restore notice display rate                             | Monitored, no target | A rising rate signals reload friction worth investigating                                                         |
| Coverage level distribution                             | Monitored, no target | A tier nobody selects is either priced wrong or explained badly; also tells marketing where a campaign would land |




### 9.2 Performance


| Metric                             | Target               | Action if breached                                                      |
| ---------------------------------- | -------------------- | ----------------------------------------------------------------------- |
| End-to-end comparison latency, p95 | < 3.100 ms           | Check per-partner latency before touching platform code                 |
| End-to-end comparison latency, p50 | < 1.500 ms           | If p50 approaches p95, a partner is slow for everyone, not occasionally |
| Per-partner response latency, p95  | Per partner baseline | Renegotiate or reweight the partner                                     |


Average latency is **not** a target. It hides the tail that the timeout and deadline rules exist to handle.

### 9.3 Reliability


| Metric                      | Target            | Action if breached                                                             |
| --------------------------- | ----------------- | ------------------------------------------------------------------------------ |
| Per-partner success rate    | ≥ 95% over 15 min | Alert partner management; expect the breaker to open                           |
| Circuit breaker open events | Monitored         | Repeated opening for one partner is a relationship problem, not a code problem |
| Backend 5xx rate            | < 0.1%            | Page on-call                                                                   |


---



## 10. Risks and Dependencies


| #   | Risk                                                                                     | Impact                                                    | Mitigation                                                                                     |
| --- | ---------------------------------------------------------------------------------------- | --------------------------------------------------------- | ---------------------------------------------------------------------------------------------- |
| R1  | In-memory circuit breaker state is per PHP process, so behaviour differs across workers  | Breaker opens inconsistently under load                   | Accepted for v1 and documented in ADR-006. Production requires shared state in Redis.          |
| R2  | `localStorage` holds date of birth and postal code                                       | Personal data on a shared device                          | 24-hour expiry, versioned key, explicit clear action, documented in ADR-009                    |
| R3  | The 3-second deadline may exclude a healthy but slow partner                             | A legitimate offer is lost, and the customer never knows  | Per-partner latency is measured; the deadline is a tunable configuration value, not a constant |
| R4  | Simulated partners are too well-behaved to prove resilience                              | Failure handling looks correct but is untested            | Failure profiles are explicit and seeded; each failure mode has a dedicated test               |
| R5  | Concurrency in PHP is more constrained than in the runtimes this pattern usually assumes | Partner calls degrade to sequential and blow the deadline | Concurrency approach is fixed in `03-architecture.md` and verified by a timing test            |
| R6  | Observability scope grows without limit                                                  | Time is taken from the comparison itself                  | Only the metrics in section 9 are implemented; each has a stated action                        |




### Dependencies

- No external service dependencies. Partners are simulated in-process.
- Docker and Docker Compose are required to run the full stack including observability.

---



## 11. Implementation Phases

Ordering for the implementation agent. Each phase must be verifiable before the next begins.

1. **Domain and contracts.** Quote request, offer, partner port, validation rules. Unit tested with no infrastructure.
2. **Simulated partners.** Four adapters with distinct pricing weights and failure profiles, seeded for determinism.
3. **Comparison orchestration.** Concurrency, timeouts, deadline, per-partner status.
4. **API layer.** Endpoint, request validation, response shape, error format.
5. **Frontend.** Form with two logical steps, all eight states from section 7.3, persistence.
6. **Campaigns.** Configuration, application, presentation.
7. **Circuit breaker.** Added last; the comparison must meet its deadline without it first.
8. **Observability.** Metrics, Prometheus, Grafana dashboard.

---



## 12. Appendix



### 12.1 Glossary

See section 4.2 for domain concepts. Additional terms:


| Term                | Meaning                                                                            |
| ------------------- | ---------------------------------------------------------------------------------- |
| **Partial result**  | A comparison that returned at least one but not all partner offers                 |
| **Failure profile** | The configured way a simulated partner misbehaves: latency, error rate, error type |
| **Half-open**       | Circuit breaker state in which one trial call is allowed through to test recovery  |




### 12.2 Decision index


| ADR     | Decision                                                                            |
| ------- | ----------------------------------------------------------------------------------- |
| ADR-001 | Scope ends at the results list; no purchase flow                                    |
| ADR-002 | Pricing is owned by partners, not by the platform                                   |
| ADR-003 | Car category is a closed enum of four values                                        |
| ADR-004 | Annual mileage is captured as a range, not a number                                 |
| ADR-005 | No retries within the deadline                                                      |
| ADR-006 | In-memory circuit breaker, with production caveat                                   |
| ADR-007 | Partial results are shown without explanation                                       |
| ADR-008 | Form is authored in two logical steps, rendered on one page                         |
| ADR-009 | Form input persisted to `localStorage` despite containing personal data             |
| ADR-010 | Composable with `reactive()` instead of Pinia                                       |
| ADR-011 | Single response at deadline rather than streaming results                           |
| ADR-012 | Annual pricing in EUR                                                               |
| ADR-013 | Coverage level is a customer input with three fixed tiers, offered by every partner |




### 12.3 Open questions taken as assumptions

Questions that would go to a product manager in a real project, and the assumption taken instead.


| Question                                                               | Assumption taken                                                              |
| ---------------------------------------------------------------------- | ----------------------------------------------------------------------------- |
| Should results be filterable or sortable by anything other than price? | No. Coverage is chosen before comparing, so price is the only remaining axis. |
| May a partner decline to quote a coverage level?                       | Not in v1. All partners quote all three tiers.                                |
| Can the customer change coverage from the results screen?              | Not in v1. They return to the form, which re-runs the comparison.             |
| Should the same customer see stable prices across sessions?            | No. Prices are recalculated per request.                                      |
| Is postal code used for risk, or only for future partner routing?      | Partners may weight it. No platform-level meaning.                            |
| Can a campaign apply to all partners at once?                          | Not in v1. Campaigns are configured per partner.                              |
| What is the retention requirement for comparison data?                 | None, since nothing is persisted.                                             |



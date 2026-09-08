# Testing Strategy

---

## 1. Where Tests Come From

Every acceptance criterion in `01-product-spec.md` section 6 maps to at least one named test. Section 5 holds that map.

Tests come from those criteria, not from a coverage percentage. A percentage target rewards testing getters and says nothing about whether a partner timeout is handled.

---

## 2. Determinism

Three controls, all of them required. Resilience tests that depend on chance are flaky, and flaky tests get deleted rather than fixed.


| Control                        | Mechanism                     | Fixed at                   |
| ------------------------------ | ----------------------------- | -------------------------- |
| Time                           | `Clock` port, frozen in tests | 2026-08-31T12:00:00Z       |
| Partner behaviour, statistical | `PARTNER_SIMULATION_SEED`     | Fixed value in `.env.test` |
| Partner behaviour, exact       | `PARTNER_{ID}_FORCE`          | Per test                   |


The seed makes a whole run reproducible. The force override is what a specific test uses when it needs one named partner to time out and the others to succeed. Tests that assert on a specific failure use the override, never the seed, because a seed that produces the right outcome today stops producing it the moment an unrelated test adds a partner call.

Pricing is never affected by either control. A test that changes the seed and sees a price move has found a bug.

---



## 3. Levels


| Level                     | Covers                                                                                               | Tooling                                                | Must not                                   |
| ------------------------- | ---------------------------------------------------------------------------------------------------- | ------------------------------------------------------ | ------------------------------------------ |
| **L1 Domain**             | Value objects, age from date of birth, money arithmetic, discount calculation, sorting rules         | PHPUnit, no container                                  | Touch HTTP, config or the clock directly   |
| **L2 Simulator**          | The four pricing engines against the test vectors                                                    | PHPUnit                                                | Assert on latency or failure               |
| **L3 Adapter**            | HTTP response parsing, schema validation, outcome mapping to `ok`/`timeout`/`error`                  | PHPUnit + `MockHttpClient`                             | Make a real network call                   |
| **L4 Application**        | Orchestration: ordering, discount-then-sort, deadline handling, breaker transitions, partial results | PHPUnit + fake gateway                                 | Depend on real timing                      |
| **L5 API**                | The contract in `05-api-contract.md`: status codes, body shape, validation errors                    | Symfony `WebTestCase`, real simulator, forced outcomes | Assert on exact prices except via a vector |
| **L6 Timing**             | Real concurrency and real timeouts                                                                   | PHPUnit, real HTTP, production timeout values          | Run on every commit if it slows the suite  |
| **L7 Frontend unit**      | Composables: validation rules, storage read/write/expiry, step definitions                           | Vitest                                                 | Render components                          |
| **L8 Frontend component** | The eight states in `01-product-spec.md` section 7.3                                                 | Vitest + Vue Test Utils, stubbed API                   | Call the real API                          |
| **L9 End to end**         | Full stack, happy path and partial results                                                           | Playwright                                             | Be the only place a rule is tested         |


L1 through L4 are the bulk and should run in under a second in total. Anything slow is at L5 and above.

---



## 4. Keeping the Suite Fast

The real timeouts are 2.000 ms and 3.000 ms. A dozen tests exercising them adds thirty seconds to every run, which is how a suite stops being run.

`.env.test` overrides them:

```
PARTNER_TIMEOUT_MS=200
COMPARISON_DEADLINE_MS=300
CIRCUIT_BREAKER_COOLDOWN_S=1
```

Exactly one test, at L6, runs with production values and asserts that four partners at 1.500 ms resolve in under 2.000 ms of wall clock. That test is the only proof that the calls are concurrent, and that the configuration is actually wired rather than hard-coded somewhere. It is tagged so it can be excluded from the fast loop but never from CI.

---



## 5. Acceptance Criteria Map


| Scenario in `01-product-spec.md`                            | Level  | Notes                                                                                           |
| ----------------------------------------------------------- | ------ | ----------------------------------------------------------------------------------------------- |
| A valid submission returns offers from all healthy partners | L5     | All four forced to `ok`                                                                         |
| Coverage level changes the prices quoted                    | L2, L5 | L2 proves the factor, L5 proves it reaches the customer                                         |
| Two partners return the same final price                    | L4     | Fake gateway returning equal prices                                                             |
| A slow partner does not delay the comparison                | L5, L6 | L5 with scaled timeouts, L6 with real ones                                                      |
| A partner returning a malformed payload is excluded         | L3, L5 | L3 for the parser, L5 for the end result                                                        |
| No partner responds                                         | L5     | Asserts 200 with an empty `offers` array                                                        |
| A partner known to be down does not consume the deadline    | L4, L5 | L4 for breaker state, L5 for `skipped` status                                                   |
| Input is restored after a reload                            | L7, L9 |                                                                                                 |
| Stale input is not restored                                 | L7     | Frozen clock plus a stored timestamp 25 hours old                                               |
| An active campaign is applied                               | L4, L5 | L4 asserts discount-then-sort ordering                                                          |
| An expired campaign is not applied                          | L4     | Frozen clock outside the validity window                                                        |
| An underage customer is rejected                            | L1, L5 | L1 for the age rule, L5 for the 422 body                                                        |
| All errors are revealed at once                             | L5, L8 | L5 asserts every invalid field appears                                                          |
| The results are usable on a narrow viewport                 | L8     |                                                                                                 |
| A fifth partner is added                                    | L4     | A stub partner registered through config, asserting it appears with no change to existing tests |
| Partner degradation is attributable                         | L5     | Asserts the per-partner counter moved after a forced failure                                    |


---



## 6. Contract Test Between Simulator and Adapter

The simulator and the adapter live in the same repository and can drift apart silently: a field renamed on one side keeps every unit test green because both sides are mocked in their own tests.

One test closes that gap. It calls the simulator's real success endpoint, feeds the raw body into the adapter's parser, and asserts an `Offer` comes out.

---



## 7. Test Data

A `QuoteRequestBuilder` with valid defaults, overriding one field per test. Two named presets matching the vectors in `04-providers.md`:

```php
QuoteRequestBuilder::vectorA()   // urban low-mileage sedan
QuoteRequestBuilder::vectorB()   // rural high-mileage commercial van
```

Tests state only what they care about. A test about coverage should not have to know a postal code.

---



## 8. What Not To Test

- **Every cell of every factor table.** The two vectors cover 56 factor lookups between them. Add only the age boundaries, where an off-by-one is plausible: 17/18, 24/25, 34/35, 54/55, 69/70.
- **Symfony.** Routing, serialisation and the container are not this project's code.
- **The whole response body as a snapshot.** Snapshots turn every intentional field addition into a red test.
- **Exact simulated latencies.** Assert that a call was cut off at the timeout, never that it took 1.643 ms.

---



## 9. Priority

Cut from the bottom.


| Tier        | Levels | Rationale                                                                                                     |
| ----------- | ------ | ------------------------------------------------------------------------------------------------------------- |
| **Must**    | L1–L5  | The acceptance criteria are unverified without these                                              |
| **Should**  | L6, L7 | L6 is the only proof of concurrency. L7 covers the storage expiry logic.                          |
| **If time** | L8, L9 | They retest rules already covered below                                                                       |


---



## 10. Continuous Integration

One workflow, four jobs, all on every push:

1. **Static analysis** — PHPStan at level 8, plus the frontend type check. Fails the build.
2. **Backend tests** — L1 through L5, with `.env.test` timeouts.
3. **Frontend tests** — L7 and L8.
4. **Timing and E2E** — L6 and L9, against the full `docker compose` stack.

Job 4 is the slow one and is the only candidate for running on pull requests rather than every push.

---




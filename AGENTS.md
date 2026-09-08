# AGENTS.md

Car insurance comparison. A Symfony API, a Vue SPA, and a simulated partner service standing in for four external insurers.

## Source of truth

`specs/` is the specification.

- When the code and a specification disagree, the specification is right and the code is a bug.
- When a specification and an ADR in `specs/02-decisions.md` disagree, the ADR is right.
- Do not invent product or architectural decisions. If something is not specified, say so rather than choosing.

The index and reading order are in `specs/00-README.md`. Read in this order:

1. `specs/01-product-spec.md` — what the product does and why
2. `specs/02-decisions.md` — product ADRs and implementation decisions
3. `specs/03-architecture.md` — structure, concurrency, layer rules
4. `specs/04-providers.md` — the four simulated partners
5. `specs/05-api-contract.md` — the HTTP contract
6. `specs/06-testing.md` — what is tested and at which level
7. `specs/07-observability.md` — metrics, logs, alerts, dashboard

## Layout

```
api/         Symfony. src/Domain, src/Application, src/Infrastructure, src/Simulator
frontend/    Vue 3 + TypeScript SPA
specs/       Specifications and decision records. Also `c24-api.postman_collection.json` (runnable API examples, not a spec)
ops/         Prometheus and Grafana configuration
```

`api/` builds one image. It runs twice: once as the API, once as the partner simulator, differing only in `APP_SIMULATOR_ENABLED`.

## Rules that hold everywhere

- **Money** is an integer number of cents inside a `Money` value object. No floats, no formatted strings.
- **Time** comes from the `Clock` port. Nothing constructs a date directly outside `Infrastructure`.
- **Partners are external systems.** They fail. A failure is a status on a `PartnerOutcome`, never an exception that escapes the gateway.
- **Pricing belongs to partners.** No pricing logic anywhere except `src/Simulator`.
- **Partner failure is not platform failure.** A comparison where every partner failed is a successful comparison with zero offers, and returns 200.

## Commands

```bash
cd api && vendor/bin/phpunit            # backend tests
cd api && vendor/bin/phpstan analyse    # static analysis, level 8
cd frontend && npm run test:unit        # frontend tests
docker compose up                       # full stack
```

## Finishing a task

End every task by listing the decisions you made that are not stated in `specs/`. Each one is a gap in the specification and needs to be reported, not silently resolved.

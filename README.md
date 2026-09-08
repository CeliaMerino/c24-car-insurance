# Car insurance comparison

Symfony API, Vue SPA, and a simulated partner service. Specs in `specs/` are the source of truth; start at [`specs/00-README.md`](specs/00-README.md).

## Prerequisites

- [Docker](https://docs.docker.com/get-docker/) with Compose V2 (`docker compose`)

No local PHP or Node is required to run the stack. They are only needed for tests outside Docker.

## Run

From the repository root:

```bash
docker compose up --build
```

The first build compiles the API (FrankenPHP) and frontend images and takes a few minutes. Later starts are faster; `--build` can be omitted unless a Dockerfile or dependency lockfile changed.

The stack has no database. Five containers come up:

| Service    | Role                                      | URL                                              |
|------------|-------------------------------------------|--------------------------------------------------|
| SPA        | Vue comparison form                       | http://localhost:5173                            |
| API        | Comparison API                            | http://localhost:8000 ([Postman collection](specs/c24-api.postman_collection.json)) |
| Simulator  | Four partner insurers (internal only)     | —                                                |
| Prometheus | Metrics scrape                            | http://localhost:9090                            |
| Grafana    | Provisioned comparison dashboard          | http://localhost:3000/d/c24-comparison           |

Open the SPA at http://localhost:5173 and submit the form. The frontend proxies `/api` to the API container.

To call the API directly, import [`specs/c24-api.postman_collection.json`](specs/c24-api.postman_collection.json) in Postman (File → Import). The HTTP contract in [`specs/05-api-contract.md`](specs/05-api-contract.md) is the source of truth; the collection is a set of runnable examples.

Grafana is provisioned from `ops/grafana/` and allows anonymous Admin access so reviewers can open the dashboard without credentials. **That setting is unsuitable for production.**

Stop with `Ctrl+C` in the same terminal, or `docker compose down` from another.

### Pin simulator behaviour (optional)

By default the simulator is unseeded, so partner latency and failures vary between runs. To make a demo repeatable, set `PARTNER_SIMULATION_SEED` on the `simulator` service in `docker-compose.yml` and recreate that container.

## Tests

These run on the host, not inside Compose. PHP 8.3+ with Composer, and Node 22+, are required.

```bash
cd api && vendor/bin/phpunit
cd api && vendor/bin/phpstan analyse
cd frontend && npm run test:unit
```

Install API and frontend dependencies once if they are not already present (`composer install` in `api/`, `npm ci` in `frontend/`).

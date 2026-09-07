# Car insurance comparison

Symfony API, Vue SPA, and a simulated partner service. Specs in `specs/` are the source of truth.

## Run

```bash
docker compose up --build
```

| Service    | URL                   |
|------------|-----------------------|
| SPA        | http://localhost:5173 |
| API        | http://localhost:8000 |
| Prometheus | http://localhost:9090 |
| Grafana    | http://localhost:3000 |

Grafana is provisioned from `ops/grafana/` and allows anonymous Admin access so reviewers can open the dashboard without credentials. **That setting is unsuitable for production.**

## Tests

```bash
cd api && vendor/bin/phpunit
cd api && vendor/bin/phpstan analyse
cd frontend && npm run test:unit
```

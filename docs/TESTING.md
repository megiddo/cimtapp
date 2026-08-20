# Testing

Coverage and mutation are **gates**, not dashboards. Scripts fail the build when a floor is missed.

## Floors

| Tool | Metric | Fail below | Config |
| --- | --- | --- | --- |
| PHPUnit | Line coverage of `backend/src` | **95%** | `phpunit.xml` source include + `scripts/check-coverage.php` |
| Vitest | Line coverage of `frontend/src/lib/**/*.ts` | **95%** | `vite.config.ts` `test.coverage.thresholds` |
| Infection | MSI / covered MSI | **80** / **85** | `backend/infection.json5` `minMsi` / `minCoveredMsi` |
| Stryker | Mutation score | **break 70**, **high 80** | `frontend/stryker.config.json` `thresholds` |

### Why these numbers

- **95% line coverage** on production source (never tests, vendor, generated, or `public/`). Crypto and dose math will live in `src/`; uncovered lines are how wrap/unwrap and remainder bugs slip through.
- **Infection 80 MSI / 85 covered MSI**: domain and crypto must kill mutants. 80 is the floor *given* 95% coverage — equivalent mutants exist, but a surviving mutant that changes wrap, remainder, or auth behavior is a product bug.
- **Stryker break 70 / high 80**: Svelte UI produces more equivalent mutants (markup, class strings) than PHP domain code. 70 fails the build; 80 is the healthy band.

Do not disable mutants globally. Infection excludes only empty interfaces / empty marker exceptions (`SettingsInterface`, `Domain/DomainException`) — files with no executable statements. That exclusion is documented in `infection.json5`.

## How coverage is measured

### PHP

```bash
cd backend
composer test
# → vendor/bin/phpunit --coverage-clover coverage/clover.xml --coverage-text
# → php scripts/check-coverage.php   # reads Clover project metrics, exits 1 if < 95%
```

PHPUnit `source.include` is `src/`. Tests, `vendor/`, `public/`, and `app/` config closures are outside that tree. `SettingsInterface` is excluded (empty interface).

Coverage driver: **pcov** in Docker (`docker-php-ext-enable pcov`). Locally, install pcov or xdebug.

PHPUnit bootstrap loads `backend/.env.testing` (dummy `CIMT_MASTER_KEY`, empty Google secrets). Tests must not need Google credentials. Google env is required only when `APP_ENV=production`.

Docker:

```bash
docker compose run --rm --no-deps app composer test
docker compose run --rm --no-deps app composer infection
```

### Frontend

```bash
cd frontend
npm test          # vitest run --coverage  (fails below 95% lines)
npm run mutation  # stryker run
```

Vitest includes `src/lib/**/*.ts` only (production helpers / API client). Route `.svelte` placeholders are not in the coverage denominator.

Docker:

```bash
docker compose run --rm frontend npm ci
docker compose run --rm frontend npm test
docker compose run --rm frontend npm run mutation
```

## Make targets

```bash
make test          # PHPUnit + Vitest (via compose)
make infection     # Infection in the PHP container
make mutation      # Infection + Stryker
```

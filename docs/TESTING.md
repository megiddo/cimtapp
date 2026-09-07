# Testing

Coverage and mutation are **gates**, not dashboards. Scripts fail the build when a floor is missed.

## Floors

| Tool | Metric | Fail below | Config |
| --- | --- | --- | --- |
| PHPUnit | Line coverage of `backend/src` | **90%** | `phpunit.xml` source include + `scripts/check-coverage.php` |
| Vitest | Line coverage of `frontend/src/lib/**/*.ts` | **90%** | `vite.config.ts` `test.coverage.thresholds` |
| Infection | MSI / covered MSI | **80** / **80** | `backend/infection.json5` `minMsi` / `minCoveredMsi` |
| Stryker | Mutation score | **break 80**, **high 80** | `frontend/stryker.config.json` `thresholds` |

### Why these numbers

- **90% line coverage** on production source (never tests, vendor, generated, or `public/`). Crypto, remainder, archive, and auth must stay covered. 95% fought equivalent `parent::__construct` / shred-cleanup lines.
- **Infection 80 MSI / 80 covered MSI**: one mutation number for PHP and JS. Domain and crypto must kill mutants. Equivalent mutants exist, but a surviving mutant that changes wrap, remainder, or auth behavior is a product bug.
- **Stryker break 80 / high 80**: the SPA client is held to the same mutation bar as PHP domain code. Markup-only `.svelte` files stay out of the mutate glob (`src/lib/**/*.ts` only).

Do not disable mutants globally. Infection excludes only empty interfaces / empty marker exceptions (`SettingsInterface`, `Domain/DomainException`) — files with no executable statements. That exclusion is documented in `infection.json5`.

## How coverage is measured

### PHP

```bash
cd backend
composer test
# → vendor/bin/phpunit --coverage-clover coverage/clover.xml --coverage-text
# → php scripts/check-coverage.php   # reads Clover project metrics, exits 1 if < 90%
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
npm test          # vitest run --coverage  (fails below 90% lines)
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
make test          # PHPUnit + Vitest (via development compose)
make infection     # Infection in the PHP container
make mutation      # Infection + Stryker
make up            # docker compose up --build (dev)
make up-prod       # docker compose -f docker-compose.prod.yml up --build
make deploy        # git pull --rebase origin main, then prod compose up --build -d
```

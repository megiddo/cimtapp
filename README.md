# CIMTapp

Compounded Incretin Mimetic Tracking App: a personal compounding log. Mix a vial, log each use in IU against a named syringe, see milligrams deducted, and watch the active vial burn down.

This repository currently ships **Phase 0 foundations**: Slim 4 JSON API at `/api/v1`, SvelteKit static SPA in `backend/public/`, global sqlite migrated on boot, libsodium crypto, locked/encrypted per-user stores, and a 360px mobile shell. Auth and dose domain land in later stacked branches.

## Quick start (Docker)

```bash
cp .env.example .env          # dummy CIMT_MASTER_KEY is fine locally
make frontend-build           # writes the SPA into backend/public/
docker compose up --build
```

App URL: **http://localhost:8080**

Health: **http://localhost:8080/api/v1/health** → `{ "status": "ok" }`

`make up` is equivalent to copying `.env` if missing and running `docker compose up --build`. Bind-mounts `backend/` for PHP edits. SQLite files persist in the `cimtapp-data` volume at `/var/www/cimtapp/data`. Decrypted user sqlite is written only under `DATA_DIR/tmp` (`/var/www/cimtapp/data/tmp` in Docker, tmpfs-mounted).

Without Docker (PHP 8.3+ and Node 22/20):

```bash
cd backend && composer install && composer start   # http://localhost:8080
cd frontend && npm ci && npm run build             # SPA into backend/public/
```

## Tests

| What | Command |
| --- | --- |
| PHPUnit + 95% coverage floor | `docker compose run --rm --no-deps app composer test` or `cd backend && composer test` |
| Infection (min MSI 80 / covered 85) | `docker compose run --rm --no-deps app composer infection` or `cd backend && composer infection` |
| Vitest + 95% coverage floor | `docker compose run --rm frontend npm ci && docker compose run --rm frontend npm test` or `cd frontend && npm test` |
| Stryker (break 70 / high 80) | `cd frontend && npm run mutation` |
| All of the above via Make | `make test` then `make mutation` |

See [docs/TESTING.md](docs/TESTING.md) for floors and why they exist.

## Environment

| Variable | Purpose |
| --- | --- |
| `APP_ENV` | `testing` / `development` / `production` |
| `CIMT_MASTER_KEY` | 256-bit AMK: **64 hex chars** or **base64 of 32 bytes**. `openssl rand -hex 32` |
| `GOOGLE_CLIENT_ID` | Google OAuth client id (required only in production) |
| `GOOGLE_CLIENT_SECRET` | Google OAuth secret (required only in production; never in the SPA) |
| `GOOGLE_REDIRECT_URI` | e.g. `http://localhost:8080/api/v1/auth/google/callback` |
| `DATA_DIR` | Directory for `global.sqlite`, `users/*.enc`, and `tmp/` plaintext-while-unlocked (Docker: `/var/www/cimtapp/data`) |
| `APP_URL` | Public origin, no trailing slash required |
| `SESSION_SECURE` | `true`/`false` — Secure cookie flag (Phase 1) |
| `docker` | Set in the image so Monolog logs to stdout |

Examples live at `.env.example` and `backend/.env.example`. Real `.env` files are gitignored. PHPUnit loads `backend/.env.testing` (dummy key, empty Google secrets).

## Layout

```
backend/          Slim 4 (slim-skeleton): public/index.php, app/, src/, tests/
backend/migrations/  global + per-user sqlite SQL, applied on boot / account create
frontend/         SvelteKit + adapter-static (SPA, no Svelte server)
docs/             DESIGN, work checklist, testing gates
data/             gitignored sqlite / encrypted user stores (`tmp/` plaintext while unlocked)
```

Architecture summary: [docs/DESIGN.md](docs/DESIGN.md). Phased work: [docs/WORK-CHECKLIST.md](docs/WORK-CHECKLIST.md).

## License

MIT © 2026 Noah Smith

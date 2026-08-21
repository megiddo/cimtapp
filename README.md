# CIMTapp

Compounded Incretin Mimetic Tracking App: a personal compounding log. Mix a vial, log each use in IU against a named syringe, see milligrams deducted, and watch the active vial burn down.

This repository currently ships **v1 (Phase 3)**: mix a vial, log IU, remainder, settings syringes, empty/error states, PWA Add to Home Screen, login/Google rate limits, AMK rotation, and an authenticated sqlite export. Cookie sessions and encrypted user sqlite come from Phase 1.

## Quick start (Docker)

```bash
cp .env.example .env          # dummy CIMT_MASTER_KEY is fine locally
make frontend-build           # writes the SPA into backend/public/
docker compose up --build     # development compose (bind-mounts PHP)
```

App URL: **http://localhost:24780** (dev). Production compose publishes **http://localhost:25880**.

If the host port is already taken, remap it and point `APP_URL` at it. Compose **appends** `ports` unless you override:

```yaml
# docker-compose.override.yml (local only)
services:
  app:
    ports: !override
      - "26980:80"
    environment:
      APP_URL: http://localhost:26980
      GOOGLE_REDIRECT_URI: http://localhost:26980/api/v1/auth/google/callback
```

Then open **http://localhost:26980** instead.

Health: **http://localhost:24780/api/v1/health** (or the remapped host port) → `{ "status": "ok" }`

Auth (same origin, cookie `cimtapp_session`): `POST /api/v1/auth/register`, `/auth/login`, `/auth/logout`; `GET /api/v1/auth/google/start` (full page); `GET /api/v1/me`. Login and Google start are limited to **10 attempts / 15 minutes** per IP (and per email for login). Domain (cookie required): `GET /peptide-types`, `/syringes`, `/compounds`, `/compounds/current` (**404** if none), `/uses`; `POST /compounds` (mix), `POST /uses` (log). `GET /api/v1/me/export` downloads the logged-in user’s plaintext sqlite. Passwords are Argon2id, minimum 12 characters. Google is mocked in tests — no real `GOOGLE_CLIENT_*` required outside production.

`make up` is equivalent to copying `.env` if missing and running `docker compose up --build` (**development**: bind-mounts `backend/` for PHP edits). `make up-prod` uses `docker-compose.prod.yml` (image contents only, `APP_ENV=production`, `SESSION_SECURE=true`, real `CIMT_MASTER_KEY` required in `.env`). Both bind `./data` on the host to `/var/www/cimtapp/data`, so `global.sqlite` and `users/*.enc` survive container rebuilds. Decrypted user sqlite is written only under `DATA_DIR/tmp` (tmpfs in Docker — not on the host). If you still have the old named volume `cimtapp_cimtapp-data`, copy it once: `docker run --rm -v cimtapp_cimtapp-data:/from -v "$PWD/data:/to" alpine sh -c 'cp -a /from/. /to/'`.

### Production

```bash
# .env needs a real CIMT_MASTER_KEY and Google OAuth (required when APP_ENV=production)
# Set APP_URL and GOOGLE_REDIRECT_URI to http://localhost:25880 (or your public origin)
make up-prod
# or
docker compose -f docker-compose.prod.yml up --build
```

No PHP bind-mount. The SPA is baked into the image on `--build`. SQLite is still `./data` on the host.

### First run

1. Open the app URL. Unauthenticated routes send you to `/login`.
2. **Need an account? Register.** Email plus a password of at least 12 characters. Skip **Continue with Google** unless `GOOGLE_CLIENT_ID` / `SECRET` and a matching redirect URI are set.
3. Home is empty until a vial exists. Open **Inventory** → **Add to Inventory**:
   - bacteriostatic water (mixes deduct from the current bottle)
   - syringe types (a 0.5 mL / 50 IU type is seeded; stock starts at 0)
   - mix a vial (optional **Calculate water needed** fills peptide mg and BAC mL)
4. Log uses from the **Log** tab. Remainder is on Home. Settings holds syringes, password, and logout.

### Frontend edits

PHP under `backend/` is bind-mounted, so API changes apply without rebuilding the image. The SPA is static files in `backend/public/`. After editing `frontend/`, rebuild and refresh the browser:

```bash
make frontend-build
# or
docker compose run --rm frontend npm run build
```

`docker compose up --build` (dev) does not rebuild the SPA. Production bakes the SPA into the image, so `make up-prod` / `docker compose -f docker-compose.prod.yml up --build` picks up frontend changes.

Without Docker (PHP 8.3+ and Node 22/20):

```bash
cd backend && composer install && composer start   # http://localhost:24780
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
| `GOOGLE_REDIRECT_URI` | e.g. `http://localhost:24780/api/v1/auth/google/callback` |
| `DATA_DIR` | Directory for `global.sqlite`, `users/*.enc`, and `tmp/` plaintext-while-unlocked (Docker: `/var/www/cimtapp/data`, host `./data`) |
| `APP_URL` | Public origin, no trailing slash required |
| `SESSION_SECURE` | `true`/`false` — Secure cookie flag (Phase 1) |
| `docker` | Set in the image so Monolog logs to stdout |

Examples live at `.env.example` and `backend/.env.example`. Real `.env` files are gitignored. PHPUnit loads `backend/.env.testing` (dummy key, empty Google secrets).

## Layout

```
backend/                 Slim 4 (slim-skeleton): public/index.php, app/, src/, tests/
backend/migrations/       global + per-user sqlite SQL, applied on boot / account create
frontend/                SvelteKit + adapter-static (SPA, no Svelte server)
docs/                    DESIGN, work checklist, testing gates, backup / AMK rotation
docker-compose.yml       development (PHP bind-mount, dummy AMK fallback)
docker-compose.prod.yml  production (image only, Secure cookies, required AMK)
data/                    host sqlite + encrypted user stores (bind-mounted in both compose files)
```

Architecture summary: [docs/DESIGN.md](docs/DESIGN.md). Phased work: [docs/WORK-CHECKLIST.md](docs/WORK-CHECKLIST.md). Backup: [docs/BACKUP.md](docs/BACKUP.md).

## License

MIT © 2026 Noah Smith

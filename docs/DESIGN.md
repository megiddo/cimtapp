# PepTrack design (in-repo)

Condensed from the design canvas so later agents do not need it. Product: a personal compounding log for incretin mimetics.

## Stack

| Layer | Choice | Notes |
| --- | --- | --- |
| API | Slim 4, PHP 8.3+, PHP-DI | JSON under `/api/v1`. Skeleton lives in `backend/` (`public/index.php`, `app/`, `src/`). |
| SPA | SvelteKit + `@sveltejs/adapter-static` | Client routes only. Build drops into `backend/public/` (`fallback: index.html`). No Svelte server. |
| Serving | Same origin | Slim (or Apache) serves `/api/v1/*` via `index.php`; everything else is static + SPA fallback. Cookie session. No CORS. |
| Global store | `data/global.sqlite` | Users, sessions, wrapped DEKs, peptide catalog. Migrated on Slim boot. |
| User store | `data/users/{uuid}.sqlite.enc` | Compounds, uses, syringe profiles, identity snapshot. Locked per request. |
| Crypto | libsodium secretbox + secretstream | `App\Domain\Crypto\Crypto`. Stock PHP (`ext-sodium`). No SQLCipher. |
| Plaintext tmp | `DATA_DIR/tmp` | Decrypted user sqlite while a request holds the flock. Docker mounts tmpfs at `/var/www/cimtapp/data/tmp`. Host `./data` is bind-mounted in both `docker-compose.yml` (dev) and `docker-compose.prod.yml` (prod) so ciphertext survives rebuilds. |
| OAuth | `league/oauth2-google` | Authorization code + PKCE (S256). Server exchanges the code. Never put the client secret in the SPA. |

PHP front controller matches the Slim skeleton: PHP-DI `ContainerBuilder`, `app/settings.php` + `dependencies.php` + `repositories.php`, `AppFactory::setContainer`, middleware + routes, `ServerRequestCreatorFactory`, `HttpErrorHandler`, `ShutdownHandler`, routing/body/error middleware, `ResponseEmitter`.

Actions are invokable classes extending `App\Application\Actions\Action` with `action(): Response` and `respondWithData()`.

## Encryption stance

The server can decrypt user data after a successful login. That is required for Google OAuth and email-matched shared login. Encrypted-at-rest protects disks, backups, and copied user files — not a hostile server operator.

Three secrets, one unwrap path. The global DB never holds a plaintext user key.

1. **AMK** (`CIMT_MASTER_KEY`) — 256-bit application master key in env. Wraps every per-user DEK. Rotatable by re-wrapping DEKs (`Crypto::rewrapDek`, `bin/rotate-amk.php`). Does not rewrite `.enc` files.
2. **DEK** — random 256-bit data key minted at account create. Stored as nonce + ciphertext in `users.encrypted_dek`.
3. **User DB** — whole sqlite file wrapped with secretstream using the DEK. Decrypt only under an exclusive flock for the request.

Request unlock path: session cookie → user row + encrypted DEK → unwrap DEK with AMK → flock `users/{id}.lock` → decrypt `.enc` to tmpfs sqlite → handler → close, re-encrypt, atomic `rename()` → unlock. File wrapping is last-writer-wins without a lock; every mutating request must hold the exclusive flock. One active person at a time is the product.

Boot (`App\Application\Boot\BootServices`): `GlobalMigrator` applies `backend/migrations/global/*.sql` onto `{DATA_DIR}/global.sqlite` (creates the dir if missing). Second boot is a no-op via `schema_migrations`. Peptide seed is `INSERT OR IGNORE`.

Open a user store: inject `App\Infrastructure\Persistence\UserStore`, unwrap the DEK with `Crypto`, then `UserStore::withUnlocked($userId, $dek, function (PDO $pdo) { ... })`. `create($userId, $dek)` applies user-schema **strategies** (`App\Infrastructure\Persistence\UserSchema`) to a fresh sqlite up to `UserStoreFormat::current()`, encrypts, and writes `{DATA_DIR}/users/{uuid}.sqlite.enc`. Unlock and `GET /me/export` detect prior format versions (`user_store_format`, legacy `schema_migrations` filenames, or table shape) and mutate forward. Export returns decrypted plaintext sqlite (`SQLite format 3`) after those mutations. Lock timeout throws `UserStoreLockedException` (HTTP 503). Plaintext files live only under `{DATA_DIR}/tmp/` for the duration of the callback.

Future (not v1): wrap DEK with Argon2id from the user password for password-only zero-knowledge. Google accounts cannot use that model without a recovery secret.

## Auth (Phase 1)

Email is the account primary key (normalized `strtolower(trim)`). Password and Google are methods on the same row.

- Register with password: validate, Argon2id (`PASSWORD_ARGON2ID`), mint DEK, wrap with AMK, insert `users`, `UserStore::create`, seed identity + default syringe inside `withUnlocked`, set session cookie.
- Password minimum: **12 characters**. Documented here and in `App\Domain\Auth\AuthConfig::PASSWORD_MIN_LENGTH`.
- Login with password: Argon2id verify. Unknown email and bad password share the copy **“Invalid email or password”** (HTTP 401). Google-only accounts cannot password-login until they set a password. Refresh `last_login_at`.
- Google, new email: create user with `google_sub`, null `password_hash`, same provisioning. Authorization code + PKCE (S256); server exchanges the code. `email_verified` is required.
- Google, existing email: attach `google_sub` if empty; sign into that account. If `google_sub` already belongs to a different user, fail safely (302 back to `/login?error=google`, no merge).
- Password signup when that email exists (Google or password): **422** `{ "email": ["Email is already registered."] }` — no extra hint that distinguishes Google vs password.
- Set password once authed: `POST /me/password`. Hashes into global `users` and mirrors the `account` snapshot in the user sqlite.

Session: opaque **32-byte hex** id (64 hex chars) in cookie **`cimtapp_session`**. Flags: HttpOnly, SameSite=Lax, Path=/, Secure from `SESSION_SECURE`. Server table `sessions` with **14-day sliding expiry** (each authenticated request extends `expires_at`). No JWT.

Rate limits (global sqlite `rate_limit_hits`, no Redis): **10 attempts / 15 minutes**. `POST /auth/login` is keyed by IP and by normalized email when present. `GET /auth/google/start` is keyed by IP. Over budget → HTTP **429** `{ type: TOO_MANY_REQUESTS, description: "Too many attempts. Try again later." }` — same body for every caller (no user enumeration). Auth failures stay **“Invalid email or password”**. Counters use injected `Clock` and expired rows are deleted opportunistically.

Request unlock path for protected routes (`/me`, later domain):

1. `SessionAuthMiddleware` reads the cookie, loads a non-expired session, loads the user, unwraps the DEK with the AMK, touches sliding expiry, attaches `AuthContext` (user, DEK, session).
2. `AuthenticatedAction` opens `UserStore::withUnlocked` **around** `action()`, so the handler runs inside the lock/decrypt window. The PDO is closed and the file re-encrypted when the callback returns.

`UserStoreLockedException` → HTTP **503** `{ type: SERVICE_UNAVAILABLE }`. `CryptoException` → **500** with a generic description (never keys). Missing/invalid session → **401**.

Google is `league/oauth2-google` behind `GoogleOAuthClient` (fake in tests; Guzzle mock for the League adapter). The SPA never sees `GOOGLE_CLIENT_SECRET`. `GET /auth/google/start` returns **503** if Google is not configured.

Each user sqlite includes an `account` snapshot (email, password hash, google_sub) and one default syringe **0.5 mL / 50 IU** (`is_default = 1`). Ciphertext path: `{DATA_DIR}/users/{uuid}.sqlite.enc`.

## Schema sketch

**Global** (not encrypted as a whole; DEKs inside it are; created by `GlobalMigrator`):

- `users`: id UUID, email unique lowercase, password_hash Argon2id nullable, google_sub unique nullable, encrypted_dek, dek_nonce, created_at, last_login_at
- `sessions`: id opaque 32-byte hex, user_id, expires_at (14d), created_at
- `peptide_types`: slug (`semaglutide`, `tirzepatide`, `retatrutide`, `liraglutide`), name, is_active, sort_order
- `oauth_states`: state CSRF nonce, expires_at 10 min, redirect_after, code_verifier (PKCE)
- `rate_limit_hits`: bucket + hit_at for login / Google-start budgets

**Per-user** (applied from `migrations/user` at account create; peptide catalog denormalized onto each compound):

- `account` (1 row): user_id, email, password_hash, google_sub, updated_at
- `syringe_profiles`: id, label, volume_ml > 0, capacity_iu > 0, is_default (exactly one)
- `compounds`: id, **name** (vial identifier), **is_open**, **archived_at** (v5; hidden from inventory when set), peptide_type_id + slug/name, peptide_mg, bac_water_ml, compounded_at, notes, created_at
- `compound_adjustments` (v5): id, compound_id, delta_mg, remaining_ml snapshot, notes, created_at — manual remaining corrections
- `uses`: id, compound_id, iu, syringe snapshots, volume_ml, peptide_mg, used_at, notes, created_at/updated_at
- `user_store_format`: single-row integer format version (strategy migrations)

Open vials (`is_open = 1`, not archived) can coexist. Home and Log list them by **name + peptide**. `GET /compounds/current` is the latest **open** `compounded_at`. Closed vials stay in inventory until remaining is 0 and they are archived. Remaining volume is computed from uses plus `compound_adjustments`. Compounds stay editable after uses; changing `peptide_mg` or `bac_water_ml` recalculates stored use milligrams. Delete is allowed only when the vial has no uses.

## API (`/api/v1`, JSON, cookie auth)

Validation errors: **422** with a field map on `error.fields`. Unauthenticated: **401**.

```json
{
  "statusCode": 422,
  "error": {
    "type": "VALIDATION_ERROR",
    "description": "Validation failed.",
    "fields": {
      "email": ["Email is already registered."],
      "password": ["Password must be at least 12 characters."]
    }
  }
}
```

Success bodies stay `{ "statusCode": 201, "data": { ... } }`. `GET /me` data is `{ email, has_password, has_google, remainder, open_vials }`. `remainder` is `null` until an **open** vial exists, then:

```json
{
  "compound_id": "…",
  "name": "Fridge A",
  "peptide_name": "Tirzepatide",
  "remaining_mg": 8.75,
  "remaining_ml": 1.75,
  "remaining_iu": 175,
  "concentration": 5,
  "compounded_at": "2026-08-20T12:00",
  "is_open": true
}
```

`open_vials` is the same shape, one object per open vial (newest mix first). `remaining_iu` on `/me` and `GET /compounds/current` uses the **default syringe**. DEK / nonce / ciphertext never appear.

| Method | Path | Role |
| --- | --- | --- |
| GET | `/health` | liveness `{ "status": "ok" }` |
| POST | `/auth/register` | email + password → session cookie |
| POST | `/auth/login` | email + password → session cookie |
| POST | `/auth/logout` | clear session row + expire cookie (idempotent) |
| GET | `/auth/google/start` | 302 to Google (full page; PKCE + state) |
| GET | `/auth/google/callback` | exchange code, set cookie, 302 to `/` |
| GET | `/me` | identity + remainder summary (auth) |
| POST | `/me/password` | set or change password (auth) |
| GET | `/me/export` | authenticated **decrypted** sqlite download (`application/octet-stream`); schema mutated to current format; temp file shredded |
| GET | `/peptide-types` | global catalog, active only, `sort_order` (`id` = slug) |
| GET/POST | `/syringes` | list / create (`volume_ml` > 0, `capacity_iu` > 0; auto label `0.5 mL / 50 IU` if omitted; exactly one `is_default`) |
| PATCH | `/syringes/{id}` | label and/or default flag (setting default unsets others) |
| GET/POST | `/compounds` | inventory (`compounded_at DESC`, remaining, name, is_open; **excludes archived**) / mix (`name` optional, defaults to peptide) |
| GET | `/compounds/open` | open vials only, newest mix first |
| GET | `/compounds/current` | latest **open** `compounded_at` + remainder at default syringe. **404** when none (SPA treats empty) |
| GET | `/compounds/{id}` | one mix + remainder |
| PATCH | `/compounds/{id}` | name, is_open, mix fields; mix changes recalc use mg and 422 if uses would overdraw |
| POST | `/compounds/{id}/adjust` | set `remaining_ml` (0 to mix volume); stores a milligram delta; 422 if archived |
| POST | `/compounds/{id}/archive` | hide an empty vial from inventory lists; closes it; 422 if remaining > 0 |
| DELETE | `/compounds/{id}` | 204 if unused; 422 if the vial has uses |
| GET/POST | `/uses` | list `used_at DESC, id DESC` / log against current (or `compound_id`) |
| PATCH | `/uses/{id}` | iu, syringe, used_at, notes; recalc mg; re-check remainder (original use put back first) |
| DELETE | `/uses/{id}` | 204; remainder on the vial increases |
| GET | `/uses/{id}` | one use |
| POST | `/bac-bottles/{id}/archive` | hide an empty bottle from inventory lists; 422 if remaining > 0 |

`GET /uses?before=iso&limit=50`. Default limit 50, cap 100. `before` is exclusive on `used_at`. POST `/uses` snapshots syringe onto the row. Default syringe for a new use: last-used if that profile still exists, else the default syringe. Edits keep the original vial.

Overdraw is **422** with the usual field map **and** `remaining_iu` on the error object (IU remaining at the syringe on this request):

```json
{
  "statusCode": 422,
  "error": {
    "type": "VALIDATION_ERROR",
    "description": "25 IU exceeds 18 IU remaining in this vial.",
    "fields": {
      "iu": ["25 IU exceeds 18 IU remaining in this vial."]
    },
    "remaining_iu": 18
  }
}
```

No vial yet → POST `/uses` 422 `{ "compound_id": ["Mix a vial before logging a use."] }`.

## Dose formulas

Do not assume U-100. A syringe is `(volume_ml, capacity_iu)`.

```
concentration = peptide_mg / bac_water_ml          # mg/mL
volume_ml     = iu × (syringe_volume_ml / syringe_capacity_iu)
peptide_mg    = volume_ml × concentration
remaining_mg  = compound.peptide_mg − Σ use.peptide_mg + Σ adjustment.delta_mg
remaining_ml  = remaining_mg / concentration
remaining_iu  = remaining_ml / (syringe_volume_ml / syringe_capacity_iu)
```

Worked example: tirzepatide 10 mg in 2.0 mL BAC, syringe 0.5 mL / 50 IU, dose 25 IU → 5.00 mg/mL, 0.010 mL/IU, 0.25 mL, 1.25 mg, remainder 8.75 mg / 1.75 mL / 175 IU.

Rules: IU allows one decimal, reject `<= 0`. Store mg at 4 decimal places; display 2–3. Overdraw → 422 with `error.remaining_iu` plus `error.fields.iu`. When **editing** a use, remainder check excludes that use’s `peptide_mg` (puts it back) before applying the new IU. No open vial yet → log use 422 pointing at mix-a-vial. Current compound = latest **open** `compounded_at`, not `created_at`. After any uses, `peptide_mg` and `bac_water_ml` (and type) cannot change.

## Mobile-first UI

Phone layout is the product. Design floor 360px (SE). Max content width 430px, single column, centered. Min tap 48px, list rows 56px. No desktop layout, sidebar, or hover-only control. Tokens live in `frontend/src/lib/chrome.ts` (`DESIGN_FLOOR_PX`, `CONTENT_MAX_PX`, `MIN_TAP_PX`, `ROW_MIN_PX`, `INPUT_FONT_PX`) and matching CSS variables.

Viewport (this milestone): `width=device-width, initial-scale=1, viewport-fit=cover`. Tab bar and sticky CTAs pad `env(safe-area-inset-*)`. Body/inputs 16px so iOS Safari does not zoom. Add to Home Screen: `frontend/static/manifest.webmanifest` plus Apple `apple-mobile-web-app-capable` / title / status-bar meta in `app.html`. Desktop uses the same shell.

Authenticated chrome: thin top bar + fixed bottom tabs. Login (`/login`) has no tabs. Home gear → `/settings` (syringes, set password, logout). Session-gated routes (Home, Log, Inventory, History, Settings) redirect to `/login` when `GET /me` is 401. “Continue with Google” is a full-page navigation to `GET /api/v1/auth/google/start` (not XHR) because of the 302.

| Tab | Route | Role |
| --- | --- | --- |
| Home | `/` | Remainder hero, last few uses |
| Log | `/use/new` | Center tab — IU field, sticky Save |
| Inventory | `/inventory` | Mixes with remaining mg/mL; Add is `/inventory/new` |
| History | `/history` | Uses newest first; tap to edit or delete |

Login has no tabs. Settings is `/settings` from the Home gear. Copy voice: clinical and short.

## Phases

Gate: nothing in the next phase starts until the previous gate is true.

- **P0 Foundations** — health JSON, SPA in `public/`, env, global migrator, crypto, user-store wrap, 360px chrome.
- **P1 Auth + stores** — register/login/Google, session, encrypted user sqlite with identity + default syringe.
- **P2 Domain** — compounds, uses, burndown, overdraw 422.
- **P3 UI + harden** — empty states, settings syringes, mobile QA, PWA, crypto/dose tests, rate limits, backup + AMK rotation. **v1 complete.**

See [WORK-CHECKLIST.md](WORK-CHECKLIST.md). Backup and AMK rotation: [BACKUP.md](BACKUP.md).

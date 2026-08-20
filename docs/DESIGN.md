# CIMTapp design (in-repo)

Condensed from the design canvas so later agents do not need it. Product: a personal compounding log for incretin mimetics.

## Stack

| Layer | Choice | Notes |
| --- | --- | --- |
| API | Slim 4, PHP 8.3+, PHP-DI | JSON under `/api/v1`. Skeleton lives in `backend/` (`public/index.php`, `app/`, `src/`). |
| SPA | SvelteKit + `@sveltejs/adapter-static` | Client routes only. Build drops into `backend/public/` (`fallback: index.html`). No Svelte server. |
| Serving | Same origin | Slim (or Apache) serves `/api/v1/*` via `index.php`; everything else is static + SPA fallback. Cookie session. No CORS. |
| Global store | `data/global.sqlite` | Users, sessions, wrapped DEKs, peptide catalog. Not implemented yet. |
| User store | `data/users/{uuid}.sqlite.enc` | Compounds, uses, syringe profiles, identity snapshot. Not implemented yet. |
| Crypto | libsodium secretbox + secretstream | Stock PHP (`ext-sodium`). No SQLCipher. |
| OAuth | Google authorization code | Server exchanges the code. Never put the client secret in the SPA. |

PHP front controller matches the Slim skeleton: PHP-DI `ContainerBuilder`, `app/settings.php` + `dependencies.php` + `repositories.php`, `AppFactory::setContainer`, middleware + routes, `ServerRequestCreatorFactory`, `HttpErrorHandler`, `ShutdownHandler`, routing/body/error middleware, `ResponseEmitter`.

Actions are invokable classes extending `App\Application\Actions\Action` with `action(): Response` and `respondWithData()`.

## Encryption stance

The server can decrypt user data after a successful login. That is required for Google OAuth and email-matched shared login. Encrypted-at-rest protects disks, backups, and copied user files — not a hostile server operator.

Three secrets, one unwrap path. The global DB never holds a plaintext user key.

1. **AMK** (`CIMT_MASTER_KEY`) — 256-bit application master key in env. Wraps every per-user DEK. Rotatable by re-wrapping DEKs.
2. **DEK** — random 256-bit data key minted at account create. Stored as nonce + ciphertext in `users.encrypted_dek`.
3. **User DB** — whole sqlite file wrapped with secretstream using the DEK. Decrypt only under an exclusive flock for the request.

Request unlock path: session cookie → user row + encrypted DEK → unwrap DEK with AMK → flock `users/{id}.lock` → decrypt `.enc` to tmpfs sqlite → handler → close, re-encrypt, atomic `rename()` → unlock. File wrapping is last-writer-wins without a lock; every mutating request must hold the exclusive flock. One active person at a time is the product.

Future (not v1): wrap DEK with Argon2id from the user password for password-only zero-knowledge. Google accounts cannot use that model without a recovery secret.

## Auth (Phase 1)

Email is the account primary key (normalized lowercase). Password and Google are methods on the same row.

- Register with password: create user, Argon2id, mint DEK, write empty encrypted store, set session.
- Login with password: verify hash in global DB; open user store; refresh last_login.
- Google, new email: create user with `google_sub`, null password_hash, same provisioning.
- Google, existing email: attach `google_sub` if empty; require `email_verified`.
- Password signup when Google email exists: reject duplicate.
- Set password later from settings.

Session: opaque id, HttpOnly / Secure / SameSite=Lax cookie, server table with expiry. No JWT in v1.

Each user sqlite includes an `account` snapshot (email, password hash, google_sub). A decrypted file is a self-contained export. Migration is copy `global.sqlite` + `users/*.enc` + AMK.

## Schema sketch

**Global** (not encrypted as a whole; DEKs inside it are):

- `users`: id UUID, email unique lowercase, password_hash Argon2id nullable, google_sub unique nullable, encrypted_dek, dek_nonce, created_at, last_login_at
- `sessions`: id opaque 32-byte hex, user_id, expires_at (14d), created_at
- `peptide_types`: slug (`semaglutide`, `tirzepatide`, `retatrutide`, `liraglutide`), name, is_active, sort_order
- `oauth_states`: state CSRF nonce, expires_at 10 min, redirect_after

**Per-user** (applied from `migrations/user` at account create; peptide catalog denormalized onto each compound):

- `account` (1 row): user_id, email, password_hash, google_sub, updated_at
- `syringe_profiles`: id, label, volume_ml > 0, capacity_iu > 0, is_default (exactly one)
- `compounds`: id, peptide_type_id + slug/name, peptide_mg, bac_water_ml, compounded_at, notes, created_at
- `uses`: id, compound_id, iu, syringe snapshots, volume_ml, peptide_mg, used_at, notes, created_at/updated_at

Active compound = latest `compounded_at`. Remainder UI always means that row. After a compound has any uses, `peptide_mg` and `bac_water_ml` cannot change.

## API (`/api/v1`, JSON, cookie auth)

Validation errors: 422 with field map. Unauthenticated: 401.

| Method | Path | Role |
| --- | --- | --- |
| GET | `/health` | liveness `{ "status": "ok" }` (this milestone) |
| POST | `/auth/register` | email + password |
| POST | `/auth/login` | email + password |
| POST | `/auth/logout` | clear session |
| GET | `/auth/google/start` | 302 to Google |
| GET | `/auth/google/callback` | exchange, set cookie, 302 SPA |
| GET | `/me` | identity + remainder summary |
| GET | `/peptide-types` | global catalog |
| GET/POST | `/syringes` | list / create |
| PATCH | `/syringes/{id}` | label, default flag |
| GET/POST | `/compounds` | inventory / mix |
| GET | `/compounds/current` | latest mix + remainder |
| GET/POST | `/uses` | list desc / log |
| PATCH | `/uses/{id}` | iu, syringe, used_at, notes |

`GET /uses?before=iso&limit=50`. Default order `used_at DESC, id DESC`.

## Dose formulas

Do not assume U-100. A syringe is `(volume_ml, capacity_iu)`.

```
concentration = peptide_mg / bac_water_ml          # mg/mL
volume_ml     = iu × (syringe_volume_ml / syringe_capacity_iu)
peptide_mg    = volume_ml × concentration
remaining_mg  = compound.peptide_mg − Σ use.peptide_mg
remaining_ml  = remaining_mg / concentration
remaining_iu  = remaining_ml / (syringe_volume_ml / syringe_capacity_iu)
```

Worked example: tirzepatide 10 mg in 2.0 mL BAC, syringe 0.5 mL / 50 IU, dose 25 IU → 5.00 mg/mL, 0.010 mL/IU, 0.25 mL, 1.25 mg, remainder 8.75 mg / 1.75 mL / 175 IU.

Rules: IU allows one decimal, reject `<= 0`. Overdraw → 422 with `remaining_iu`. Store mg at 4 decimal places; display 2–3. No compound yet → log use disabled.

## Mobile-first UI

Phone layout is the product. Design floor 360px (SE). Max content width 430px, single column, centered. Min tap 48px, list rows 56px. No desktop layout, sidebar, or hover-only control.

Viewport (this milestone): `width=device-width, initial-scale=1, viewport-fit=cover`. Tab bar and sticky CTAs pad `env(safe-area-inset-*)`. Body/inputs 16px so iOS Safari does not zoom.

Authenticated chrome (Phase 0 remaining / Phase 3): thin top bar + fixed bottom tabs.

| Tab | Route | Role |
| --- | --- | --- |
| Home | `/` | Remainder hero, last few uses |
| Log | `/use/new` | Center tab — IU stepper, sticky Save |
| Vials | `/inventory` | Mixes; Mix is `/inventory/new` full-screen |
| History | `/history` | Uses newest first; tap to edit |

Login has no tabs. Settings is `/settings` from the Home gear. Copy voice: clinical and short.

## Phases

Gate: nothing in the next phase starts until the previous gate is true.

- **P0 Foundations** — health JSON, SPA in `public/`, env, later crypto + migrators + 360px chrome.
- **P1 Auth + stores** — register/login/Google, session, encrypted user sqlite with identity + default syringe.
- **P2 Domain** — compounds, uses, burndown, overdraw 422.
- **P3 UI + harden** — empty states, settings, mobile QA, crypto/dose tests, rate limits, backup note.

See [WORK-CHECKLIST.md](WORK-CHECKLIST.md).

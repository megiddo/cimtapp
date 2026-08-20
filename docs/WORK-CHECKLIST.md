# CIMTapp work checklist

Implementation-sized items from the design plan. Framework (this branch) marks scaffolding done; domain stays pending.

## Testing gates

CI and local scripts **must fail** below these floors. Details: [TESTING.md](TESTING.md).

| Gate | Floor | Why |
| --- | --- | --- |
| PHPUnit line coverage | **95%** of `backend/src` (exclude tests, vendor, generated, `public/`) | Domain/crypto will live here; untested lines are how wrap/unwrap bugs hide. |
| Vitest line coverage | **95%** of `frontend/src/lib` production TS | API client and later dose helpers must stay honest. |
| Infection | **minMsi 80**, **minCoveredMsi 85** | Domain/crypto code must kill mutants. 80 MSI is the fail floor given 95% coverage — equivalent mutants exist, but surviving “real” mutants are not acceptable. |
| Stryker | **thresholds.break 70**, **thresholds.high 80** | Svelte UI has more equivalent mutants than PHP domain code; 70 is the fail floor, 80 is the healthy band. |

## Phase 0 — Foundations

Gate: Slim serves health JSON; Svelte static build is copied into `public/`; global sqlite migrates on boot; crypto + user-db wrap exist and are tested; frontend shell is 360px + bottom tabs.

- [x] Monorepo: backend (composer Slim 4, php-di, vlucas/phpdotenv) + frontend (SvelteKit adapter-static)
- [x] Env: `CIMT_MASTER_KEY`, `GOOGLE_CLIENT_ID`/`SECRET`, `GOOGLE_REDIRECT_URI`, `DATA_DIR`, `APP_URL`, `SESSION_SECURE`
- [x] Global migrator + peptide_types seed (semaglutide, tirzepatide, retatrutide, liraglutide)
- [x] Crypto service: AMK load, DEK mint, secretbox wrap/unwrap, secretstream file wrap
- [x] User DB template migrator; atomic encrypt/decrypt with flock + tmpfs
- [x] Slim `GET /api/v1/health`; SPA fallback for non-API GET; CORS not needed (same origin)
- [x] Frontend shell from first paint: 360px column, viewport-fit=cover, safe-area padding, bottom tab bar, API client with credentials

## Phase 1 — Accounts, Google, encrypted stores

Gate: register, password login, and Google login (verified email) all produce a session and a decryptable user sqlite that contains the identity snapshot and a default 0.5 mL / 50 IU syringe.

- [ ] `POST /auth/register`: validate email, Argon2id, mint DEK, create encrypted user DB, set session cookie
- [ ] `POST /auth/login` + `POST /auth/logout`; 401 on bad credentials without user enumeration beyond generic copy
- [ ] Google start/callback with state nonce; require `email_verified`; link by email or `google_sub`
- [ ] Email-match rules: Google onto password account; refuse second register; allow set-password from `/me` once authed
- [ ] Auth middleware: session → unwrap DEK → lock → decrypt → attach PDO → re-encrypt on terminate
- [ ] `GET /me` returns email, login methods, no DEK material
- [ ] SPA login/register + Continue with Google; session-gated routes redirect to `/login`

## Phase 2 — Compounds, uses, burndown

Gate: mix a vial, log a use in IU, see mg and remainder, edit the use, and get a newest-first history. Overdraw returns 422.

- [ ] `DoseCalculator` domain service + unit tests for U-100 and non-U-100 syringes
- [ ] `GET /peptide-types`; `GET/POST /compounds`; `GET /compounds/current` with computed remainder
- [ ] Compound immutability after first use (mg and BAC locked)
- [ ] Syringe CRUD; seed default 0.5 mL / 50 IU; snapshot syringe onto each use
- [ ] `POST /uses` against current compound; store iu, volume_ml, peptide_mg; reject overdraw
- [ ] `GET /uses` used_at DESC; `PATCH /uses` recalculates mg and re-checks remainder
- [ ] SPA: mix as full-screen form, inventory cards, log-use with thumb stepper + sticky Save, history 56px rows grouped by day
- [ ] Home remainder hero wired to `/compounds/current` (mg, mL, IU at default syringe)

## Phase 3 — UX finish + hardening

Gate: usable one-handed on iPhone SE-width Safari with empty/error states; tests around crypto and remainder; documented backup (AMK + global + user files).

- [ ] Empty states: no vial, no uses; remainder warning/danger tones; 422 copy on overdraw
- [ ] Settings: syringes, set password on Google-only accounts, logout
- [ ] Mobile shell QA: 360px floor, 48px targets, 56px rows, sticky Save above tabs, safe-area, 16px inputs, native datetime-local
- [ ] Tests: crypto wrap round-trip, flock serializes writes, dose math, auth email linking
- [ ] Rate-limit login and Google start; generic auth errors; secure cookie flags
- [ ] Backup note: copy `data/` + `CIMT_MASTER_KEY`; optional decrypt-to-sqlite export for a logged-in user
- [ ] DEK rewrap helper for AMK rotation; confirm old ciphertext still opens

## Suggested first slice after this branch

Password register/login plus a throwaway “write a row into the user DB” endpoint, to prove encrypt / lock / decrypt before Google or dose math.

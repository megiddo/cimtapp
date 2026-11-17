# PepTrack v2 — orchestrator checklist

Source of truth for the branch orchestrator. Product behavior is [v2.md](v2.md). Workers append one record per milestone to [v2-milestones.md](v2-milestones.md).

Stack base is branch **`v2`** (this plan, stacked on `0.3.5-close-vials`). Each next milestone branches from the **tip of the previous milestone**, not from `main`.

**Resume from:** done (v2.5-cleanup committed)

Do not launch M0. M0 is this documentation. The parent commits branch `v2` before any worker starts. A worker that finds a dirty tree on its base branch stops with `blocked`.

| # | Milestone | Branch | Base | APP_VERSION | Done |
| --- | --- | --- | --- | --- | --- |
| M0 | Plan and orchestrator docs | `v2` | `0.3.5-close-vials` | unchanged `v0.3.5` | [x] |
| M1 | Log-book API and v8 migration | `v2.1-log-api` | `v2` | `v2.1.0` | [x] |
| M2 | Log screen | `v2.2-log-ui` | `v2.1-log-api` | `v2.2.0` | [x] |
| M3 | Vial modal and inventory | `v2.3-vial` | `v2.2-log-ui` | `v2.3.0` | [x] |
| M4 | Settings | `v2.4-settings` | `v2.3-vial` | `v2.4.0` | [x] |
| M5 | Remove v0.3 stock routes | `v2.5-cleanup` | `v2.4-settings` | `v2.5.0` | [x] |

Workers follow the development skill and, for PHP, the PHP Slim rule. **v2 milestones use a lighter gate than main** so the stack can land without long Infection/Stryker runs on every branch. Repo-wide floors in [TESTING.md](TESTING.md) stay unchanged for `main` and release PRs.

### Required every milestone (M1–M5)

| Gate | Command | Fail below |
| --- | --- | --- |
| PHPUnit line coverage of `backend/src` | `cd backend && composer test` | **90%** (`scripts/check-coverage.php`) |
| Vitest line coverage of `frontend/src/lib/**/*.ts` | `cd frontend && npm test` | **90%** |
| Health | included in `composer test` | `GET /api/v1/health` returns ok |

Run the full suite. No `--filter` on sign-off. Do not commit `build/`, `coverage/`, or `.stryker-tmp/`.

### Optional (record in [v2-milestones.md](v2-milestones.md); do not block the commit)

| Gate | Command | When |
| --- | --- | --- |
| Infection | `cd backend && ./scripts/infection-capped.sh` | After M5 or locally when you want extra confidence |
| Stryker | `cd frontend && ./scripts/mutation-capped.sh` | Same |

**Watchdog:** each capped script kills the run after **30 minutes** (`MUTATION_TIME_LIMIT_SEC`, default **1800**). Exit **124** means timed out — record `mutation: watchdog timeout` in the milestone record and treat the milestone as **ok** if PHPUnit and Vitest passed. Do not restart mutation in the same milestone.

If mutation finishes under the cap and MSI is below 80, note it in the record. That is still not a v2 orchestrator failure.

No benchmark gate. No new log database and no `/debug` endpoint. Logging uses the Monolog `LoggerInterface` already registered in `backend/app/dependencies.php`.

## Worker preamble

Paste this to every implementation worker, then paste that milestone's section in full.

You are implementing one PepTrack v2 milestone. You have no parent chat. Read [v2.md](v2.md) for product behavior and this milestone's section below for scope. Do the milestone only.

- Branch from the **Base** named in the table. Branch name is the **Branch** cell. One branch, one commit. Do not push. Do not change git config. Do not start if the base worktree is dirty.
- `declare(strict_types=1)` on every new PHP file. Actions stay thin and extend the existing authenticated action. Domain services own rules. Repositories take the unlocked `PDO`. Schema changes go through a `UserSchemaStrategy` registered in `UserSchemaCatalog`, not DDL in a request handler. JSON responses use the existing `respondWithData` writer. New classes are `final` unless they are a designed base. Name the design pattern in the class docblock. Split a class past 500 lines and a method past 80 lines.
- PHP domain for the log book lives in `App\Domain\LogBook`. Do not fold it into `App\Domain\Dose`. v0.3 dose, BAC, and syringe-stock types stay until M5.
- Svelte pages fetch and render. Math and prefill rules live in `frontend/src/lib` so Vitest and Stryker see them. Follow the repository plus `DomainResult` split already used under `frontend/src/lib/repositories`.
- Set `frontend/src/lib/version.ts` `APP_VERSION` to this milestone's version. `version.test.ts` must keep matching `vMAJOR.MINOR.PATCH`. Name that version in the commit body.
- Check this milestone's boxes only. Append one record to [v2-milestones.md](v2-milestones.md) using the template in that file: branch, commit, what changed, PHPUnit line percent, Infection covered MSI, Vitest line percent, Stryker score when run, and which decision branches were log-tested.
- Commit message says why, and lists the checklist items this commit closes.
- UI milestones (M2, M3, M4) exercise the changed flow in a browser when browser tools are available: the main path, the empty state, and one related screen. Record what you could not open.
- Return exactly: `status` (`ok`, `blocked`, or `failed`), `branch`, `commit`, `gates` (PHPUnit and Vitest line percent; mutation numbers only if you ran them), `blockers` (empty when ok).

## Logging

Each **major decision branch** listed on the milestone calls `$logger->info($message, $context)` with the stable message in the table. Context may hold ids. It must not hold DEKs, session ids, or ciphertext.

A dedicated test method, named `testLogs…`, attaches `Monolog\Handler\TestHandler` and asserts that message. That test does not also assert the HTTP status. Behavior stays in its own test. Existing log tests from earlier milestones stay green.

UI milestones add no server log lines of their own. Their record says the M1 log tests still pass.

## Coexistence until M5

M1 adds v8 tables and v2 routes beside the v0.3 schema and routes. Old screens keep calling `/profiles`, `/compounds`, `/uses`, `/bac-bottles`, `/syringes`, and `/peptide-types`. New screens call `/people`, `/peptides`, `/syringe`, `/vials`, and `/log`. Neither side writes the other's tables. `GET /me` keeps `remainder` and `open_vials` until M5.

A new account's only profile is the seeded **Default**, with no compounds and no uses. v8 leaves `people` empty in that case. Any other store copies profiles into people.

## M1 — Log-book API and v8 migration

Branch `v2.1-log-api` from `v2`. `APP_VERSION` **v2.1.0**.

Ship the v8 user-store strategy and the v2 HTTP API in [v2.md](v2.md) (schema, migration, API, dose math, vial rules, log snapshot, last draw). No Svelte screens.

### In scope

- `UserStoreFormat::V8LogBook = 8`, and `current()` returns it.
- Strategy class on the same pattern as `AddSyringeArchive`: SQL file `backend/migrations/user/008_log_book.sql` plus PHP backfill inside `apply`. Register it last in `UserSchemaCatalog::default`.
- New tables only: `people`, `peptides`, `lid_colors`, `vials`, `vial_people`, `log_entries`, `account_syringe`. Columns and rules are the schema and log-row sections of [v2.md](v2.md).
- Backfill matches the migration section of [v2.md](v2.md), including the fresh-account exception in this checklist.
- Routes, all behind the existing session middleware: people list/create/rename/remove/restore, peptides list/create/remove, `GET`/`PUT /syringe`, `GET`/`POST /vials`, `GET /log/defaults`, log list/create/read/patch/delete. Shapes and status codes are the API section of [v2.md](v2.md).
- `POST /vials` find-or-creates a lid color from `lid_color_name` or uses `lid_color_id`. Derived name uses the same `formatConcentration` rules as `frontend/src/lib/dose.ts` (`5.00 mg/mL`, three decimals when the third digit is significant).
- Inject the existing `App\Domain\Auth\Clock` for the default `logged_at` suggestion.
- Services: people, peptides, account syringe, vials (name, supersede, `mg_left`, per-person drawn, probably-empty), log (snapshot, defaults, edit, delete). Repositories own SQL. A pure `VialNamer` builds the display name.

### Out of scope

- Any file under `frontend/src/routes`. Any change to v0.3 route registration or to `ViewMeAction`'s payload.
- Dropping, renaming, or dual-writing `profiles`, `compounds`, `uses`, `bac_bottles`, `syringe_profiles`, `user_peptide_types`.
- Browser work. Mutation tools are optional for v2. The version bump is the only required frontend edit.

### Tests that must exist

HTTP, through the real app the way `DoseHttpTest` does, plus a v7 sqlite fixture unlocked through `UserStore`:

- Fresh account: `GET /people` is `[]`, peptides are Tirzepatide and Retatrutide, syringe is 0.5 mL / 50 IU.
- v7 fixture: two profiles, one shared open compound, a newer open compound for one profile, an archived compound that has a use, a BAC bottle, a syringe quantity, and a compound adjustment. After unlock, each person has one current vial, the shared vial's people list lost the person who moved on, the archived dose is a log row whose snapshot still names that compound, `usage_offset_mg` equals the negated adjustment sum, `is_last_draw` is false, lid color is empty, BAC and syringe quantity are not on any v2 payload.
- Create vial `Tirzepatide - Noah - 5.00 mg/mL` and `Retatrutide - Noah, Sam - 10.00 mg/mL`.
- Replace: a vial on Noah and Sam, then a new vial for Noah only. Sam keeps the old vial. The old display name no longer includes Noah.
- `POST /log` stores person, BAC, vial mg, concentration, IU, dose mg, dose volume, and the account syringe. A later `PUT /syringe` does not change that row.
- Noah can log against Sam's current vial. Sam's assignment does not change. The dose counts in that vial's `mg_left` and in Noah's `drawn_mg`, not Sam's.
- A dose whose mg exceeds `mg_left` returns 201. `mg_left` may be negative. There is no `remaining_iu` field on the error.
- 422 for an unknown person, a superseded vial, a non-positive IU, and an IU with more than one decimal.
- Last draw on the latest row makes `probably_empty` true on `GET /vials` and on defaults. A newer row with last draw false clears it.
- Defaults: IU and `source` come from that person's latest row; `logged_at` is `Clock::now()`; no rows yields null `source` and null `iu`.
- Delete log returns 204 and drops that dose out of `mg_left`. It does not clear `superseded_at`.
- Duplicate visible person name is 422. Remove unassigns the current vial. Restore of a clashing name is 422.

### Log lines

| Message | When |
| --- | --- |
| `logbook.migration.applied` | v8 backfill finishes |
| `logbook.vial.created` | a current vial is saved, context includes who was unassigned |
| `logbook.entry.saved` | create or patch, context includes person id, vial id, iu, last-draw flag |
| `logbook.entry.rejected` | 422 on create or patch, context includes the field name |
| `logbook.entry.deleted` | delete succeeds |

### Done when

- [x] v8 strategy registered; fresh account and v7 fixture behave as written
- [x] v2 routes match the API section; v0.3 routes and `GET /me` payload unchanged
- [x] Snapshot, cross-person draw, negative `mg_left`, last draw, and replace covered by HTTP tests
- [x] Log tests assert the five messages on their own
- [x] `composer test` and `npm test` pass at 90% line coverage; `APP_VERSION` is `v2.1.0`
- [x] Record appended to [v2-milestones.md](v2-milestones.md)

## M2 — Log screen

Branch `v2.2-log-ui` from the M1 tip. `APP_VERSION` **v2.2.0**.

Ship the Log screen in [v2.md](v2.md) against the M1 API. Inventory and Settings pages stay as they are.

### In scope

- `/` renders Log. `/log/{id}` edits one row with the same form.
- Chrome tabs become Log (`/`, emphasized), Inventory (`/inventory`), Settings (`/settings`). Update `frontend/src/lib/chrome` and `chrome.test.ts`. Home and History leave the tab bar. Their routes may remain.
- `PersonChips`, `LogForm`, `IuField`, `DoseLine`, `SourceCaption`, `LogList`, `LogRow`, `NameModal` for adding a person, `StickySave`.
- `frontend/src/lib/log/logDefaults.ts` is the prefill view-model. Inputs are the defaults payload plus the client's current `datetime-local` value. Output is form state. Latest source wins. The date stays the client value unless the user edits it. `localStorage` key `peptrack.person` remembers the chip.
- Repository module for `/people` and `/log`. Pages call it through the existing `DomainResult` path. `runDomainMutation` wraps save.
- Empty states on the same route: no people shows the name field; a person with no current vial shows **Make a vial** with save disabled. The vial modal itself is M3. M2's button can open a placeholder that tells the user the vial step arrives with inventory, or it can no-op with that copy. It must not rebuild the v0.3 mix page.
- IU field selects its text on focus. Step is 0.5. Syringe is not on the form.
- Edit recalculates the preview from the row's snapshotted syringe and the selected vial. Delete asks once, then `DELETE /log/{id}`.
- New account path: Google login already exists. After login, `/` shows the name field when `/people` is empty.

### Out of scope

- Vial modal, pepcalc, inventory cards, lid color, probably-empty banner, settings content.
- Deleting `/use`, `/history`, or inventory subroutes.
- Writing `/compounds` or `/uses` from the new log page.

### Tests that must exist

Vitest on the view-model and the repository payload, not on markup:

- Defaults with a source keep IU and vial id and use the client date, not the source date.
- Defaults with null source leave IU empty and omit the "Same as" caption.
- Save body is `person_id`, `vial_id`, `iu`, `logged_at`, `is_last_draw`, and omits syringe fields.
- A negative remaining figure is not a reason to disable save. Missing person, missing vial, or non-positive IU is.
- `NAV_TABS` is Log, Inventory, Settings, with Log emphasized and href `/`.

### Browser

Open `/` signed in (or the project's existing test session). Confirm a prefilled dose saves and appears in the list, a person with no history gets an empty IU, and the Inventory tab still opens the old sheet.

### Done when

- [x] `/` is the log form and the recent list; `/log/{id}` edits and deletes
- [x] Prefill and save rules covered in `frontend/src/lib`
- [x] Tabs are Log, Inventory, Settings
- [x] Browser check recorded, including anything that could not be opened
- [x] `composer test` and `npm test` pass; `APP_VERSION` is `v2.2.0`
- [x] Record appended

## M3 — Vial modal and inventory

Branch `v2.3-vial` from the M2 tip. `APP_VERSION` **v2.3.0**.

Ship the shared vial modal, pepcalc, and the inventory screen from [v2.md](v2.md).

### In scope

- Replace `frontend/src/routes/inventory/+page.svelte` with current vials only. One `VialCard` per vial from `GET /vials`: derived name, lid line (**No lid color** when empty), mg left, per-person line when two or more people are assigned, **Probably empty** when the flag is set. **New vial** and **Replace** open the modal.
- `VialModal` is the only mix form. Callers: Inventory new, Inventory replace, Log **Make a vial**, Log **Recompound**, and the vial step after **Recalculate**.
- Pepcalc replaces the body of that same `ModalSheet` and returns. It uses `waterNeededPerBottle`. **Use this mix** writes peptide mg and BAC mL. It does not POST a vial. Concentration outside 0.5–20 mg/mL shows the existing warning and still allows the apply.
- `LidColorCombo`: typeahead, and **Add "{name}"** when there is no case-insensitive match. The new name is sent as `lid_color_name` on vial save.
- `PersonChecks` preserves check order for the derived name. `vialName.ts` matches the PHP `VialNamer` examples.
- `remaining.ts` formats mg left and **{mg} mg past the label** when negative. `probablyEmpty.ts` reads the API flag; it does not recompute from milligrams.
- Log banner when the selected vial is probably empty: **Recompound** and **Recalculate**, with the prefill rules in the vial-modal section of [v2.md](v2.md).
- Repository for `GET`/`POST /vials`.

### Out of scope

- Settings people, peptides, and syringe editors.
- Deleting inventory subroutes (`/inventory/vials`, `/inventory/water`, `/inventory/syringes`, and the rest). The new index simply does not link to them.
- Archive, BAC stock, syringe counts, and remaining adjustments.

### Tests that must exist

- `vialName` examples: `Tirzepatide - Noah - 5.00 mg/mL` and `Retatrutide - Noah, Sam - 10.00 mg/mL`.
- Remaining: offset and summed doses; negative copy; per-person sums ignore the other person.
- Pepcalc apply produces the BAC volume from `waterNeededPerBottle` for 10 mg, 2.5 mg target, 25 IU, 0.5 mL / 50 IU.
- Recompound field set equals the current vial. Recalculate field set starts from vial mg plus the person's latest dose mg and IU.
- Probably-empty copy follows the flag, including when mg left is still positive.

### Browser

Create a vial from Inventory, see the derived name, log a last draw from Log, see the banner on both screens, recompound, and confirm the old vial is gone from Inventory while the earlier log row still shows the old name.

### Done when

- [ ] One vial modal serves Inventory and Log, including pepcalc inside the same sheet
- [ ] Inventory lists only current vials and the convenience totals
- [ ] Name, remaining, and prefill rules covered in `frontend/src/lib`
- [ ] Browser check recorded
- [ ] `composer test` and `npm test` pass; `APP_VERSION` is `v2.3.0`
- [ ] Record appended

## M4 — Settings

Branch `v2.4-settings` from the M3 tip. `APP_VERSION` **v2.4.0**.

Replace settings content. Tabs already point at `/settings` from M2.

### In scope

- `/settings` sections: People (add, rename, remove, restore), Peptides (add, remove; seeded names already come from the API), Syringe (two numbers, `GET`/`PUT /syringe`), Account (email, export, log out, `APP_VERSION`).
- Removed people sit under **Removed** with Restore. Removing the last person is allowed. Log then shows the M2 add-person state.
- A removed peptide disappears from the vial modal picker. Rows that already snapshotted the name still show it.
- Syringe change affects the next log row only. State that in the section copy in one short line.
- Login page stays Google-first with the existing secondary email control. Do not add password fields.
- `NameModal` is reused for person and peptide names. Empty and duplicate names surface the API 422 field text.

### Out of scope

- Deleting v0.3 settings blocks' server routes. The old syringe list, BAC, and password section leave this page. Password endpoints stay.
- Changing chrome tab identity.

### Tests that must exist

- Client mapper: a 422 `name` field becomes the string shown under the name field.
- Syringe form sends `{ volume_ml, capacity_iu }` and refuses either value at 0 before the request.
- People list splits on `removed_at` into the visible section and the Removed section.

### Browser

Add a person from Settings and see them as a Log chip. Remove them and restore them. Add a peptide and see it in the vial modal. Change the syringe and save a new log; an older log row's displayed mg does not change.

### Done when

- [ ] Settings edits people, peptides, and the one syringe
- [ ] Login is unchanged and Google-first
- [ ] Mapper tests cover 422 names, syringe bounds, and removed people
- [ ] Browser check recorded
- [ ] `composer test` and `npm test` pass; `APP_VERSION` is `v2.4.0`
- [ ] Record appended

## M5 — Remove v0.3 stock routes

Branch `v2.5-cleanup` from the M4 tip. `APP_VERSION` **v2.5.0**.

Remove the stock ledger from the running app. Leave the sqlite tables in place so old files still open and export.

### In scope

- Delete route trees: `/use`, `/history`, `/inventory/new`, `/inventory/vials`, `/inventory/water`, `/inventory/syringes`, `/inventory/peptides`, `/inventory/[id]`. `/`, `/log/[id]`, `/inventory` (the M3 page), `/settings`, and `/login` stay.
- Unregister and delete the actions used only by `/compounds`, `/uses`, `/bac-bottles`, `/syringes`, `/peptide-types`, and `/profiles`.
- `GET /me` returns email and login flags. It omits `remainder` and `open_vials`.
- Delete frontend repositories, view-models, and components that only served those routes. `frontend/src/lib` must not reference those six paths.
- Keep auth, health, export, backup, crypto, and the v2 routes.
- Fix or delete tests that called the removed routes. `HealthActionTest` and the auth HTTP tests stay.
- v8 strategy and v2 HTTP tests from M1 stay green without the old routes mounted.

### Out of scope

- Dropping v0.3 tables or rewriting historical ciphertext.
- New product behavior. If a v2 screen still depends on a removed call, point it at the v2 API instead of keeping the old route.

### Tests that must exist

- A source scan, in PHPUnit or Vitest, fails if `backend/app/routes.php` or `frontend/src` still contains `/compounds`, `/uses`, `/bac-bottles`, `/syringes`, `/peptide-types`, or `/profiles` as API paths. Allow the words inside v8 migration SQL and the v7 fixture, which still describe the old tables.
- `GET /me` body has no `remainder` and no `open_vials`.
- One v2 flow still passes over HTTP: create person, create vial, log a dose, read it back.

### Done when

- [ ] Old stock routes and their SPA are gone; v8 tables remain
- [ ] `GET /me` is identity only
- [ ] Scan test and the v2 HTTP smoke test pass
- [ ] `composer test` and `npm test` pass at 90% line coverage
- [ ] `APP_VERSION` is `v2.5.0`
- [ ] Record appended

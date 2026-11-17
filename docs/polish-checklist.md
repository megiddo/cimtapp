# PepTrack polish — orchestrator checklist

Source of truth for the branch orchestrator. Product behavior already shipped is [docs/v2.md](docs/v2.md) and [docs/v2-checklist.md](docs/v2-checklist.md). This queue is the follow-up. Workers append one record per milestone to [docs/polish-milestones.md](docs/polish-milestones.md). Do not rewrite records in [docs/v2-milestones.md](docs/v2-milestones.md).

Stack base is branch **`v2.5-cleanup`**. Each next milestone branches from the **tip of the previous milestone**, not from `main`.

**Resume from:** — polish queue complete (`v2.8-settings`)

Do not launch P0. P0 is this documentation. The parent commits branch `v2.6` (this file at `docs/polish-checklist.md`, plus an empty record file `docs/polish-milestones.md`) before any worker starts. A worker that finds a dirty tree on its base branch stops with `blocked`. Leave untracked `Untitled/` untracked. Do not `git add -A`.

| # | Milestone | Branch | Base | APP_VERSION | Done |
| --- | --- | --- | --- | --- | --- |
| P0 | Plan and orchestrator docs | `v2.6` | `v2.5-cleanup` | unchanged `v2.5.0` | [x] |
| P1 | Inventory names and modal | `v2.6-inventory` | `v2.6` | `v2.6.0` | [x] |
| P2 | Log screen | `v2.7-log` | `v2.6-inventory` | `v2.7.0` | [x] |
| P3 | Settings trim | `v2.8-settings` | `v2.7-log` | `v2.8.0` | [x] |

Workers follow the development skill and, for PHP, the PHP Slim rule. **These milestones use the same lighter gate as v2.** Repo-wide floors in [TESTING.md](TESTING.md) stay unchanged for `main` and release PRs.

### Required every milestone (P1–P3)

| Gate | Command | Fail below |
| --- | --- | --- |
| PHPUnit line coverage of `backend/src` | `cd backend && composer test` | **90%** (`scripts/check-coverage.php`) |
| Vitest line coverage of `frontend/src/lib/**/*.ts` | `cd frontend && npm test` | **90%** |
| Health | included in `composer test` | `GET /api/v1/health` returns ok |

Run the full suite. No `--filter` on sign-off. Do not commit `build/`, `coverage/`, or `.stryker-tmp/`.

### Optional (record in [docs/polish-milestones.md](docs/polish-milestones.md); do not block the commit)

| Gate | Command | When |
| --- | --- | --- |
| Infection | `cd backend && ./scripts/infection-capped.sh` | After P3 or locally when you want extra confidence |
| Stryker | `cd frontend && ./scripts/mutation-capped.sh` | Same |

**Watchdog:** each capped script kills the run after **30 minutes** (`MUTATION_TIME_LIMIT_SEC`, default **1800**). Exit **124** means timed out — record `mutation: watchdog timeout` in the milestone record and treat the milestone as **ok** if PHPUnit and Vitest passed. Do not restart mutation in the same milestone.

If mutation finishes under the cap and MSI is below 80, note it in the record. That is still not an orchestrator failure.

No benchmark gate. No new log database and no `/debug` endpoint. Logging uses the Monolog `LoggerInterface` already registered in `backend/app/dependencies.php`. These milestones add **no new log lines**. Existing `testLogs…` methods from v2 M1 stay green. The milestone record says those lines still pass.

## Worker preamble

Paste this to every implementation worker, then paste that milestone's section in full.

You are implementing one PepTrack polish milestone. You have no parent chat. Read this milestone's section below for scope. Do the milestone only.

- Branch from the **Base** named in the table. Branch name is the **Branch** cell. One branch, one commit. Do not push. Do not change git config. Do not start if the base worktree is dirty.
- `declare(strict_types=1)` on every new PHP file. Actions stay thin and extend the existing authenticated action. Domain services own rules. Repositories take the unlocked `PDO`. No schema change in this queue. JSON responses use the existing `respondWithData` writer. New classes are `final` unless they are a designed base. Name the design pattern in the class docblock. Split a class past 500 lines and a method past 80 lines.
- Svelte pages fetch and render. Math and name formatting live in `frontend/src/lib` so Vitest sees them. Follow the repository plus `DomainResult` split already used under `frontend/src/lib/repositories`.
- Set `frontend/src/lib/version.ts` `APP_VERSION` to this milestone's version. `version.test.ts` must keep matching `vMAJOR.MINOR.PATCH`. Name that version in the commit body.
- Check this milestone's boxes only. Append one record to [docs/polish-milestones.md](docs/polish-milestones.md) using the template in that file: branch, commit, what changed, PHPUnit line percent, Vitest line percent, mutation only if run, and browser notes.
- Commit message says why, and lists the checklist items this commit closes.
- UI milestones exercise the changed flow in a browser when browser tools are available: the main path, the empty state, and one related screen. Record what you could not open.
- Return exactly: `status` (`ok`, `blocked`, or `failed`), `branch`, `commit`, `gates` (PHPUnit and Vitest line percent; mutation numbers only if you ran them), `blockers` (empty when ok).

## Locked decisions

- Vial display name is `{Peptide} - {people} - {mg per IU}`. Rate, not totals. Example: 10 mg peptide in 2 mL BAC, account syringe 0.5 mL / 50 IU (100 IU per mL), concentration 5.00 mg/mL, name **`Tirzepatide - Noah - 0.05 mg/IU`**. 10 mg in 1 mL is **`Retatrutide - Noah, Sam - 0.10 mg/IU`**.
- `mgPerIu = concentrationMgPerMl / (capacityIu / volumeMl)`. Read `account_syringe`. Do not add a settings control for it.
- Email and password login stay. Only the Settings password block goes.
- Syringe HTTP API and the stored default stay. Only the Settings syringe section goes.
- Existing saved `display_name` values are not rewritten until the next create or person reassignment.
- Do not delete password, export, backup, or syringe routes.

## P1 — Inventory names and modal

Branch `v2.6-inventory` from `v2.6`. `APP_VERSION` **v2.6.0**.

### In scope

- [`backend/src/Domain/LogBook/VialNamer.php`](backend/src/Domain/LogBook/VialNamer.php) builds `{peptide} - {people} - {rate} mg/IU` using the same rounding as `LogBookFormatter::formatMg`.
- Call sites pass the account-syringe rate: [`VialService`](backend/src/Domain/LogBook/VialService.php) create, [`VialAssignmentService::refreshDisplayName`](backend/src/Domain/LogBook/VialAssignmentService.php), and both name writes in [`LogBookMigrationBackfill`](backend/src/Infrastructure/Persistence/UserSchema/LogBookMigrationBackfill.php). `seedAccountSyringe` already runs before those names.
- [`frontend/src/lib/logbook/vialName.ts`](frontend/src/lib/logbook/vialName.ts) uses the same formula for the modal preview. Default the conversion to 100 IU/mL when the preview has no syringe in hand. The live modal may pass the account syringe if it already loads one; do not add a syringe form.
- One sentence in [docs/v2.md](docs/v2.md) so the display-name example matches `0.05 mg/IU` instead of `mg/mL`. Leave the rest of that spec alone.
- Checkboxes: [`.auth-form input`](frontend/src/styles/forms.css) sets `width: 100%` and `min-height: 48px`, which blows up `input[type=checkbox]` in the vial modal. Exclude checkboxes (about 1.25rem, no extra padding). `.check` is a row: box, then the person’s name.
- Calculator: [`.modal-backdrop`](frontend/src/styles/modals.css) is `align-items: flex-end`. Add a placement on [`ModalSheet.svelte`](frontend/src/lib/ui/ModalSheet.svelte). The vial sheet stays on the bottom. When Calculate is open in [`VialModal.svelte`](frontend/src/lib/logbook/VialModal.svelte), center the dialog (`align-items: center`, inset margin, radius on all corners, `max-height` with scroll). Closing Calculate returns the bottom sheet.

### Out of scope

- Log page, history route, settings password, settings syringe.
- Rewriting `display_name` on vials that are not created or reassigned in this request.
- Deleting `formatConcentration` or the mg/mL warning copy inside the calculator.

### Tests that must exist

- [`VialNamerTest`](backend/tests/Domain/LogBook/VialNamerTest.php): `Tirzepatide - Noah - 0.05 mg/IU` at 5 mg/mL and 100 IU/mL; `Retatrutide - Noah, Sam - 0.10 mg/IU` at 10 mg/mL and 100 IU/mL.
- [`LogBookHttpTest`](backend/tests/Application/Actions/LogBook/LogBookHttpTest.php) display-name asserts match those strings for the existing 10 mg / 2 mL and 10 mg / 1 mL creates (fresh account syringe is 0.5 mL / 50 IU).
- [`vialName.test.ts`](frontend/src/lib/logbook/vialName.test.ts) expects the same two strings.

### Browser

Open Inventory, start a vial, confirm the preview name ends in `mg/IU` and the person checkboxes are small with the name beside them. Open Calculate and confirm the dialog sits in the center and scrolls instead of hanging off the bottom edge. Close Calculate and confirm the sheet returns to the bottom.

### Done when

- [x] New and reassigned vials are named `{Peptide} - {people} - {n} mg/IU`
- [x] Person checkboxes are small and labeled
- [x] Calculate centers the dialog; closing it restores the bottom sheet
- [x] Browser check recorded
- [x] `composer test` and `npm test` pass; `APP_VERSION` is `v2.6.0`
- [x] Record appended

## P2 — Log screen

Branch `v2.7-log` from the P1 tip. `APP_VERSION` **v2.7.0**.

### In scope

- [`PersonChips.svelte`](frontend/src/lib/logbook/PersonChips.svelte) on [`LogScreen.svelte`](frontend/src/lib/logbook/LogScreen.svelte): a **Person** heading above the chips, including the add-person chip, so the row reads as who the dose is for.
- [`IuField.svelte`](frontend/src/lib/logbook/IuField.svelte): remove the − and + buttons. The input still opens on the last logged IU from `fetchLogDefaults`.
- Last-draw checkbox keeps its behavior. Label text is **Last draw — vial is empty after this dose**. It uses the small checkbox row from P1.
- Under the vial select, show the selected vial’s `mg_left` from `GET /vials`, for example **8.75 mg left in this vial**. Refresh vials after a successful save.
- The log page requests `limit: 1` and renders only that row.
- **History** link to `/history?person={id}`. New [`frontend/src/routes/history/+page.svelte`](frontend/src/routes/history/+page.svelte) lists that person’s entries with the existing log list and edit links (`/log/{id}`). Register the route in [`frontend/src/lib/chrome/policy.ts`](frontend/src/lib/chrome/policy.ts) (title History, back to `/`). [`frontend/src/lib/chrome/nav.ts`](frontend/src/lib/chrome/nav.ts) treats `/history` as the Log tab.

### Out of scope

- New log API fields. `GET /log` already accepts `person_id` and `limit`.
- Changing vial naming, the calculator, or Settings.
- Pagination beyond a single list request. A limit of 100 on the history page is enough.

### Tests that must exist

- Chrome tests cover History title, back href `/`, and the Log tab active on `/history`.
- A unit test, if IU stepping is only reachable from the removed buttons, may drop the stepper assertions. Defaults still seed the IU field.

### Browser

Select a person, confirm the heading, a prefilled IU with no steppers, the last-draw label, and the mg-left line. Save is not required if a vial already has a dose: the log page shows one row and History shows the rest. Empty person list still offers Add person.

### Done when

- [x] Person chips are labeled; IU has no stepper; last draw is labeled
- [x] Current vial shows mg left
- [x] Log shows one row; History lists the person’s rows
- [x] Browser check recorded
- [x] `composer test` and `npm test` pass; `APP_VERSION` is `v2.7.0`
- [x] Record appended

## P3 — Settings trim

Branch `v2.8-settings` from the P2 tip. `APP_VERSION` **v2.8.0**.

### In scope

- [`frontend/src/routes/settings/+page.svelte`](frontend/src/routes/settings/+page.svelte): remove `PasswordSection`, the syringe form, and the sentence about changing the syringe.
- Keep people, peptides, export and backup (`DataSection`), log out, email, and `APP_VERSION`.
- Stop calling `fetchAccountSyringe` / `putAccountSyringe` from this page. Leave [`frontend/src/lib/repositories/syringe.ts`](frontend/src/lib/repositories/syringe.ts) in place if dose or vial code still imports it.

### Out of scope

- Deleting password, syringe, export, or backup routes.
- Changing [`frontend/src/routes/login/+page.svelte`](frontend/src/routes/login/+page.svelte). Email and password login stay.
- Inventory or log layout.

### Tests that must exist

- Settings tests that only covered the syringe form are updated or removed so `npm test` stays green. People, peptide, and field-error tests stay.

### Browser

Open Settings. Account shows email, export or backup, and log out. There is no password block and no syringe block. People and peptides still add. Version at the bottom is `v2.8.0`.

### Done when

- [x] Settings has no password section and no syringe section
- [x] People, peptides, export, and log out remain
- [x] Browser check recorded
- [x] `composer test` and `npm test` pass; `APP_VERSION` is `v2.8.0`
- [x] Record appended

## Record template

`docs/polish-milestones.md` starts with this template and no completed sections. Workers append below it.

```markdown
## P1 — v2.6-inventory

- Branch: `v2.6-inventory`
- Commit: `<sha>`
- Version: `v2.6.0`
- What changed: <one short paragraph>
- PHPUnit line: <percent> (`composer test`)
- Vitest line: <percent> (`npm test`)
- Infection / Stryker: optional — <percent, "not run", or "watchdog timeout">
- Log-tested branches: v2 M1 lines still pass; no new lines
- Browser: <what was exercised, or what could not be opened>
```

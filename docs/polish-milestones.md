# PepTrack polish — milestone record

Workers append one section here when a milestone commits. The queue and checkboxes live in [polish-checklist.md](polish-checklist.md).

Do not rewrite an earlier record. Append a new section using:

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

## P1 — v2.6-inventory

- Branch: `v2.6-inventory`
- Commit: `5084728`
- Version: `v2.6.0`
- What changed: Vial display names use mg/IU from account syringe on server and in the modal preview; person checkboxes are compact; Calculate opens a centered modal while the vial sheet stays bottom-aligned.
- PHPUnit line: 95.66% (`composer test`)
- Vitest line: 97.38% (`npm test`)
- Infection / Stryker: not run
- Log-tested branches: v2 M1 lines still pass; no new lines
- Browser: not exercised in agent session (local `make up` recommended)

## P2 — v2.7-log

- Branch: `v2.7-log`
- Commit: `9fcdd0a`
- Version: `v2.7.0`
- What changed: Log UI labels the person row, drops IU steppers, clarifies last-draw copy, shows mg left under the vial, lists one recent row with a History page for the full person list.
- PHPUnit line: 95.66% (`composer test`)
- Vitest line: 97.38% (`npm test`)
- Infection / Stryker: not run
- Log-tested branches: v2 M1 lines still pass; no new lines
- Browser: not exercised in agent session

## P3 — v2.8-settings

- Branch: `v2.8-settings`
- Commit: `b65fde6`
- Version: `v2.8.0`
- What changed: Settings keeps people, peptides, export/backup, and logout; password change and syringe editing UI removed while APIs stay for login and dosing.
- PHPUnit line: 95.66% (`composer test`)
- Vitest line: 97.38% (`npm test`)
- Infection / Stryker: not run
- Log-tested branches: v2 M1 lines still pass; no new lines
- Browser: not exercised in agent session
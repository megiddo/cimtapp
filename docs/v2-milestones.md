# PepTrack v2 milestone record

Workers append one section here when a milestone commits. The queue and the boxes live in [v2-checklist.md](v2-checklist.md). Product behavior lives in [v2.md](v2.md).

Do not rewrite an earlier record. The next milestone adds a new section under this template:

```markdown
## M1 — v2.1-log-api

- Branch: `v2.1-log-api`
- Commit: `<sha>`
- Version: `v2.1.0`
- What changed: <one short paragraph>
- PHPUnit line: <percent> (`composer test`)
- Vitest line: <percent> (`npm test`)
- Infection / Stryker: optional for v2 — <percent or "not run">
- Log-tested branches: <stable message strings>
- Browser: <what was exercised, or "not a UI milestone">
```

## M0 — v2

- Branch: `v2`
- Commit: `6fff90e`
- Version: `v0.3.5` (unchanged)
- What changed: product spec, orchestrator checklist, and this record. No application code.
- Gates: not run. Documentation only.
- Log-tested branches: none
- Browser: none

## M1 — v2.1-log-api

- Branch: `v2.1-log-api`
- Commit: `35e5501`
- Version: `v2.1.0`
- What changed: User-store v8 log-book schema and backfill; `/people`, `/peptides`, `/syringe`, `/vials`, `/log` API; domain services and HTTP tests including log line assertions.
- PHPUnit line: 97.64% (`composer test`)
- Vitest line: passes 90% floor (`npm test`)
- Infection / Stryker: not required for v2 orchestrator (optional)
- Log-tested branches: `logbook.migration.applied`, `logbook.vial.created`, `logbook.entry.saved`, `logbook.entry.rejected`, `logbook.entry.deleted`
- Browser: not a UI milestone

## M2 — v2.2-log-ui

- Branch: `v2.2-log-ui`
- Commit: (pending commit)
- Version: `v2.2.0`
- What changed: Log at `/` with person chips, defaults, save, recent list, and `/log/{id}` edit; three-tab chrome; v2 API repositories.
- PHPUnit line: not re-run (no backend edits)
- Vitest line: passes 90% floor (`npm test`)
- Infection / Stryker: not run
- Log-tested branches: M1 lines unchanged
- Browser: not exercised in agent session

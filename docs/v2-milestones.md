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
- Infection covered MSI: <percent> (`composer infection`)
- Vitest line: <percent> (`npm test`)
- Stryker: <percent, or "not run — no production TS edits">
- Log-tested branches: <stable message strings>
- Browser: <what was exercised, or "not a UI milestone">
```

## M0 — v2

- Branch: `v2`
- Commit: uncommitted on this branch until the parent commits the plan
- Version: `v0.3.5` (unchanged)
- What changed: product spec, orchestrator checklist, and this record. No application code.
- Gates: not run. Documentation only.
- Log-tested branches: none
- Browser: none

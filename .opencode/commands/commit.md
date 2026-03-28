---
description: Suggest a conventional commit message
subtask: true
---

Show staged changes: !`git diff --staged 2>&1`

If nothing is staged, show all changes: !`git diff HEAD 2>&1`

Suggest a commit message following Conventional Commits:
- `feat(scope): description` — new feature
- `fix(scope): description` — bug fix
- `refactor(scope): description` — code change that neither fixes nor adds
- `test(type): description` — adding/updating tests
- `docs(scope): description` — documentation only
- `chore(scope): description` — config, deps, CI

Scope options: `contracts`, `data`, `drivers`, `services`, `commands`, `config`

Format: `type(scope): description`

Only suggest — do not commit.

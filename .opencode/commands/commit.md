---
description: Suggest a conventional commit message from staged changes
agent: laravel-expert
template: Suggest a conventional commit message from staged changes
---

Show staged changes:

!`git diff --staged 2>&1`

!`git status --short 2>&1`

Based on the changes, suggest a commit message following the Conventional Commits format:
`type(scope): description`

Types: feat / fix / refactor / test / docs / chore / perf
Scopes: contracts / drivers / services / commands / tests / config

Example output:
```
feat(drivers): add OpenAI embedding driver with embedBatch support
```

Only suggest the message. Do not commit. The user will decide and run:
```bash
git commit -m "suggested message"
```

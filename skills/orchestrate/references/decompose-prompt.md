# Issue Decomposition Heuristics

Use these rules when classifying GitHub issues into subtasks.

## Issue Size Classification

| Label / Signal | Classification | Agent count |
|---|---|---|
| `bug`, `fix`, error in title | Atomic | 1 agent |
| `enhancement`, `feature` with small scope | Atomic | 1 agent |
| `feature` touching 3+ modules | Composite | 2–4 agents |
| `refactor` across multiple files | Composite | 2–5 agents |
| `test` coverage task | Atomic per module | 1 agent per module |
| `docs` update | Atomic | 1 agent |

## Splitting Rules

Split an issue into subtasks when:
- It touches more than 2 separate modules/packages
- It requires changes to both frontend and backend
- It has multiple distinct acceptance criteria that don't depend on each other
- Estimated lines of change > 200

Do NOT split when:
- Changes are tightly coupled (e.g., changing a function signature used everywhere)
- The issue is a single bug with a single root cause
- Total scope is < 50 lines

## Dependency Detection

Flag a dependency between two subtasks when:
- Subtask B modifies a file that Subtask A also modifies
- Subtask B needs an API or function that Subtask A creates
- Both tasks touch the same database schema or migration file

When a dependency is detected:
1. Try to reorganize to eliminate the dependency
2. If unavoidable, schedule the dependency in a later round (sequential batching)
3. Note the dependency clearly in the plan shown to the user

## GitHub Issue Body Parsing

When reading issue bodies, extract:
- **Acceptance criteria** — lines starting with `- [ ]` or under "## Acceptance Criteria"
- **Affected files** — lines mentioning file paths or module names
- **Linked PRs or issues** — `#123` references (may indicate dependencies)
- **Labels** — `bug`, `enhancement`, `good first issue`, `breaking change`

Breaking change issues should always be flagged to the user before spawning agents.

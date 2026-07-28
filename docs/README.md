# Aureo Documentation

Start here. Each document has one job.

| Document | Answers | Audience |
|---|---|---|
| **[SPEC.md](./SPEC.md)** | What does Aureo do? Domain model, lifecycles, rules, constraints. | Everyone |
| **[USERGUIDE.md](./USERGUIDE.md)** | How do I use it? | End users, team leads |
| **[DEPLOYMENT.md](./DEPLOYMENT.md)** | How do I install, configure, upgrade and operate it? | Administrators |
| **[ARCHITECTURE.md](./ARCHITECTURE.md)** | How is it built, and why that way? | Developers |
| **[SECURITY.md](./SECURITY.md)** | What protects it, and how do I report a vulnerability? | Administrators, developers |
| **[../CONTRIBUTING.md](../CONTRIBUTING.md)** | How do I contribute a change? | Contributors |
| **[../.claude/CLAUDE.md](../.claude/CLAUDE.md)** | Which non-obvious constraints will bite me? | Developers, agents |

---

## Common starting points

**I'm evaluating Aureo** → [SPEC.md](./SPEC.md), then the
[project README](../README.md).

**I'm setting up a server** → [DEPLOYMENT.md](./DEPLOYMENT.md), then the
[go-live checklist](./DEPLOYMENT.md#go-live-checklist) and [SECURITY.md](./SECURITY.md).

**I'm using Aureo for the first time** → [USERGUIDE.md](./USERGUIDE.md).

**I'm about to change the code** → [ARCHITECTURE.md](./ARCHITECTURE.md) and
[../.claude/CLAUDE.md](../.claude/CLAUDE.md), then [../CONTRIBUTING.md](../CONTRIBUTING.md).

**Something is broken** → `log/aureo.log` first, then
[DEPLOYMENT.md § Troubleshooting](./DEPLOYMENT.md#troubleshooting).

---

## Keeping documentation honest

[SPEC.md](./SPEC.md) is a **living document**: it describes the code on `master`, not intentions.
Behavior changes and the spec change in the same pull request. Anything unbuilt belongs under
*Planned* or *Out of scope*, never in the present tense.

The same applies to the rest: a documented command that no longer works is worse than no
documentation, because it costs someone their afternoon before they stop trusting it.

Feature proposals under `docs/specs/` and the vendored agent skills under `docs/superpowers/`
are developer-local and intentionally excluded from version control.

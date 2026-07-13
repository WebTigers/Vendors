# AGENTS.md — working in the Tiger Vendor Registry

Instructions for an AI assistant (or a new contributor) editing this repo. For *how to submit a
module* read [CONTRIBUTING.md](CONTRIBUTING.md); for *how the reviewer works* read
[docs/BOT.md](docs/BOT.md). This file is the conventions for changing the **registry itself**.

## What this repo is

**The registry IS this git repo — there is no server.** It's a catalog of *listings* (thin
metadata pointing at each module's own public repo). Tiger fetches the compiled
[`data/index.json`](data/index.json) a few times a day, caches it, and searches it locally. The
repo never hosts module code, and no single party is a chokepoint (fork it, repoint
`tiger.modules.registry`, done). Its two values are **transparency** (every listing, review, and
sponsorship is a public commit) and **antifragility**.

## The golden rules

- **One file per module** at `data/<GitHubOrg>_<GitHubRepo>.json`. Never let two PRs touch the
  same file — that's why the key is the repo, not the slug.
- **Never hand-edit `data/index.json`.** It's a build artifact — `scripts/compile-index.php`
  regenerates it (CI, on any `data/` change), pure vendor data sorted alphabetically. Edit
  listings, recompile.
- **Never write a listing's `review` block.** The `tiger-vendor-bot` owns it. Only listings with
  `review.status == "accepted"` reach the index; pending/rejected stay out.
- **`schema/registry.v1.json` is the contract.** Change it and you must update
  `scripts/review.php` (the deterministic gate) and CONTRIBUTING.md in the same change.

## Two axes: `type` (kind) and `category` (domain) — keep them separate

- **`type`** = `theme` | `app` | `plugin` — a **searchable label, NON-behavioral.** It never
  gates anything. `app` (large feature/product) vs `plugin` (small tweak) is scale, not
  mechanism — both install identically. A listing may omit it; the index defaults it to `plugin`.
- **`category`** = subject-matter domain (Commerce, Media, Auth, …). Orthogonal to `type` — a
  theme can be a Media-domain theme.
- **Capabilities are detected, not declared.** Tiger decides what a module *does* at activation
  by what it *ships* — a `theme.json` → theme resolution, a `migrations/` folder → run
  migrations — not by the `type` word. So `review.php` picks the manifest by **presence**
  (`module.json` else `theme.json`), never by `type`. A theme's slug is `theme-` + its
  `theme.json` `key`.

## Sponsorship — `data/sponsored.json` (WebTigers-curated)

Placement is monetizable but **transparent**: [`data/sponsored.json`](data/sponsored.json) (edited
only by WebTigers) maps a listing key `<Org>_<Repo>` → `{ priority, label, until }`. It is **not**
baked into `index.json` — **Tiger fetches it alongside the index and merges the `priority` at search
time** (client-side, in `Tiger_Module_Registry`), so placement changes need no recompile. In the
directory's default **Featured** sort, higher `priority` floats up with a badge; the Title and
Latest sorts ignore it. Vendors **cannot** rank themselves — it's a separate curated file, a public
commit, and auto-expires via `until`. **Enforced in CI:** `.github/workflows/guard-sponsored.yml`
hard-fails any PR that modifies `data/sponsored.json` unless it's from the registry owner
(`author_association == OWNER`, so it survives a rename), and `.github/CODEOWNERS` requires owner
review. Make the guard a **required status check** in branch protection to seal it.

## Validate locally before you PR

```bash
php scripts/review.php data/<Org>_<Repo>.json     # deterministic gate (fetches the pinned ref live)
php scripts/compile-index.php                      # rebuild the pure index (alpha-sorted); prints module count
```

The gate needs no keys. The AI pass (`scripts/review-ai.php`) is advisory and needs a model token
(see docs/BOT.md); a human always makes the final merge call — code that runs in someone's app is
never auto-merged.

## PR etiquette

- A **vendor submission** is exactly one new `data/*.json` file.
- A **maintainer/tooling change** (schema, scripts, docs, sponsorship) is its own PR, kept
  separate from vendor listings where practical.
- Don't commit secrets, tokens, or module code here — listings point at repos; they never embed.

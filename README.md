# Tiger Vendor Registry

The open, community catalog of installable [Tiger](https://github.com/WebTigers/TigerCore)
modules. **There is no registry server** — this git repo *is* the registry. Tiger fetches
[`data/index.json`](data/index.json) a few times a day, caches it, and searches it locally
(Modules → **Add New** → *Browse the directory*).

Because it's a public repo:

- **No chokepoint.** No single party can cut anyone off — modules install from *their own*
  public repos; this repo only *lists* them. If WebTigers ever went dark, fork this repo and
  point Tiger at your copy (`tiger.modules.registry`). The catalog is antifragile by design.
- **Everything is auditable.** Every listing, every review, every change is a public commit.

## How it works

```
data/<Org>_<Repo>.json   ── one thin listing per module (PR-friendly: no file ever collides)
        │
   scripts/compile-index.php  (CI, on any data/ change)
        ▼
data/index.json          ── the compiled search index Tiger fetches
```

A **listing** ([`schema/registry.v1.json`](schema/registry.v1.json)) is *metadata that points
at a public module repo* — name, vendor, GitHub URL, website/contact, version, logo/hero,
description, license, pricing. The rich write-up lives in the module repo's **`TIGER.md`**
(shown as the "View more" modal in Tiger). Tiger installs the module from the repo's pinned
release — this registry never hosts code.

## Submit your module

Open a PR adding **one file**: [`CONTRIBUTING.md`](CONTRIBUTING.md) has the steps. The
`tiger-vendor-bot` reviews open PRs a few times a day (schema, public repo, `module.json` +
`TIGER.md` at the pinned ref, license, release exists — see
[`scripts/review.php`](scripts/review.php)), then opens a PR **on your repo** with its
findings. Accepted → your listing merges and `index.json` recompiles. Not yet → fix and it
re-runs.

**Rules:** public repos only (code must be reviewable); a declared license (public ≠ free —
proprietary-but-public is fine); and we *encourage a free tier* for every module.

## License

Listings are © their vendors. This repo's tooling is MIT — see [LICENSE](LICENSE).

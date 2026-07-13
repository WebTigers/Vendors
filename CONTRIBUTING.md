# Submitting a module to the Tiger Vendor Registry

One PR, one file. Takes about two minutes.

## 1. Prerequisites (in your module repo)

- It's a **public** GitHub repo (private repos are rejected — module code must be reviewable).
- Its root has a valid **manifest**:
  - a **code module** (app or plugin) → **`module.json`** (slug, version, `requires`, `provides`).
    See [WebTigers/TigerDocs](https://github.com/WebTigers/TigerDocs) as a template.
  - a **theme** → **`theme.json`** (`key`, `type:"theme"`, `assetBase`). Its listing `slug` is
    **`theme-` + the manifest `key`** (e.g. key `grey-mist` → slug `theme-grey-mist`).
- Its root has a **`TIGER.md`** — your human-facing description (this becomes the "View more"
  modal in Tiger). Markdown; keep it current.
- You've cut a **release** (a tag, e.g. `v1.0.0`) — the registry pins to a reviewed release,
  so what's reviewed is exactly what installs.

**`type` vs `category`** are two independent axes: `type` (`theme` \| `app` \| `plugin`) is a
searchable *kind* label — it's **non-behavioral**, purely for filtering the directory (Tiger
detects real capabilities like `theme.json` / a `migrations/` folder at activation). `category`
is the subject-matter *domain* (Commerce, Media, …). A theme can still be a Media-domain theme.

## 2. Add your listing

Create **`data/<GitHubOrg>_<GitHubRepo>.json`** (exact repo org + name, e.g.
`data/Acme_AcmeBilling.json`), following [`schema/registry.v1.json`](schema/registry.v1.json):

```json
{
  "schema": "tiger.registry/v1",
  "slug": "billing",
  "module": "Billing",
  "vendor": "Acme",
  "repository": "https://github.com/Acme/AcmeBilling",
  "website": "https://acme.example",
  "contact": "support@acme.example",
  "version": "1.0.0",
  "ref": "v1.0.0",
  "logo": "https://raw.githubusercontent.com/Acme/AcmeBilling/v1.0.0/assets/logo.png",
  "hero": "https://raw.githubusercontent.com/Acme/AcmeBilling/v1.0.0/assets/hero.png",
  "description": "Stripe billing for your Tiger orgs — plans, subscriptions, invoices.",
  "tiger_md": "https://raw.githubusercontent.com/Acme/AcmeBilling/v1.0.0/TIGER.md",
  "keywords": ["billing", "stripe", "subscriptions"],
  "category": "Commerce",
  "license": "MIT",
  "pricing": { "model": "freemium", "pro_url": "https://acme.example/billing/pro" }
}
```

Leave out the `review` block — the bot writes that.

## 3. Open the PR

Open a pull request with just that one file. The **`tiger-vendor-bot`** runs a few times a
day: it validates the schema, confirms the repo is public with a manifest (`module.json` or a
theme's `theme.json`) + `TIGER.md` at your pinned `ref`, checks the license and that the release
exists, then does an AI review pass. It opens a PR **on your repo** with the findings.

- **Accepted** → your listing merges (`review.status: accepted`) and `index.json` recompiles;
  you're searchable in Tiger within a few hours.
- **Changes requested** → address the findings, push a new release/ref, and it re-runs.

## Updating

Bump `version` + `ref` in your listing (point at the new release) and open a PR. Same review.

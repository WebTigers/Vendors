# tiger-vendor-bot

The automated reviewer for the open Tiger module registry. It gives every submission a fast,
public, reproducible triage — but a human maintainer always makes the final merge call. Code
that runs inside someone's app should never be auto-merged by a machine.

## What runs, in order

When a PR touches `data/*.json`, `.github/workflows/review.yml` runs two passes and posts the
combined result as a single sticky comment on the PR:

1. **Deterministic gate — `scripts/review.php`** (free, no keys). Filename convention,
   required fields, schema constant, slug pattern, **public-repo** check, the pinned ref's
   `module.json` (+ slug match) and `TIGER.md` presence, and a declared license. Most bad
   submissions stop here — the LLM never sees them.

2. **AI pass — `scripts/review-ai.php`** (only if the gate passed *and* a model token is set).
   Fetches the module's executable + descriptive surface at the pinned ref (`module.json`,
   `TIGER.md`, its `.php` — tests/vendor excluded, capped to keep it cheap) and asks a model for
   a **quality + safety** verdict: does the code match the claim, any obfuscation / eval-of-remote
   / exfiltration / backdoor red flags, is it a real module. Returns `accept | flag | reject` +
   itemized findings. Advisory — it informs the maintainer, it doesn't gate the merge.

On accept, a maintainer merges; `compile.yml` rebuilds `data/index.json` and Tiger sees the
listing on its next poll.

## Enabling the AI pass

The deterministic gate needs nothing. The AI pass needs one secret — **provider-agnostic**
(any OpenAI-compatible `chat/completions`), so you choose by setting repo secrets/variables:

| Setting | Where | Default | Notes |
|---|---|---|---|
| `MODEL_TOKEN` | **secret** (required) | — | GitHub Models PAT (`models:read`) **or** an LLM API key |
| `MODEL_ENDPOINT` | variable | `https://models.github.ai/inference/chat/completions` | swap to OpenAI/Anthropic-compatible |
| `MODEL_NAME` | variable | `openai/gpt-4o-mini` | any model your endpoint serves |

**Recommended: GitHub Models** — runs inside the Action, free tier covers a low-volume
registry, no extra service. Create a PAT with `models:read`, add it as the `MODEL_TOKEN` secret,
done. Because the deterministic gate filters junk first, the model runs rarely, so even a paid
API is pennies per review. It is **not** Copilot (that's the IDE assistant, not a batch API).

Run it locally against any listing:

```bash
php scripts/review-ai.php data/<Org>_<Repo>.json --dry-run   # gather + print the prompt, no key
MODEL_TOKEN=… php scripts/review-ai.php data/<Org>_<Repo>.json   # live verdict
```

## Where the findings land

**A sticky comment on the submission PR.** The submitter opens their PR *against this repo*, so it
is *their* PR — they watch it, get the bot's comment inline, and see it merged or closed. The bot
has `pull-requests: write` here via the built-in `GITHUB_TOKEN`, so there is **zero** per-vendor
setup. That comment is the whole feedback loop.

We deliberately do **not** open a findings PR back on the vendor's own repo: it would need a GitHub
App the vendor installs, and it's redundant — they already see (and own) their submission PR here.

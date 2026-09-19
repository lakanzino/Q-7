# Memory pointer — the combined skill (for future sessions)

**This is the agent-side copy of "where the skill lives and how to use it."** The repo files are the source of truth.

## Read first, before any task in this repo

1. `skills/wp-fa-translation-seo/SKILL.md` — the combined skill: Persian (fa, RTL) WordPress translation +
   SEO + modern block-template rules. Its `references/` folder holds the four deep-dive docs and
   `scripts/fa_precheck.py` is the deterministic gate.
2. `AGENTS.md` (repo root) — Rule zero binds step 1 for every session; it also lists allowed/forbidden
   paths, the owner's standing instructions, and the known environment traps.
3. `# پرامپت نویسنده اختصاصی Qpedi.txt` — the site's editorial standard; it outranks the generic SEO rules
   in the skill whenever they disagree.

## The gate

```bash
python3 skills/wp-fa-translation-seo/scripts/fa_precheck.py \
  --target <draft.html> [--source <original.md>] [--json]
# 0 = clean · 1 = warnings (must be justified in the report) · 2 = blocked, do not ship
```

Catches: untranslated Latin prose, raw `&`, `<style>`/`<script>`/`<?php`, Arabic `ي ك`, missing ZWNJ,
missing house sections/«پاسخ کوتاه», <3 internal links, <3 verifiable sources, missing `alt`,
nofollow-less third-party links, over-long paragraphs, machine rhythm, banned clichés,
metaphor without the analogy-limit note, and paragraph-level untranslated pairing against `--source`.

## What the skill is merged from

| Upstream (forked by `lakanzino`) | Taken |
|---|---|
| `WordPress/agent-skills` | SKILL.md section contract, "Done when" per step, router/triage habit, block-theme + patterns + block-dev + interactivity rules |
| `UiPath/skills` | front-matter with `when_to_use`, explicit "Do NOT use for" routing, `references/` instead of one big file |
| `sickn33/agentic-awesome-skills` | front-matter schema, audit-first gated orchestrator, four internal-link types (cluster→pillar mandatory), CTR meta rules, schema-must-match-visible-content, seven hreflang checks, evidence-or-`Not assessed`, i18n/RTL awareness |
| `conholdate/blog-translation-agent` | Scan→Translate→Quality→Retranslate loop, `appears_translated()` 20 % heuristic, `should_skip_validation()`, `SCORE/DECISION/REASON/UNTRANSLATED` contract, `fa` as an RTL first-class locale |
| Qpedia prompt file | fixed H2 sequence, tone, hard-fail list, author QC report |

The catalogue had 6,730 SKILL.md files and **none** covered Persian translation quality — this skill fills that hole.

## Delivered artifacts already finished (do not redo)

- `fixes/articles/nisq-era.html` + `README-nisq-era.md` + featured image — article for the `/nisq-era/`
  404, passed `fa_precheck.py` with `VERDICT: PASS` (0 errors) and the editorial filter (PASS).
- `fixes/theme/custom.css` — header/footer recoloured to the site background; `custom.orig.css` is the rollback.
- `fixes/pages/start-page.html` — rewritten start page; needs the page template switched to Default.
- `tools/featured-images/` — shipped plugin, 36 webp images. Do not rebuild.

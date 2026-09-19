---
name: wp-fa-translation-seo
description: "Translate, write, and pre-publish Persian (fa, RTL) WordPress posts with verified facts, block-safe markup, SEO/schema, and a deterministic quality gate before anything reaches the live site."
category: content
risk: critical
source: self
source_type: self
date_added: "2026-09-19"
author: lakanzino
tags: [wordpress, persian, fa, rtl, translation, seo, gutenberg, qpedia]
tools: [bash, read, edit, write, gh, fetch_page]
compatibility: "WordPress 6.x+ (Gutenberg), child theme with qp-* classes, cPanel File Manager workflow, no wp-cli assumed."
when_to_use: "Any task that writes, translates, edits, reviews, or publishes Persian WordPress content for qpedia.ir (or any fa-RTL WP site); also when auditing an existing translated post for SEO, RTL, tone, or block safety."
---

# Persian WordPress Translation + SEO (combined skill)

> **متن این فایل انگلیسی است تا ابزارها و اسکریپت‌ها دقیق اجرا شوند؛ همهٔ قانون‌های متنی که باید روی مقاله اعمال شوند فارسی‌اند و در `references/` آمده‌اند.**

This skill is a merge of four upstream mechanisms plus the site's own editorial standard. It exists because none of the 2,040 skills in the catalogue covers Persian/RTL translation **quality** — only API wrappers do.

| Source (forked repo) | What this skill takes from it |
|---|---|
| `WordPress/agent-skills` (`lakanzino/agent-skills`) | SKILL.md anatomy (`When to use / Inputs required / Procedure / Verification / Failure modes / Escalation`), router + negative routing, block-theme & pattern rules for "modern templates" |
| `UiPath/skills` (`lakanzino/skills`) | Front-matter style: long `description` with routing hints, explicit `when_to_use`, "Do NOT use this skill for" list, `references/` deep-dives |
| `sickn33/agentic-awesome-skills` (`lakanzino/agentic-awesome-skills`) | Front-matter schema (`category`, `risk`, `source_repo`, `source_type`, `date_added`, `tags`, `tools`), audit-first gated orchestrator, internal-linking rules, meta/CTR framework, JSON-LD generation, `seo-hreflang` validation checks, i18n/RTL notes |
| `conholdate/blog-translation-agent` (`lakanzino/blog-translation-agent`) | The 4-step pipeline (Scan → Translate → Quality-check → Retranslate), the paragraph `appears_translated` heuristic, `RTL_LANGS = {ar, fa, he, ur}`, `fa` as a first-class locale, and the LLM rubric `SCORE / DECISION / REASON / UNTRANSLATED` with "prefer RETRANSLATE when in doubt" |
| `# پرامپت نویسنده اختصاصی Qpedi.txt` (this repo) | The binding editorial contract: section sequence, tone, hard-fail list, author QC report |

## When to use

- Translating an English (or other-language) WordPress post into Persian, or writing a new Persian post from scratch.
- Turning a translated draft into block markup that survives the Gutenberg / Custom HTML paste path.
- Auditing an existing post: orphan Persian sentences left in English, Latin digits, missing ALT, dead internal links, missing schema, RTL layout regressions.
- Deciding **before publishing** whether a piece of content may go live (the gate below).

## Do NOT use this skill for

- Creating custom Gutenberg blocks, `theme.json`, patterns, or template parts as *code deliverables* → use upstream `wp-block-development`, `wp-block-themes`, `wp-patterns`. This skill only dictates which markup shapes are safe to paste into a post body.
- Server performance, caching, plugin security audits → `wp-performance`, `wp-abilities-audit`.
- Bulk machine translation of hundreds of posts with spreadsheet bookkeeping → run the actual `blog-translation-agent` CI pipeline; this skill is the editorial and gate logic, not the runner.

## Inputs required

1. **Target file** — the Persian HTML/Markdown body (no `<h1>` in the body; the title is a WP field).
2. **Source file** (translation tasks only) — the original text, so untranslated paragraphs can be detected.
3. **Publication intent** — draft | paste-ready file | live publish. Default is *paste-ready file, nothing published*.
4. **Destination facts** — post type (`quantum_article` on qpedia.ir), slug, category taxonomy terms, whether the theme auto-renders related posts.
5. **House standard** — `# پرامپت نویسنده اختصاصی Qpedi.txt` must be read **before** drafting, every time.

## Procedure

### 0) Load the contract (never skip)

Read the site's own standard file and treat its section sequence + hard-fail list as law. If it conflicts with generic SEO advice, the site standard wins; note the conflict instead of silently choosing.

### 1) Classify the target

- Post vs page vs archive. On qpedia.ir: articles are `quantum_article` at root-relative URLs (`https://qpedia.ir/<slug>/`).
- Detect template traps: any page assigned `page-homepage.php` never prints its own content — check `template` via REST before promising a layout will appear.
- Confirm whether the destination already renders related posts automatically (avoid duplicating it with hand-built cards).

### 2) Fact and link verification (before writing prose)

- Every number, date, product name, person, DOI, and quoted sentence must have a real, resolvable source or be removed. A source you cannot open is written as *unverified and flagged in the report*, never presented as fact.
- Quotes: reproduce the source wording, translate it, and state that it is a translation (link the original).
- Internal links: batch-verify with `…/wp-json/wp/v2/<post_type>?slug=a,b,c&_fields=id,slug,title&per_page=100`. **Always pass `per_page=100`** — the default of 10 silently truncates and fakes 404s.
- Never invent slugs that "sound right". If the page does not exist, either write it or drop the link.
- Apply the four link types from `references/seo-wordpress.md`; every cluster post must link back up to its pillar.

### 3) Translate / write

Follow `references/persian-style.md` (tone, orthography, numerals, banned clichés, the analogy-limit sentence, the "simple → precise" pattern). Translation-specific rules:

- Target structure, not sentences: a Persian sentence may need to be split or merged; the count of paragraphs must stay aligned with the source so step 6 can pair them.
- Keep code, commands, URLs, file names, and code-block content **verbatim**. Do not translate identifiers.
- First occurrence of a technical term: Persian + Latin in parentheses, once; afterwards use the Persian.
- Never leave a full sentence in the source language. Brand/product names, ISO units, and standard identifiers may remain Latin — that is `KEEP`, everything else is `RETRANSLATE`.
- RTL hygiene: parentheses, quotes (`«…»`), embedded LTR runs (URLs, English titles, DOIs) and ranges (`۵۰ تا ۱۰۰`, never `۵۰-۱۰۰`).

### 4) Make the markup paste-safe

Per `references/wp-blocks-templates.md`:

- No `<style>`, no `<script>`, no raw `&` (use «و»), no `<?php` inside the body file — one bad `&` corrupted a live front page once.
- Only block-native shapes: `<p>`, `<h2 class="wp-block-heading">`, `<h3>`, `<ul class="wp-block-list">`, `<ol>`, `<blockquote>`, `<details><summary>`, plus theme classes already defined in the child stylesheet.
- Whole-file edits are done as **scripted exact-string replacements** over the user's own verbatim copy, each asserted to match exactly once. Never reconstruct a file from fetched chunks.

### 5) SEO layer

From `references/seo-wordpress.md`: one H1 (the WP title field), title ≤ ~60 chars with the primary keyword first, meta description ≤ 155 chars ending in a real reason to click, descriptive Persian slug, short English slug, ≥3 natural-anchor internal links, descriptive Persian ALT for the featured image, `Article`/`FAQPage` JSON-LD, and hreflang only if a second language version actually exists (bidirectional + `x-default`, or omit the tags entirely).

### 6) The gate — run both halves, in this order

**6a. Deterministic** (never trust an eyeball for this):

```bash
python3 skills/wp-fa-translation-seo/scripts/fa_precheck.py \
  --target <file.html> [--source <original.md>] [--json]
```

It reports: untranslated paragraphs (upstream `appears_translated` heuristic, 20 % change threshold), Latin-script leftovers, Arabic-vs-Persian letter/substitution issues, markup-safety violations, block-list hits, structural requirements, link inventory, and numeral style. Exit `0` = clean, `1` = fixable warnings, `2` = blocking failure.

**6b. Editorial** — run the site's «فیلتر نهایی تأیید انتشار» verbatim (scores out of 50/100, three strengths, five required fixes, AI-smell sentence rewrites, `PASS / REVIEW / FAIL`) and apply the *same* decision discipline as the upstream quality validator:

```
SCORE: <integer 0-100 untranslated words>
DECISION: RETRANSLATE | KEEP
REASON: <2-5 words>
UNTRANSLATED: <up to 5 snippets>
```

When in doubt → `RETRANSLATE`. `FAIL` conditions are exactly the hard-fail list in the house standard; `REVIEW` means the remaining items are one-line edits; `PASS` is only allowed when both halves are clean.

### 7) Report and hand-off

- Deliver a file the user can download/copy, and announce the verdict with the score table. Never announce "published" unless publishing was explicitly requested and verified.
- Links given to the user for downloading files are **GitHub raw URLs only** (`https://raw.githubusercontent.com/<owner>/<repo>/<branch>/<path>`), never sandbox/preview hosts.
- State the rollback path for every change (which file replaces which), and name the exact screen where a paste happens (cPanel File Manager path / Theme File Editor / post editor + which block).
- Include the `گزارش کنترل نویسنده` block (word count, internal links, sources, images, main misconception fixed, main scientific limitation, unproven-claim answer, expert-review need, self-score, rewritten sentences).

## Authorization gates

Read-only inspection of the repo/site, drafting files, and running the checker need no confirmation. These require an explicit user request, each time:

- pushing to Git, opening a PR, uploading to cPanel, publishing/scheduling a post, changing a live option/template;
- touching any existing live site object beyond the minimum necessary (recover the user's own version first);
- anything involving credentials: never ask for a password/token/2FA, and never store one.

If a mutation fails on auth, tell the user the GitHub connection needs reconnecting in Arena and stop.

## Verification

- `fa_precheck.py` exits 0 (or only intentional warnings, each justified in the report).
- Every internal link resolves to a real post ID (REST check recorded in the report).
- Every source line has a DOI/arXiv/URL that was opened, or is marked unverified.
- The rendered result was inspected for: callout boxes, list nesting, `details` toggling, RTL punctuation around Latin runs, and the featured image's ALT in markup.
- Numbers in prose match the arithmetic the article claims; if a number is derived, the derivation is stated.

## Failure modes / debugging

- **`SignatureDoesNotMatch`** when re-fetching: fetched pages come back as one-shot proxy URLs; always re-issue the original URL.
- **REST "missing" rows** → `per_page=100` was omitted.
- **Body invisible after paste** → the page uses a non-default template that never calls `the_content()`.
- **Layout destroyed after pasting markup** → a raw `&`/`<style>`/`<?php>` entered a Custom HTML block; restore from the user's backup file, then re-run 6a.
- **Push rejected (non-fast-forward)** on a shallow clone → `git fetch --depth=1 origin <branch>` then rebase/`commit-tree` onto `FETCH_HEAD`.
- **Screenshot shows wrong colors** → Chrome/Android force-dark; ask for a desktop screenshot before "fixing" CSS.
- **Deliverable written into a nested folder** → relative paths in tools resolve against the repo root (`/home/user/Q-7`); pass absolute paths when in doubt.

## Escalation

When routing is ambiguous, ask exactly one question, e.g.: "Is this for the post body, a page, or the theme stylesheet?" or "Should I also create the missing page this article links to, or drop the link?"

## Merged skill index (inline digest — this is the part that actually executes)

The full text of each upstream skill stays upstream; what this skill carries is the distilled rules below.

- **`WordPress/agent-skills` (18 skills)** — adopted the section contract (`When to use / Inputs required / Procedure / Verification / Failure modes / Escalation`), a **"Done when"** clause per step, and the habit of classifying before acting (`wordpress-router`). Its `wp-block-themes`, `wp-patterns`, `wp-block-development` and `wp-interactivity-api` content is restated for our use case in `references/wp-blocks-templates.md`.
- **`UiPath/skills` (41 skills)** — adopted front-matter with explicit `when_to_use` triggers, a mandatory **"Do NOT use this skill for"** list that names the skill to use instead, and deep detail kept in `references/` rather than one bloated file.
- **`agentic-awesome-skills` (2,040 skills / 6,730 SKILL.md)** — adopted the front-matter schema (`category`, `risk`, `source`, `source_repo`, `source_type`, `date_added`, `tags`, `tools`); the audit-first gated orchestrator (`seo-aeo-orchestrator`, incl. "no mutation before authorization" and "never claim indexation/deployment without observable evidence"); the four internal-link types with **"every cluster article links up to its pillar — no exceptions"** (`seo-aeo-internal-linking`); CTR mechanics for title/description (`seo-aeo-meta-description-generator`); "schema must match visible content" (`seo-aeo-schema-generator`); the seven hreflang checks and `fa` code discipline (`seo-hreflang`); the evidence-or-`Not assessed` rule and severity→evidence→fix→verification finding format (`seo-aeo-content-quality-auditor`); hardcoded-string / RTL awareness (`i18n-localization`). **None of them covered Persian** — that gap is exactly what `references/persian-style.md` and `scripts/fa_precheck.py` exist for.
- **`conholdate/blog-translation-agent`** — has **no SKILL.md**; its governance lives in `AGENTS.md` (allowed/forbidden paths). Adopted: the four-step loop **Scan → Translate → Quality check → Retranslate**, paragraph-level alignment, `should_skip_validation()`, the `appears_translated()` 20 % change heuristic, and the `SCORE / DECISION / REASON / UNTRANSLATED` contract with "when in doubt, RETRANSLATE". It lists `fa` among RTL locales, which is why its heuristics port here unchanged. See `references/pipeline-gates.md`.
- **`# پرامپت نویسنده اختصاصی Qpedi.txt`** — the site's own standard outranks every generic SEO habit above: fixed H2 sequence, tone contract, analogy-limit sentence, hard-fail list, and «گزارش کنترل نویسنده».

Storage and retrieval: this folder is the source of truth in the repo; the repo's `AGENTS.md` (Rule zero) makes every future session read it before touching anything, and a pointer memory (`qpedia-fa-skill`) keeps it reachable from the agent side. For global use in an editor, copy the folder to `~/.claude/skills/`.

## Files in this skill

```
skills/wp-fa-translation-seo/
├── SKILL.md                      this file
├── references/persian-style.md       tone, orthography, numerals, clichés (Persian, binding)
├── references/seo-wordpress.md       title/meta/links/schema/hreflang for fa-RTL
├── references/wp-blocks-templates.md block markup, paste paths, theme/class contracts
├── references/pipeline-gates.md      the 4-step translation loop + decision rubric
└── scripts/fa_precheck.py            deterministic gate (stdlib only, python3)
```

Install / activate: keep this folder in the repo, then link it into the agent's project-scope skills
(`.claude/skills/wp-fa-translation-seo → ../../skills/wp-fa-translation-seo`) or copy it to
`~/.claude/skills/` for global use. `AGENTS.md` at the repo root already orders every agent to read
this SKILL.md before any task.

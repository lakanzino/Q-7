# Translation pipeline gates (adapted from `conholdate/blog-translation-agent`)

The upstream repo runs a four-step CI pipeline for a multi-locale blog:
**1 Scan → 2 Translate → 3 Quality check → 4 Retranslate**, with a Google-Sheet ledger and an LLM
validator. It treats `fa` (Persian) as a first-class locale and marks `ar, fa, he, ur` as RTL, which is
why its heuristics port cleanly to this site. We keep the *decision discipline*, drop the *runner*.

## Step 1 — Scan (what is missing or stale)

- Enumerate source posts and existing translations; classify each as `missing | stale | present`.
- `stale` = source `dateModified` newer than the translation's, or the slug changed.
- Never assume a translation exists because a file exists: an empty front-matter stub is `missing`.

## Step 2 — Translate

- Unit = paragraph, not sentence. Paragraph alignment must be preserved so Step 3 can pair them 1:1
  (upstream zips `original.split("\n\n")` against `translated.split("\n\n")`).
- `should_skip_validation` — never diff-measure: fenced code blocks, `---` front-matter dividers,
  shortcode-like `{{< … >}}` blocks (in our case also: `<figure>` captions copied verbatim, math,
  and DOI/URL lines).
- Do not translate: code, commands, file names, URLs, identifiers, product/API names, schema keys.
- Do translate: prose, headings, alt text, captions, FAQ questions, table headers — everything the
  reader reads.
- Numbers: convert to Persian digits in prose (keep Latin digits inside DOIs, URLs, version strings,
  and file sizes, where conversion is a bug).

## Step 3 — Quality check (two phases, deterministic first)

**Phase A — heuristic Error%** per file. For each aligned paragraph pair, `appears_translated()`:

1. strip markdown syntax, turn `[text](url)` into `text`, drop fenced + inline code, collapse whitespace, lowercase;
2. if cleaned original == cleaned translation → **not translated**;
3. if the original has ≤ 2 words → pass as long as anything exists;
4. otherwise `changed% = |orig_words − trans_words| / |orig_words| × 100`, and `changed% ≥ 20.0` counts as translated.

`Error% = untranslated / checked × 100`. Files with 100 % untranslated get shouted about in the log.
Additional Persian-specific Phase A signals (our extension, implemented in `scripts/fa_precheck.py`):
Latin runs inside Persian paragraphs, Arabic-script letters (`ي ك`) instead of Persian (`ی ک`),
Latin digits in prose, raw `&`/`<style>`, and RTL punctuation hazards.

**Phase B — LLM validation** on a random sample of paragraphs, with the exact contract:

```
SCORE: <integer 0-100>            # % of translatable words still in English
DECISION: RETRANSLATE | KEEP
REASON: <2-5 words>
UNTRANSLATED:
<up to 5 snippets, first ~100 chars each, one per line>
```

Decision rule, verbatim in spirit from upstream: choose `RETRANSLATE` if **any** full sentence or
meaningful phrase is still untranslated, if quality is poor, or if there is **any doubt** — when in
doubt prefer `RETRANSLATE` over `KEEP`. `KEEP` only when the untranslated residue is minor items
(brand/product names, abbreviations, stray technical terms) around properly translated prose. A low
SCORE never excuses a whole untranslated sentence, and a high SCORE never overrides a real problem.

## Step 4 — Retranslate only what was flagged

- Retranslate the flagged file, not the whole locale. Re-run Phase A + B on it before hand-off.
- Every gate result is recorded; the report is the artefact, not a chat message saying "done".

## Our two extra gates before anything reaches WordPress

1. `scripts/fa_precheck.py` must exit 0 (warnings allowed only when justified in the report).
2. The site's «فیلتر نهایی تأیید انتشار» must return `PASS`. Its hard-fail list is the only thing that
   can turn a mechanically clean file into a blocked one (fake source, <3 sources, <3 internal links,
   missing ALT, machine/advertising tone, definitive claims about unproven topics, excessive colloquial
   speech, no analogy-limit note).

`PASS` requires both. Anything else is reported as `REVIEW` (one-line fixes left) or `FAIL` (do not
publish), with the unresolved list in the hand-off.

## Publishing gate (site-specific, learned the hard way)

- Body file is paste-ready; nothing is published without an explicit request.
- Slug must equal the slug other pages already link to (`per_page=100` REST check first).
- Page template must be Default if the body is supposed to render.
- Hand-off = GitHub raw link + exact screen path + file size/line count + rollback file.

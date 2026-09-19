# SEO layer for a Persian (fa, RTL) WordPress post

Evidence-first: never claim a score, a keyword volume, an indexation state, or a schema validation
that was not observed. Mark any check that could not run as **Not assessed** in the report.

## Findings format (use for every SEO remark you hand back)

`Severity (Blocker | High | Medium | Low) · Evidence (file, URL, selector, command output) · Impact ·
Smallest fix · Verification step · Dependency (what the user must do).`

## 1) Title, slug, description

| Field | Rule |
|---|---|
| SEO title | ≤ ~60 chars, primary keyword first, no clickbait; a question mark is allowed when the article actually answers it |
| Slug | short English lowercase, hyphenated, stable; on qpedia.ir the article URL is `https://qpedia.ir/<slug>/` — the slug must equal the one other pages already link to, otherwise you create a 404 |
| Meta description | ≤ 155 chars, benefit-first, specific number if the article has one, ends with a real reason to click; never repeats the title; Persian sentence, no «کلیک کنید» |
| Focus keyword | one primary + 3–5 secondary in the *writer brief*, not stuffed into headings |

Never invent: search volume, rankings, impressions, traffic, backlinks, or competitor claims.

## 2) Structure and answer extraction (AEO)

- Exactly one H1 (the WP title field). The body starts at H2 — no `<h1>` inside the pasted body.
- First 100 words must contain a direct 2–4 sentence answer (the «پاسخ کوتاه» box). Answer-engine and featured-snippet extraction both take it from there.
- Lists for steps/comparisons, `<details>` FAQ with **visible** questions, one definition sentence per concept.
- Do not add `FAQPage`/`Review` schema for content that is not literally visible on the page.

## 3) Internal linking (four link types)

| Type | Direction | Purpose |
|---|---|---|
| Pillar → Cluster | hub → article | distributes authority |
| Cluster → Pillar | article → hub | consolidates authority — **mandatory, at least one per article** |
| Cluster → Cluster | article → sibling article | semantic depth |
| Contextual boost | any → focus page | concentrates equity on the page being promoted |

Rules:
- Descriptive Persian anchors that read naturally inside the sentence; never bare URLs, never «اینجا را کلیک کنید».
- Verify every target exists before shipping: `GET /wp-json/wp/v2/<post_type>?slug=a,b,c&_fields=id,slug,title&per_page=100`. Omitting `per_page` returns 10 rows and fakes 404s.
- Orphan check: a newly published post must be linked *from* at least one live page (start page, hub, or a sibling article) — otherwise it will never be crawled.
- Cannibalisation: if a live post already targets the same phrase, link to it instead of duplicating the topic.
- External links: `target="_blank" rel="noopener nofollow"`; the site's own bibliography style puts the DOI as the anchor text.

## 4) Media

- Featured image: ≥ 1200×675 (16:9), descriptive **Persian** ALT (what the image shows, not the keyword), Title + Caption + Description filled, English hyphenated file name, WebP preferred.
- No fake photographs of real people/labs/equipment. AI images must contain **no** formulas, scientific symbols, labels or numbers; a human scientific review is required before publishing.
- Add width/height or rely on the theme's responsive rule — never a `<style>` block in the post body.

## 5) Structured data

Generate only what the page supports, then validate against the visible text:

```json
{"@context":"https://schema.org","@type":"Article",
 "headline":"…","description":"…","inLanguage":"fa-IR","datePublished":"YYYY-MM-DD",
 "dateModified":"YYYY-MM-DD","author":{"@type":"Person","name":"…"},
 "publisher":{"@type":"Organization","name":"Qpedia","logo":{"@type":"ImageObject","url":"…"}},
 "mainEntityOfPage":"https://qpedia.ir/<slug>/","image":["https://qpedia.ir/wp-content/uploads/…"]}
```

`FAQPage` for the visible `<details>` block; `BreadcrumbList` only if the theme shows breadcrumbs.
Where the SEO plugin already emits these, **do not duplicate** — check the rendered head first, and pass JSON-LD through the plugin's field, never pasted into a Custom HTML block.

## 6) hreflang — only for genuine multilingual sites

Skip the whole section if no second language version exists (a `fa` page alone needs no hreflang). If both exist:

- Every page self-references with a tag pointing at itself; missing self-reference makes Google ignore the whole set.
- Return tags are bidirectional (A→B and B→A), full mesh across all variants.
- `x-default` exactly once, pointing to the language selector or fallback page.
- Codes: ISO 639-1 (`fa`, not `per`/`farsi`), optional ISO 3166-1 region uppercase (`fa-IR`); Persian is `fa`.
- Tags live only on canonical URLs, same protocol everywhere, URLs byte-identical to the canonical (trailing slash included).
- Sitemap-based hreflang is the safer implementation for cross-domain setups.

## 7) Technical checks specific to this stack

- LiteSpeed cache: purge the single affected URL after publishing; a full purge is not needed for CSS-only edits via the append block.
- Force-dark on Android/Chrome rewrites dark bands; never conclude from a phone screenshot that colours are broken.
- Template traps: a page whose template is `page-homepage.php` ignores its own content (that template never calls `the_content()`); set it to Default first.
- Post type is `quantum_article`; the archive uses `quantum_category` terms (parent + child — assign the child).

## 8) Post-publication verification (report these, don't assume)

1. `curl`/fetch the live URL → confirm H1, meta description, one canonical, FAQ `<details>` present, ALT rendered.
2. REST `?slug=<slug>` returns the new ID with the intended `link`.
3. The page that should link to it now does (grep the source file of that page).
4. Nothing else on the page changed: diff the file you replaced against the backup copy.

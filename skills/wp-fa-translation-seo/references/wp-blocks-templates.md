# Modern WordPress template & markup contract for Persian content

Two different jobs, two different rulesets. Decide which one you are doing **before** writing anything:

- **A) Content work** — you produce a post body (or a page body) that a human pastes into the editor.
- **B) Theme work** — you touch the child theme: templates, `custom.css`, patterns, `theme.json`.

Routing (from the upstream `wordpress-router` pattern): content work stays in the post body and never
edits the theme; theme work never rewrites the user's existing files beyond the minimum. If the request
mixes both, split the deliverables into two files and two instructions.

## A) The only markup shapes that are safe in a post body

```html
<p>…</p>
<h2 class="wp-block-heading">…</h2>
<h3>…</h3>
<ul class="wp-block-list"><li>…</li></ul>
<ol class="wp-block-list"><li>…</li></ol>
<blockquote><p>…</p></blockquote>
<details><summary><strong>سؤال</strong></summary><p>پاسخ</p></details>
<aside class="qp-callout"><p class="qp-callout__title">پاسخ کوتاه</p><p>…</p></aside>
<div class="qp-intuition-box">…</div>          <!-- شهود کوانتومی -->
<div class="qp-narrative-box"><p>…</p></div>    <!-- روایت شخصیِ بزرگان علم (نه جمع‌بندی!) -->
```

Hard rules:

1. **No** `<style>`, `<script>`, `<?php`, and **no raw `&`** (use «و» or `&amp;`). A raw `&` inside a
   Custom HTML block has corrupted a live front page before.
2. One paste = one Custom HTML block. Splitting the body across several blocks re-flows the nesting.
3. No `<h1>` in the body (the title field is the H1) and no `<h4>` ladders.
4. Classes used must already exist in the active stylesheet (`custom.css` in the child theme). Never
   invent a class and never add CSS to make a content block look right.
5. Persian punctuation and quotes live inside the tags; don't leave a `</p>` inside a `<summary>`.
6. Verify before hand-off: tag balance, no orphan `<li>`, no double spaces from leftover tags, and each
   `<a>` has a target that returns 200.
7. Deliverables carry **markup only** unless styling was explicitly requested — and then as a complete
   replacement file, never an unlabelled snippet.

## B) Theme work (block-theme era, WordPress 6.x / 7.0+)

Modern means block themes, not HTML tables. Concretely:

- **`theme.json`** holds the design system: `settings` (colour/font-size/spacing presets, layout) and
  `styles`. A content author should reference presets, not hardcode hex values. If you must add CSS,
  add it to the child theme's `custom.css` and prefer **no `!important`** (a stylesheet the user must
  replace wholesale is already risky enough).
- **Templates** `templates/*.html`, **template parts** `parts/*.html` (parts must not be nested in
  subdirectories). Style hierarchy to remember when "my change did nothing":
  core defaults → `theme.json` → child theme → user customizations. A user's saved customization
  silently overrides theme defaults.
- **Patterns** `patterns/*.php` with a `Title:`/`Inserter:`/`Categories:` header; block markup written
  with `<!-- wp:group {"style":…} -->` comments and preset slugs; placeholder text must be real copy,
  never Lorem ipsum. Escaping and i18n are part of pattern review: `esc_html__()` for strings,
  `esc_url()` for URLs, and `Translate`-ready text.
- **Block development** (if a new block is truly required): `block.json` metadata +
  `register_block_type_from_metadata()`, static save vs dynamic `render.php`, and a `deprecated`
  callback whenever saved markup changes — skipping deprecations is what produces the "This block
  contains unexpected or invalid content" error on existing posts.
- **Interactivity API** for frontend state (`data-wp-*`, `@wordpress/interactivity` store) instead of
  inline `<script>` in content. Inline JS in a post body is not an option here at all.

When editing theme files for this site specifically:

- Whole-file edits are done by **scripted exact-string replacements** over the user's own verbatim copy,
  every pattern asserted to match exactly once (`count == 1`). Never reconstruct a file from fetched
  chunks — markdown escaping (`\_`, `\|`) corrupts silently.
- Validate CSS mechanically after the edit (rule/declaration counts + a parser pass) and keep the
  original file in the repo as the rollback.
- State in the hand-off: exact path (`wp-content/themes/<child>/…` or File Manager path), file size and
  line count so the user can confirm the upload landed, and the rollback = re-upload the original.
- Child templates that do not call `the_content()` (e.g. `page-homepage.php`) explain "my page content
  disappeared" — check the assigned template before touching content.

## "Done when" checklist (per step, upstream style)

- Content: markup validator clean, links verified, precheck script exit 0.
- Theme: file uploaded at the named path, rendered page inspected, rollback file present in the repo.
- Both: report names what changed, what was deliberately left alone, and what the user must click.

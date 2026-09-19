# AGENTS.md — qpedia.ir working repo

Governance file for every agent working in this repository. Modelled on the `AGENTS.md` pattern of
`conholdate/blog-translation-agent` (allowed/forbidden paths + explicit gates), adapted to this repo.

## Rule zero — binding

**Before any task in this repo, read and obey `skills/wp-fa-translation-seo/SKILL.md`.**
For any editorial/content task, also read `# پرامپت نویسنده اختصاصی Qpedi.txt` (the site's own writer
prompt + «فیلتر نهایی تأیید انتشار») — it outranks generic SEO advice. Conflicts are reported, not
silently resolved.

Every deliverable that is a Persian post/page body must pass both halves of the skill's gate 6:

```bash
python3 skills/wp-fa-translation-seo/scripts/fa_precheck.py --target <file.html> [--source <orig.md>]
# exit 0 = clean · 1 = warnings (justify them in the report) · 2 = blocked, do not ship
```

then the site's editorial filter. `PASS` requires both. Announce the verdict; never announce
"published" unless publishing was requested and verified.

## What lives where

| Path | Contents |
|---|---|
| `skills/wp-fa-translation-seo/` | the combined skill: SKILL.md, `references/` (style, SEO, blocks/themes, pipeline gates), `scripts/fa_precheck.py` |
| `.claude/skills/wp-fa-translation-seo` | project-scope symlink → the skill above (auto-discovery) |
| `fixes/articles/` | article drafts (HTML body + publish package README + featured image) |
| `fixes/theme/` | child-theme files: `custom.css` + `custom.orig.css` (rollback copy) + templates |
| `fixes/pages/` | page bodies (e.g. `start-page.html`) + their READMEs |
| `tools/featured-images/` | shipped plugin + generated images — do not rebuild |
| `WordPress.2026-09-14 all.xml` | full WXR export; parse `<content:encoded><![CDATA[…]]>` for house markup study |

## Allowed without asking

Reading anything; inspecting the live site read-only; drafting files under `fixes/` and `skills/`;
running `fa_precheck.py`; verifying slugs via `https://qpedia.ir/wp-json/wp/v2/...`.

## Require an explicit request first

- publishing / scheduling / editing a live post or page, or any cPanel write;
- `git push`, opening PRs, deleting or replacing a live file;
- touching an existing site object beyond the minimum necessary (recover the user's own version first);
- anything credential-shaped: never ask for a password, token, or 2FA code, and never store one.
  If `git`/`gh` fails on auth, tell the user to reconnect GitHub in Arena and stop.

## Standing instructions from the site owner (do not drop)

1. Download links are **GitHub raw URLs only** — never sandbox/preview hosts. When asked for a file, the
   answer is the link itself.
2. Workflow is phone + cPanel File Manager (right-click → Edit / Replace). No wp-cli/SSH as the primary path.
3. Always name the exact screen for a change (File Manager path / Theme File Editor / post editor +
   which block) and warn when a block is *not* the right place. Give the rollback in the same message.
4. Content deliverables are markup-only: no `<style>`, no `<script>`, no raw `&`, no `<?php` in a body file.
5. Whole-file edits = scripted exact-string replacements over the user's own verbatim copy, each asserted
   `count == 1`; never reconstruct a file from fetched page chunks.
6. Lists of items go in the chat reply, not into new files, unless asked.
7. No invented facts, quotes, slugs, or "I tested this" claims. Unverified source → say so and flag it.

## Known traps in this environment

- REST slug checks need `per_page=100` (default 10 truncates → fake 404s) and `_fields=id,slug,title`.
- `fetch_page` returns one-shot proxy URLs: re-issue the *original* URL, never the returned one.
- `page-homepage.php` template never calls `the_content()` → a page assigned to it shows no body.
- Chrome/Android force-dark repaints dark bands; don't "fix" CSS from a phone screenshot.
- Shallow clone: rebase/`commit-tree` onto `FETCH_HEAD` so pushes stay fast-forward.
- Tool relative paths resolve against the repo root (`/home/user/Q-7`) — pass absolute paths when unsure.

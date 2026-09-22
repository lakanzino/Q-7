=== Qpedia SEO Pro ===
Contributors: qpedia
Donate link: https://qpedia.ir
Tags: seo, schema, sitemap, persian, farsi, rtl, rank-math, technical-seo, content-audit
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Dedicated technical SEO plugin for Quantum Pedia Farsi (qpedia.ir) — افزونه سئوی اختصاصی کوانتوم پدیا فارسی.

== Description ==

Qpedia SEO Pro is a purpose-built technical SEO plugin for **https://qpedia.ir** (کوانتوم پدیا فارسی). It understands the site’s custom post types (`quantum_article`, `quantum_scientist`), the `quantum_category` taxonomy, Persian word counting, Rank Math meta keys, and the production URL map:

* Articles: `https://qpedia.ir/{slug}/`
* Scientists: `https://qpedia.ir/scientists/{slug}/`
* Topics: `https://qpedia.ir/topic/{slug}/`

افزونه سئوی تکنیکال اختصاصی برای کوانتوم پدیا فارسی. این افزونه انواع پست سفارشی، تاکسونومی موضوعی، متاهای Rank Math و ساختار نشانی‌های سایت را می‌شناسد و بدون بازنویسی متاهای Rank Math در کنار آن کار می‌کند.

= Modules / ماژول‌ها =

1. **Core** — singleton bootstrap, Persian word count (space + ZWNJ), grading A–F, settings, scan cache.
2. **Collector** — batched DB collection of articles, scientists, terms, pages, images, sitemaps and robots.txt.
3. **Analyzer** — 0–100 scoring with exact weights for articles, scientists, terms, pages and images.
4. **Schema** — JSON-LD for Article / Person / CollectionPage / WebSite / Organization / BreadcrumbList.
5. **Sitemap** — audit of sitemap_index.xml, wp-sitemap.xml and quantum_category-sitemap.xml.
6. **Meta Tags** — title, description, canonical, Open Graph, Twitter Card and Rank Math / Yoast / Qpedia conflict detection.
7. **Internal Links** — internal link graph, orphan pages, anchor-text quality, link opportunities.
8. **Content Audit** — thin content, duplicate titles/descriptions, keyword cannibalization, Persian readability, drafts.
9. **Export** — ZIP / CSV / JSON / standalone RTL HTML reports.
10. **Scientist SEO** — completeness checklist and Person schema readiness for scientist profiles.
11. **Taxonomy SEO** — quantum_category + post_tag audit, Persian slugs, empty terms, hierarchy.
12. **Image Audit** — alt text, dimensions, attachment, oversized files (>200KB), missing thumbnails.
13. **Performance** — response times, cache headers, Gzip/Brotli.
14. **Robots** — robots.txt parse, sitemap declaration, crawlability.
15. **Breadcrumb** — HTML + JSON-LD trails for articles, scientists, topics and pages.
16. **Cron** — weekly full scan (history of 12) and daily quick check (new publishes + home 404).
17. **Admin / Dashboard / Metabox** — RTL admin UI, reports, per-post SEO analysis.

= Compatibility =

* WordPress 6.0+ (tested up to 6.8)
* PHP 7.4+
* Rank Math (reads its meta, does not overwrite it)
* GeneratePress + quantum-pedia-child
* RTL / fa-IR

= Privacy =

The plugin stores scan results in WordPress options and transients only. No data is sent to third parties.

== Installation ==

1. Upload the `qpedia-seo-pro` folder to `/wp-content/plugins/` or install the ZIP via Plugins → Add New → Upload Plugin.
2. Activate **Qpedia SEO Pro**.
3. Open the **Qpedia SEO** menu and run the initial site scan.
4. Review scores, fix issues, and optionally export a full ZIP report.

نصب: پوشه افزونه را در `wp-content/plugins` قرار دهید یا فایل ZIP را بارگذاری کنید، افزونه را فعال کنید، سپس از منوی «Qpedia SEO» اسکن اولیه را اجرا کنید.

== Frequently Asked Questions ==

= Does it replace Rank Math? =

No. It reads Rank Math titles, descriptions, focus keywords and FAQ schema. If Rank Math is active, Qpedia can skip injecting duplicate schema.

= Is it only for qpedia.ir? =

Yes. Permalinks, post types, taxonomies and scoring rules are hard-wired for Quantum Pedia Farsi.

= How is the article score calculated? =

Title 15, description 15, keyword 10, content length 10, headings 10, thumbnail 10, internal links 10, slug 5, FAQ 5, category 5, freshness 5.

= How is the site score calculated? =

Weighted average: articles 50%, scientists 20%, terms 15%, pages 5%, images 10%. Grades: A 90+, B 70+, C 50+, D 30+, F otherwise.

== Changelog ==

= 1.0.0 =
* Initial production release for qpedia.ir.
* Collector, analyzer, schema, sitemap, meta tags, internal links, content audit, export, scientist SEO, taxonomy SEO, image audit, performance, robots, breadcrumb, cron, admin dashboard and metabox.

== Upgrade Notice ==

= 1.0.0 =
First release. Activate, run a full scan, then review the dashboard.

== Screenshots ==

1. Dashboard with site grade and module summaries.
2. Article SEO table with scores and issues.
3. Scientist completeness report.
4. Taxonomy and image audits.

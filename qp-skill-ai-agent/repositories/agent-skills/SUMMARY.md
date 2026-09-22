# چکیده اصلی مخزن agent-skills

- **مخزن:** `lakanzino/agent-skills`
- **موضوع اصلی:** دانش تخصصی WordPress برای دستیارهای کدنویسی
- **شاخه بررسی‌شده:** `trunk`
- **تعداد مهارت‌ها:** ۱۸

این مجموعه برای تشخیص نوع پروژه وردپرس و هدایت عامل به جریان درست طراحی شده است. حوزه‌ها شامل توسعه بلوک، Block Theme، Interactivity API، الگوها، کارایی، PHPStan، Playground، توسعه افزونه، دستورالعمل مخزن افزونه‌ها، REST API، WP-CLI و عملیات، WordPress Design System و Abilities API است.

## فهرست مهارت‌ها

| مهارت | توضیح اصلی | مسیر |
|---|---|---|
| blueprint | Use when the deliverable is WordPress Playground Blueprint JSON or a Blueprint bundle, including creating, editing, reviewing, validating schema keys, choosing steps/resources, and debugging Blueprint files. For only running or sharing a Playground environment, use wp-playground. | skills/blueprint/SKILL.md |
| wordpress-router | Use when the user asks about WordPress codebases (plugins, themes, block themes, Gutenberg blocks, WP core checkouts) and you need to quickly classify the repo and route to the correct workflow/skill (blocks, theme.json, REST API, WP-CLI, performance, security, testing, release packaging). | skills/wordpress-router/SKILL.md |
| wp-abilities-api | Use when working with the WordPress Abilities API (wp_register_ability, wp_register_ability_category, /wp-json/wp-abilities/v1/*, @wordpress/abilities) including defining abilities, categories, meta, REST exposure, and permissions checks for clients. | skills/wp-abilities-api/SKILL.md |
| wp-abilities-audit | Audit a WordPress plugin's REST surface and produce a standardized audit document proposing Abilities API registrations. Produces a markdown doc with a YAML schema and prose sections that humans and agents can both consume when planning a registration rollout. Works on any WP plugin. | skills/wp-abilities-audit/SKILL.md |
| wp-abilities-verify | Verify a WordPress plugin's Abilities API registrations: enumerate abilities, check that callback behavior matches each annotation's claim (the adversarial readonly-but-writes detection), validate permissions and schemas, and validate audit documents produced by wp-abilities-audit. | skills/wp-abilities-verify/SKILL.md |
| wp-block-development | Use when developing WordPress (Gutenberg) blocks: block.json metadata, register_block_type(_from_metadata), attributes/serialization, supports, dynamic rendering (render.php/render_callback), deprecations/migrations, viewScript vs viewScriptModule, and @wordpress/scripts/@wordpress/create-block build and test workflows. | skills/wp-block-development/SKILL.md |
| wp-block-themes | Use when developing WordPress block themes: theme.json (global settings/styles), templates and template parts, patterns, style variations, and Site Editor troubleshooting (style hierarchy, overrides, caching). | skills/wp-block-themes/SKILL.md |
| wp-interactivity-api | Use when building or debugging WordPress Interactivity API features (data-wp-* directives, @wordpress/interactivity store/state/actions, block viewScriptModule integration, wp_interactivity_*()) including performance, hydration, and directive behavior. | skills/wp-interactivity-api/SKILL.md |
| wp-patterns | Pattern: create or update WordPress block patterns (starter pages, templates, template parts, Query Loop layouts), review pattern registration, block markup, categories, accessibility, or i18n/escaping, or improve pattern design quality. Route custom blocks to wp-block-development; route frontend interactivity to wp-interactivity-api. | skills/wp-patterns/SKILL.md |
| wp-performance | Use when investigating or improving WordPress performance (backend-only agent): profiling and measurement (WP-CLI profile/doctor, Server-Timing, Query Monitor via REST headers), database/query optimization, autoloaded options, object caching, cron, HTTP API calls, and safe verification. | skills/wp-performance/SKILL.md |
| wp-phpstan | Use when configuring, running, or fixing PHPStan static analysis in WordPress projects (plugins/themes/sites): phpstan.neon setup, baselines, WordPress-specific typing, and handling third-party plugin classes. | skills/wp-phpstan/SKILL.md |
| wp-playground | Use as the WordPress Playground routing wrapper for ambiguous Playground work, local CLI runs with @wp-playground/cli, playground.wordpress.net share links, browser previews, snapshots, mounts, version switching, and Xdebug. For Blueprint JSON authoring or review, use the blueprint skill directly. | skills/wp-playground/SKILL.md |
| wp-plugin-development | Use when developing WordPress plugins: architecture and hooks, activation/deactivation/uninstall, admin UI and Settings API, data storage, cron/tasks, security (nonces/capabilities/sanitization/escaping), and release packaging. | skills/wp-plugin-development/SKILL.md |
| wp-plugin-directory-guidelines | Use when reviewing WordPress plugins for GPL compliance, checking license headers or compatibility, evaluating upsell/freemium/trialware patterns, validating plugin naming or trademark rules, checking plugin slugs, understanding why a plugin was rejected from WordPress.org, or answering any question about the 18 WordPress.org Plugin Directory guidelines — even if the user doesn't mention 'guidelines' explicitly. | skills/wp-plugin-directory-guidelines/SKILL.md |
| wp-project-triage | Use when you need a deterministic inspection of a WordPress repository (plugin/theme/block theme/WP core/Gutenberg/full site) including tooling/tests/version hints, and a structured JSON report to guide workflows and guardrails. | skills/wp-project-triage/SKILL.md |
| wp-rest-api | Use when building, extending, or debugging WordPress REST API endpoints/routes: register_rest_route, WP_REST_Controller/controller classes, schema/argument validation, permission_callback/authentication, response shaping, register_rest_field/register_meta, or exposing CPTs/taxonomies via show_in_rest. | skills/wp-rest-api/SKILL.md |
| wp-wpcli-and-ops | Use when working with WP-CLI (wp) for WordPress operations: safe search-replace, db export/import, plugin/theme/user/content management, cron, cache flushing, multisite, and scripting/automation with wp-cli.yml. | skills/wp-wpcli-and-ops/SKILL.md |
| wpds | Use when building UIs leveraging the WordPress Design System (WPDS) and its components, tokens, patterns, etc. | skills/wpds/SKILL.md |

فهرست ماشین‌خوان کامل در [`skills-inventory.csv`](./skills-inventory.csv) قرار دارد.

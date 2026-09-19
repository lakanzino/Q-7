# Q-7
New Information of website qpedia.ir

## Skills & agent rules

- `AGENTS.md` — governance: read the skill before any task; allowed/forbidden paths; standing instructions from the site owner.
- `skills/wp-fa-translation-seo/` — combined skill for Persian (fa, RTL) WordPress translation + SEO + modern block templates, merged from `WordPress/agent-skills`, `UiPath/skills`, `agentic-awesome-skills`, `conholdate/blog-translation-agent`, and this site's own writer prompt.
- Gate: `python3 skills/wp-fa-translation-seo/scripts/fa_precheck.py --target <file.html> [--source <orig.md>]`
- Editorial standard: `# پرامپت نویسنده اختصاصی Qpedi.txt`


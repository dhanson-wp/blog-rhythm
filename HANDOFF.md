# Blog Rhythm: handoff

Written 2026-10-27 from an Opa session. Read this first, then continue the brainstorm. **Nothing is scaffolded yet.** This folder holds only this file, two mockups, and reference code copied from Opa.

## Process state (important)

We are mid-way through the `superpowers:brainstorming` skill. Its hard gate is still active: no plugin scaffolding, `npm install`, or product code until Derek approves a written design. Next steps in order:

1. Invoke `superpowers:brainstorming` and carry on the conversation (one question per message, plain language).
2. Show new mockups (visual companion is already accepted; see "Mockups" below).
3. Write the design doc to `docs/superpowers/specs/YYYY-MM-DD-blog-rhythm-design.md`, run the builder check, get approval.
4. Then `superpowers:writing-plans`, then build.

Derek's voice rules apply to any prose written as him (no em dashes, contractions, sentence case). Source: `~/Projects/no-code-required/VOICE.md`. Code follows WordPress conventions instead.

## What this is

A standalone WordPress plugin, **Blog Rhythm** (slug `blog-rhythm`), extracted from the "Rhythm Section" dashboard card in the dormant Opa project. It helps bloggers, writers, and site owners set their own writing goals and see their own rhythm.

Origin: a conversation in the #blogging channel. People want to be more consistent without pressure to write perfectly or post daily.

### Principles (Derek's words, paraphrased)
- **Personal growth, not performance.** No traffic, stats, likes, or leaderboards.
- **The writer decides.** Some log in daily, others weekly or monthly. It must be pliable.
- **Drafting, outlining, and ideas count as showing up**, not only publishing.
- **Quiet stretches are never failure.** No guilt mechanics.
- **Approachable to all levels**: visual, interactive, friendly, inviting, not busy, not a journal.

### Anti-goals
- Streak pressure, "crushed it" celebrations, percent-tiered judgment copy.
- Upgrade nudges that only push toward doing more.
- Journal-style entry writing as the main interaction.
- Whimsical illustration. **The garden metaphor is rejected** (and the bloom flower is probably out too).
- A look that feels like a plugin. It should feel like part of WordPress core.

## Decisions made

| Decision | Status |
|---|---|
| Name: **Blog Rhythm**, slug `blog-rhythm` | Decided by Derek. Directory and trademark availability not yet checked. |
| Two surfaces: a **dashboard widget** and a **comprehensive, interactive settings page** | Decided by Derek. |
| UI is built with **WordPress/ui** (`@wordpress/ui`) and should feel like core | Decided by Derek. |
| Mostly passive tracking from posts and drafts; manual input optional | Claude's guess, Derek hasn't objected. |
| Settings are per user, stored in user meta, no custom tables | Claude's guess. Confirm. |
| Visual centerpiece leans toward a restrained dot/heatmap grid, adapting to cadence | Claude's guess. Confirm. |
| Dashboard adapts to the cadence each writer chooses (daily, weekly, monthly) | Claude's guess. Confirm. |
| The three sketches (Bloom, dots, garden) could become options on the settings page | Floated, not decided. Garden is now out. |

## Open questions for Derek

1. **What should someone be able to set on the settings page** to make it feel like theirs? (Asked, not yet answered.) Candidates: cadence, what counts (draft, outline, idea, edit, publish), goals in their own words, quiet-week behavior, widget visuals, reminders, privacy and data export.
2. Passive tracking only, or also a manual "I wrote today" action?
3. Multi-author sites: each author gets their own rhythm, or can an admin see everyone? (Guess: private per author, no admin view.)
4. How do non-publishing activities get tracked? Posts table gives draft touches and new posts. Outlining or ideas outside WordPress would need own storage. Decide where that line is.
5. Targeted WordPress and PHP minimums.

## Mockups (in `docs/mockups/`)

- `dashboard-layouts.html`: Today first / Writing journal / Goals and seasons. Derek: "too busy, too much like a journal."
- `dashboard-visual-v2.html`: Bloom / Year of dots / Little garden. Derek then redirected to the two-surface plan and rejected the garden.

Next mockups should show (a) the **dashboard widget** at real widget size, and (b) the **settings page** laid out like a native WordPress screen, using WordPress/ui and core tokens. The visual companion server from the old session will be gone; restart it from `superpowers:brainstorming` with this folder as `--project-dir` (add `.superpowers/` to `.gitignore`).

## What exists in Opa (reference copies in `reference/`)

The Opa card (`reference/opa-rhythm-section/`, 17 files) offers cadence picker, year heatmap, progress ring, streak badge, feedback line, upgrade nudge, smart and manual goals, and a celebration banner. Backend: `reference/rest-dashboard-rhythm.php` derives per-day activity types (`publish`, `draft`, `idea`) from the posts table, in the site timezone. Goals live in user meta (`opa_rhythm_cadence`, `opa_rhythm_goals`); see `reference/user-preferences-rhythm-excerpt.php`.

**Keep:** user-meta storage with REST schema and per-item sanitize, sparse year data, cadence concept, lenient-on-current-period rule.
**Drop or rework:** streak badge, celebration copy, percent-tiered feedback, upgrade nudge, fixed always-on rhythm goal, `OpaCard`, Phosphor icons, `--opa-*` tokens, inline styles, the `opa/v1` namespace.
**Unverified:** whether manual goals reset each period (schema stores one `done` boolean).
Original repo: `/Users/derekhanson/opa-blog` (commit `80928b4`).

## Latest WordPress standards (researched 2026-10-27, verify before relying)

- **WordPress 7.1.3** is current (7.1 shipped August 2026). **7.2** is due December 8, 2026 (Beta 1 October 20, RC1 November 17). 7.0 shipped May 2026 with DataViews-powered admin screens.
- **Gutenberg 24.1** is latest cited. React 19 is on the roadmap but unlikely to land in 7.2.
- **`@wordpress/ui`** is the cohesive design-system package, built on `@wordpress/theme` design tokens (CSS custom properties). Still labeled *experimental* in its README. Installed from npm with semver; not on `window.wp`. Recent additions by release: 22.9 EmptyState, InputControl, AlertDialog; 23.0 Popover, Dialog; 23.2 SelectControl, motion tokens; 23.3 Text, HStack, VStack promoted; 23.4 Combobox, cascade layers; 24.1 CheckboxGroup, RadioGroup, Switch, SwitchControl. Also seen: Stack, IconButton, ButtonLink, Menu, Tabs, CollapsibleCard, Notice, Drawer. Trunk lists Calendar and RangeCalendar moved from private APIs. **Charts are not mentioned**, so a custom dot grid will probably be hand-built from tokens.
- **`@wordpress/components`**: Divider, ResponsiveWrapper, Scrollable, ZStack, Elevation are deprecated for removal in 7.4. Use Separator and CSS `aspect-ratio`. The `use-recommended-components` ESLint rule gives per-component guidance on UI vs components.
- **Icons** in core are now stroke-based, so CSS `fill` rules won't recolor them. Update `@wordpress/icons` and `@wordpress/components` together. Plugin-only icon collections can be registered with `'public' => false`.
- **DataViews / DataForm**: DataForm is the way to build settings-style forms (panel layout has `showPlaceholderIfEmpty`; combined fields are layout-only). The post sidebar is being rebuilt on DataForm.
- Dashicons are being replaced in the admin bar and menu.
- The WordPress.org review runs an automated security review during release cooldown.

**To check before the design is final:** current `@wordpress/ui` export list and stability, `@wordpress/theme` token usage in a plugin admin page, whether a standard admin-page wrapper component exists (nothing found on `@wordpress/admin-ui`), and the dashboard widget API (`wp_add_dashboard_widget` in PHP, with a React mount point) plus any 7.x changes to it.

## Skills to use in the new session

Invoke before writing code: `superpowers:brainstorming` (now), `wordpress-plugin-development`, `wp-plugin-development`, `wordpress-dataviews` (for DataForm), `wordpress-core-data`, `wordpress-rest-api`, `wp-block-themes` only if theme.json tokens matter, `wp-phpstan`, `wp-playground` for local testing, `wp-ship` for commits and PRs, `wpds` and `a8c-design:wpds-changes` for design-system guidance, `impeccable` or `design-html` for mockup polish. MCP helpers: `wp-devdocs` (docs and hooks), `wp-playground`, `wordpress-studio`. Studio was flaky in the old project; Playground (`npx @wp-playground/cli server`) worked.

## Conventions carried over from Derek's other work

- Label every plan decision **load-bearing** (firm) or **tunable** (validate empirically).
- Lean over comprehensive in user-facing docs.
- Check as you go on multi-step operations.
- Use explicit AskUserQuestion for fix-or-skip judgment calls.
- Rename branches descriptively; PRs branch off `trunk`/`main` as `feat/<name>`.
- Never use team51 MCP for Pressable operations.
- Code: ABSPATH guard, prefix globals, namespace classes, nonce + capability + sanitize/escape.

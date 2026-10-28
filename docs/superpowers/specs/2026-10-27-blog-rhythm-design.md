# Blog Rhythm: design

Status: draft for Derek's approval. Date: 2026-10-27.
Conventions: **[firm]** means load-bearing, change only on purpose. **[tunable]** means validate empirically. **[my call]** means Claude decided it and Derek hasn't weighed in.

## Intent

Blog Rhythm is a standalone WordPress plugin (slug `blog-rhythm`) that helps bloggers, writers, and site owners set their own writing pace and see their own rhythm. It grew out of a #blogging conversation: people want to be more consistent without pressure to write perfectly or post daily. It is extracted from the "Rhythm Section" dashboard card in the dormant Opa project.

The point is personal growth, not performance. Writers decide how often they want to show up (daily, weekly, monthly, or no target at all), and drafting, outlining, and ideas count as showing up, not only publishing. Quiet stretches are normal and are never shown as failure.

It has two surfaces:

1. A **dashboard widget**: a calm picture of recent rhythm, one line of summary, and an optional "I showed up today" button.
2. A **settings page**: where each writer makes it theirs (pace, what counts, their own words, quiet stretches, reminders, widget look, privacy and data).

Both are built with WordPress/ui (`@wordpress/ui`) and core design tokens, and should feel like part of WordPress core, not like a plugin.

## Goals

- A writer can set a pace that fits their life and change it any time without losing anything. **[firm]**
- Passive by default: the rhythm fills in from what WordPress already knows (posts and drafts). Manual input is optional. **[firm]**
- Native look and feel: admin color scheme respected, core tokens, standard admin page layout.
- Approachable to every level: visual, friendly, not busy, not a journal.
- Private: each writer sees only their own rhythm. **[firm]**

## Non-goals and anti-goals

Non-goals for v1:
- The Abilities API integration (see "Later"). Leave room for it, do not build it.
- Admin or editor visibility into other people's rhythm.
- Any chart beyond the dot grid, week stack, and month blocks shown in the mockups.

Anti-goals (a failure even if it technically works): **[firm]**
- Streaks, "crushed it" celebrations, percent-tiered judgment copy, "you missed" language anywhere, including reminders.
- Traffic, stats, likes, or leaderboards.
- Upgrade nudges that push toward doing more.
- Journal-style writing as the main interaction.
- Whimsical illustration. The garden metaphor and the bloom flower are rejected.
- A look that feels like a plugin.

## What the writer can set (settings page)

Approved mockup: `docs/mockups/widget-and-settings-v2.html` (open it in a browser; it is interactive, with the four widget states and the settings page). It is the authority for layout, copy, and the look of the dot grid, week stack, and month blocks. The other two files in `docs/mockups/` are earlier, rejected explorations.

Each card below is a section on one scrolling page, with a sticky live preview of the widget beside it.

| Section | What they set |
|---|---|
| Your pace | Most days / every week / every month / no target. A target number ("times I'd like to show up", default 3 a week, 2 a week, 4 a month). Copy says it is a gentle aim and a miss is never shown as a miss. |
| What counts | Publishing a post, working on a draft, ideas and outlines (logged by the writer), updating an older post (off by default). |
| In your own words | One short line (80 chars) shown at the top of the widget. Empty hides it. |
| Quiet stretches | A "Rest for a while" switch with an optional return date. Hides targets and reminders. Past days stay as they were. No streak exists to lose. |
| Reminders | Off by default. Day and time (site time zone), email and/or a quiet note in the widget, "skip if I've already shown up" (on by default), editable message with a gentle default. Resting pauses reminders. Every email has a one-click turn-off link. |
| Your dashboard widget | Show: match my pace (default) / calendar of dots / week by week. Toggle the one-line summary. Toggle the "I showed up today" button. |
| Privacy and your data | Plain statement that it is private to the account. Export my data (JSON). Clear my history. |

The widget adapts to the chosen pace: daily gets the dot calendar (26 weeks), weekly gets a week-by-week stack of target slots, monthly gets month blocks, no-target gets the dot calendar and no pace line. Going over a target is never celebrated or shown differently.

## Decisions made

| Decision | Status |
|---|---|
| Name **Blog Rhythm**, slug `blog-rhythm` | Derek. Directory and trademark availability not yet checked. |
| Two surfaces: dashboard widget and a comprehensive, interactive settings page | Derek |
| UI built with `@wordpress/ui`, feels like core | Derek |
| Layout and controls as in the v2 mockup | Derek ("looks right") |
| Reminders are configurable and off by default; email reminders are in v1 | Derek |
| Abilities API integration comes in a later version | Derek |
| Mostly passive tracking, manual input optional | Derek, by approving the mockup |
| Settings are per user | Derek, by approving the mockup |
| Private per author, no admin view | Derek, by approving the mockup (the privacy copy says so) |
| Garden metaphor out | Derek |

## How it gets made (Claude's calls unless marked)

Verified 2026-10-27 against npm and developer.wordpress.org (details in the session notes `verified-wp-ui-findings.md`).

**Front end**
- `@wordpress/ui` is 0.25.0 and its README calls it experimental with breaking changes expected. It is not on `window.wp`, so it is bundled with the plugin. **Pin the exact version.** **[firm]**
- If a spike shows bundling `@wordpress/ui` against WordPress's React is not workable, fall back to `@wordpress/components` for the same layout. **[my call]** The first build task is that spike.
- Components in use: Card, Switch/RadioGroup/Checkbox/Select/Input controls, Notice, EmptyState, Button, Text, Stack. No chart component exists, so the dot grid, week stack, and month blocks are hand-built from `--wpds-*` tokens.
- Settings page: `@wordpress/admin-ui` `Page` for the header and layout. Depend on the `wp-theme` style handle (tokens) and `wp-components` instead of bundling token CSS. The brand color follows the writer's admin color scheme through the token fallback to `--wp-admin-theme-color`.
- Build with `@wordpress/scripts`. DataForm is optional for the settings form; use it only if it fits the card layout cleanly.
- Accessibility: the visualization must not rely on color alone. Each activity type needs a non-color cue (shape or pattern, or a text list alternative), a text summary for screen readers, and keyboard-reachable cells or an equivalent list. Respect `prefers-reduced-motion`. **[firm]**

**Dashboard widget**
- `wp_add_dashboard_widget()` on `wp_dashboard_setup`. The callback echoes a mount point and React renders into it. Only users with `edit_posts` see it.
- The "Settings" link in the widget header goes to the settings page. Core's "Configure" mechanism is not used.

**Data**
- Per user, stored in user meta, no custom tables. **[firm]**
  - `blog_rhythm_settings`: one array holding everything in the settings table above, with a REST schema and per-field sanitization.
  - `blog_rhythm_log`: sparse map of date to activity types for things WordPress cannot see (manual "I showed up" and ideas/outlines). Only dates with entries are stored.
- Passive activity is derived from the posts table, in the site time zone, per day: published, drafted (new or modified drafts), and updated. Where revisions exist they can give finer draft history than a post's last-modified date. How far to lean on revisions is the builder's call. **[tunable]**
- Ideas and outlines that happen outside WordPress cannot be seen. The line is: if WordPress can see it, it is automatic; otherwise the writer logs it by hand. No backfilling of manual entries.
- Keep logic in small service classes (activity, settings, reminders) that REST routes call, so other entry points can reuse them later.

**REST**
- Namespace `blog-rhythm/v1` (not `opa/v1`). Routes for settings (read/update), activity for a date range, a manual log entry, data export, and clearing history. Every route checks `edit_posts`, acts only on the current user, and has a `permission_callback`, sanitization, and a nonce for state changes.

**Reminders**
- WP-Cron hourly event finds users whose reminder is due in their site-time-zone window and sends through `wp_mail()`. Skips users who are resting, who have already shown up that period (when the skip option is on), or who have reminders off.
- Turn-off link in each email is a signed, user-bound URL that disables reminders in one click, no login required. **[firm]**
- WP-Cron only fires on traffic, so delivery times are approximate; say so in the settings help text. If the site cannot send mail, nothing is sent and nothing breaks.
- The widget's quiet note shows only when the writer chose it.

**Privacy and lifecycle**
- Register WordPress personal data exporter and eraser for the plugin's user meta.
- `uninstall.php` removes user meta and the cron event.
- Nothing leaves the site. No tracking, no external requests.

**Plugin basics**
- ABSPATH guard, prefixed or namespaced globals, nonce + capability + sanitize/escape, i18n for all strings.
- Minimum WordPress 6.9 and PHP 7.4. **[tunable]** (6.9 gives a clean path to the core Abilities API later.)
- Sentence case everywhere in the UI. No em dashes in any user-facing copy.

**Carried over from Opa** (reference copies in `reference/`): user-meta storage with REST schema and per-item sanitize, sparse year data, the cadence concept, the lenient-on-current-period rule, and the date and rhythm math helpers where they fit. Dropped: streak badge, celebration banner, percent-tiered feedback, upgrade nudge, the always-on rhythm goal, `OpaCard`, Phosphor icons, `--opa-*` tokens, inline styles, the `opa/v1` namespace.

## Behavior rules (settled after a builder review) **[my call]**

- **A "show up" is a day.** A day with any counted activity is one show-up, however many things happened. The pace target is show-up days per period ("3 days this week", as in the mockup). Reminder skipping uses the same rule. Over-target days are not marked differently.
- **Time zone is the site's.** Day boundaries, reminders, and the cron window all use the site time zone. No per-user time zone in v1; the settings help text names the zone.
- **What passive activity covers:** post type `post` only (filterable for other types later), attributed to `post_author` only, so it never shows anyone else's work. Published and private posts count as published. Drafts, pending, and scheduled posts count as drafted. Editing a post published on an earlier day counts as updated, and only when "updating an older post" is on. Trashed posts are ignored.
- **Manual "I showed up today"** logs one idea-or-outline entry for today. A writer can undo today's entry. No past-date entries. The route shape is create-today and delete-today.
- **Clear my history** removes the manual log and sets a "cleared before" date; passive activity before that date is hidden in the widget and excluded from export, since WordPress's own posts can't be deleted. The button copy says exactly that. **Export** includes settings, the manual log, and visible derived activity. The WordPress eraser removes everything the plugin stores.
- **Reminder emails:** from the site's default sender, plain text, the writer's message with no placeholders. A last-sent meta key per period stops the hourly cron from resending. The turn-off link does not expire, is bound to the user, and is invalidated when the writer next turns reminders on.

## Spike gate

The first build task is a spike: render one `@wordpress/ui` component and a token-styled element inside a dashboard widget and an admin page, bundled with `@wordpress/scripts` on the target WordPress. The spike also confirms the `wp-theme` style handle exists on the minimum WordPress version; the version floor is the lowest version where it does. If the spike fails, stop and come back to Derek before changing the UI library. **[firm]**

## Later (not v1)

- **Abilities API:** expose read and log actions (for example "how is my rhythm this week", "log that I showed up") through the WordPress Abilities API, so a writer can connect their own AI tool. The service classes and per-user privacy rule are the groundwork. The privacy default applies: an ability only ever sees the calling user's own data. The design for this is a separate brainstorm.
- Other reminder channels, multi-site network behavior, additional visual options.

## Left to the builder

- Exact component choices within `@wordpress/ui`, and whether DataForm helps.
- Fine details of the dot grid, week stack, and month blocks (sizes, patterns, labels), within the accessibility rule.
- How the passive derivation queries are written and cached, and how much to use revisions.
- Reminder scheduling internals, and the signed-link scheme.
- Where the settings page lives in the admin menu. The mockup shows Settings > Blog Rhythm, but users without `manage_options` do not see Settings, so the right home may be under Posts or Dashboard. **[my call, builder to confirm]**
- Multisite handling, defaulting to per-site settings.
- Test setup: PHPUnit for activity derivation and reminder due-time logic, plus a Playground or wp-env smoke test.

## Open items

- Directory and trademark availability of "Blog Rhythm".
- Whether `@wordpress/ui` bundles cleanly (answered by the first spike).
- Whether the Abilities API shape in WordPress 6.9 and later matches what the later phase needs (verify when that brainstorm starts).

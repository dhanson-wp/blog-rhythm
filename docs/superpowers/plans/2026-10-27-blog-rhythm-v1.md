# Blog Rhythm v1 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the Blog Rhythm WordPress plugin: a per-writer dashboard widget and a comprehensive settings page that show a writer's own rhythm, built with `@wordpress/ui` and core tokens.

**Architecture:** PHP services (settings, manual log, activity derivation, rhythm math, reminders) hold all logic and store everything in user meta. A thin REST layer (`blog-rhythm/v1`) exposes them. Two React apps (widget, settings page) bundled with `@wordpress/scripts` only render what the API returns; pure layout functions build the dot grid, week stack, and month blocks and are unit tested.

**Tech Stack:** PHP 7.4+, WordPress 6.9+, `@wordpress/scripts`, React via `@wordpress/element`, `@wordpress/ui` 0.25.0, `@wordpress/admin-ui` 3.0.0, PHPUnit via `@wordpress/env`, Jest, WordPress Playground for smoke tests.

**Spec:** `docs/superpowers/specs/2026-10-27-blog-rhythm-design.md` (approved by Derek). Approved mockup: `docs/mockups/widget-and-settings-v2.html`. Reference code from Opa: `reference/`. Executors read all three.

## Global Constraints

- WordPress 6.9 and PHP 7.4 minimums are tunable; the floor is the lowest WordPress version where the `wp-theme` style handle exists (Task 1 decides).
- Pin exact versions: `@wordpress/ui` `0.25.0`, `@wordpress/admin-ui` `3.0.0`. If the Task 1 spike fails, stop and ask Derek before changing UI library.
- Storage is user meta only, no custom tables. Settings are per user, private to the account, no admin view. Nothing leaves the site, no external requests.
- REST namespace `blog-rhythm/v1`; every route has `permission_callback` requiring `edit_posts`, acts only on the current user, sanitizes input, and uses the REST nonce for state changes.
- Site time zone governs day boundaries, reminders, and the cron window. No per-user time zone.
- A "show up" is a day with any counted activity. Going over target is never styled differently.
- Passive activity: post type `post` only, `post_author` only; private counts as published; draft, pending, future count as drafted; editing a post published on an earlier day is "updated" and counts only when that option is on; trash is ignored.
- Tokens come from the core `wp-theme` style handle; never bundle token CSS. Use `--wpds-*` tokens, not hard-coded colors.
- Accessibility: never color alone (non-color cue per activity type plus a text alternative), keyboard reachable, `prefers-reduced-motion` respected.
- Anti-goals everywhere, including emails: no streaks, celebrations, "you missed" language, percent judgment, upgrade nudges, stats, or garden imagery.
- Copy: sentence case, contractions, no em dashes. All strings go through i18n with text domain `blog-rhythm`.
- Every PHP file starts with an ABSPATH guard; classes are namespaced `BlogRhythm\`; hooks, meta keys, and handles are prefixed `blog_rhythm`.
- Branch `feat/blog-rhythm-v1` off `main`; commit after each task.

## Review Focus

1. A brand-new user with no posts (and a user whose only posts belong to someone else): widget shows the empty state, no PHP notices, no JS errors. Pinned in Tasks 3 and 8.
2. A post published at 23:30 site time on a day that is already the next day in UTC, and a DST-change day: lands on the correct site-time date. Pinned in Task 3.
3. The hourly cron runs twice in one window, or the user is resting, has reminders off, or already showed up: exactly zero or one email, never two. Pinned in Task 7.
4. Another author's posts, or edits by an editor on your post, never appear in your rhythm or export. Pinned in Task 3.
5. Malformed settings payloads (target 0, 99, or "abc"; intention over 80 characters or containing HTML; unknown keys; bad dates): clamped or rejected, never stored raw. A subscriber calling any route gets 403. Pinned in Tasks 2 and 5.

---

## File Structure

```
blog-rhythm.php              bootstrap, autoloader, hook wiring
uninstall.php                removes user meta and cron event
readme.txt                   wp.org readme
includes/Settings.php        defaults, sanitize, read/write user settings
includes/Log.php             manual log entries and "cleared before" date
includes/Activity.php        derives per-day activity from posts and revisions
includes/Rhythm.php          periods, counted days, summary, reminder period
includes/Reminders.php       due logic, cron, mail, signed turn-off link
includes/Rest.php            blog-rhythm/v1 routes
includes/Privacy.php         personal data exporter and eraser
includes/Dashboard_Widget.php  wp_add_dashboard_widget + mount point
includes/Settings_Page.php   admin menu, enqueue, mount point
src/shared/api.js            apiFetch wrappers
src/shared/layout.js         pure builders: dot columns, week stack, month blocks, text summary
src/shared/Visualization.js  renders the three visuals plus the text alternative
src/widget/                  Widget.js, index.js
src/settings/                SettingsApp.js, cards/*.js, Preview.js, index.js
tests/phpunit/               one test file per service
src/**/test/                 Jest tests
.wp-env.json, package.json, composer.json, phpunit.xml.dist
```

---

### Task 1: Scaffold and spike gate

**Files:**
- Create: `blog-rhythm.php`, `package.json`, `composer.json`, `.wp-env.json`, `phpunit.xml.dist`, `includes/Dashboard_Widget.php` and `includes/Settings_Page.php` (spike stubs), `src/widget/index.js`, `src/settings/index.js`, `tests/phpunit/test-bootstrap.php`, `docs/superpowers/spike-result.md`

**Interfaces:**
- Produces: plugin header (Plugin Name `Blog Rhythm`, Text Domain `blog-rhythm`, Requires at least `6.9`, Requires PHP `7.4`, License GPLv2 or later), constants `BLOG_RHYTHM_VERSION`, `BLOG_RHYTHM_FILE`, `BLOG_RHYTHM_DIR`, `BLOG_RHYTHM_URL`, an autoloader mapping `BlogRhythm\Foo_Bar` to `includes/Foo_Bar.php`, npm scripts `build`, `start`, `test:js`, `test:php`, build entries `widget` and `settings` producing `build/*.asset.php`.

- [ ] **Step 1: Baseline commit.** `git add -A && git commit -m "docs: handoff, spec, mockups, plan"` on `main`, then `git switch -c feat/blog-rhythm-v1`.
- [ ] **Step 2: Create the plugin skeleton** with the header, ABSPATH guard, autoloader, and activation/deactivation hook stubs.
- [ ] **Step 3: Install pinned deps.** `npm i -D @wordpress/scripts @wordpress/env` and `npm i -E @wordpress/ui@0.25.0 @wordpress/admin-ui@3.0.0`. Confirm `package.json` shows exact versions with no caret.
- [ ] **Step 4: Spike stubs.** Widget stub: `wp_add_dashboard_widget` echoing `<div id="blog-rhythm-widget-root">`, enqueueing `build/widget.js` with its `.asset.php` dependencies and the `wp-theme` style handle as a dependency. React stub renders a `Card` and a `SwitchControl` from `@wordpress/ui` styled with one `--wpds-*` token. Settings stub: a submenu page rendering `@wordpress/admin-ui` `Page` with the same two elements.
- [ ] **Step 5: Verify in Playground on 6.9 and on current WordPress.** Run `npx @wp-playground/cli server --auto-mount --wp=6.9` (then latest). Expected: both surfaces render the Card and Switch with correct styling, no console errors; `wp eval 'var_dump(wp_style_is("wp-theme","registered"));'` prints `bool(true)`. If `wp-theme` is not registered on 6.9, find the lowest version that has it and raise the floor in the header and `readme.txt`.
- [ ] **Step 6: Verify the PHP test runner.** Add a trivial passing test; `npm run test:php` (wp-env) must pass. If Docker is unavailable, report that to Derek rather than inventing a different runner.
- [ ] **Step 7: Write `docs/superpowers/spike-result.md`** (what rendered, WordPress versions tried, floor chosen). **If the UI spike failed, STOP and ask Derek before continuing.**
- [ ] **Step 8: Commit** `feat: scaffold plugin and bundle spike`.

### Task 2: Settings service

**Files:** Create `includes/Settings.php`; Test `tests/phpunit/test-settings.php`

**Interfaces:**
- Produces (class `BlogRhythm\Settings`): `const META_KEY = 'blog_rhythm_settings'`; `defaults(): array`; `sanitize( array $input ): array`; `get( int $user_id ): array`; `update( int $user_id, array $input ): array` (returns stored settings); `is_resting( array $settings, \DateTimeImmutable $now ): bool`.
- Settings shape: `pace` (`daily|weekly|monthly|open`), `target` (int 1-7 for daily/weekly, 1-31 for monthly), `counts` (`publish`, `draft`, `idea`, `edit` bools), `intention` (string, 80 max), `resting` (bool), `rest_until` (`Y-m-d` or ``), `reminders` (`enabled`, `day` in `daily|weekdays|mon|wed|fri|sun`, `hour` int 0-23, `email`, `widget_note`, `skip_if_done` bools, `message` string 120 max), `look` (`auto|dots|weeks`), `show_summary`, `show_log_button` bools.
- Defaults: pace `daily`, target `3`, counts publish/draft/idea true and edit false, intention `Write a little, most days.`, reminders off, day `mon`, hour `9`, email true, widget_note false, skip_if_done true, message `Your rhythm is here whenever you are. Want to write a little?`, look `auto`, both show flags true.
- Side effect: `update` mirrors `reminders.enabled` into user meta `blog_rhythm_reminders_on` (`1` or deleted), and regenerates user meta `blog_rhythm_reminder_secret` (random 32 chars) whenever reminders go from off to on.

- [ ] **Step 1: Write failing tests:** `test_defaults_match_spec`; `test_sanitize_clamps_target` (0 becomes 1, 99 becomes 7 for weekly, "abc" becomes default); `test_sanitize_strips_html_and_truncates_intention` (HTML tags removed, 80 chars max); `test_sanitize_drops_unknown_keys_and_bad_enums`; `test_sanitize_rejects_invalid_rest_until`; `test_update_persists_and_merges_over_defaults`; `test_enabling_reminders_sets_flag_and_rotates_secret`; `test_is_resting_respects_rest_until` (resting with a past `rest_until` is false; empty `rest_until` stays true).
- [ ] **Step 2: Run** `npm run test:php -- --filter Settings`; expect FAIL (class missing).
- [ ] **Step 3: Implement** the signatures above. Sanitize with `sanitize_text_field` and explicit enum/int clamps; never store unknown keys.
- [ ] **Step 4: Run tests; expect PASS. Commit** `feat: settings service`.

### Task 3: Activity derivation

**Files:** Create `includes/Log.php`, `includes/Activity.php`; Test `tests/phpunit/test-log.php`, `tests/phpunit/test-activity.php`

**Interfaces:**
- Consumes: `Settings::get`.
- Produces: `Log::META_KEY = 'blog_rhythm_log'` (stores `['entries' => [date => ['idea']], 'cleared_before' => '']`); `Log::add_today( int $user_id ): bool`; `Log::remove_today( int $user_id ): bool`; `Log::entries( int $user_id ): array`; `Log::clear( int $user_id ): void` (empties entries, sets `cleared_before` to today); `Log::cleared_before( int $user_id ): string`. `Activity::for_range( int $user_id, string $from, string $to ): array` returning `[ 'Y-m-d' => string[] ]` where each value is a subset of `published|drafted|updated|idea`, computed in site time zone, with manual entries merged and anything before `cleared_before` omitted.

- [ ] **Step 1: Write failing tests** for `Log`: add then remove today; `add_today` twice stores one entry; `clear` hides earlier passive days and empties entries.
- [ ] **Step 2: Write failing tests** for `Activity::for_range`: `test_published_post_counts_on_site_date`; `test_late_evening_post_uses_site_timezone_not_utc` (site tz America/New_York, post at 23:30 local); `test_dst_change_day_has_one_entry`; `test_draft_pending_future_are_drafted`; `test_private_counts_as_published`; `test_trashed_posts_ignored`; `test_other_authors_posts_never_appear`; `test_editor_revision_on_my_post_does_not_count_for_me` (revision authored by someone else); `test_my_revision_counts_as_drafted`; `test_edit_of_older_published_post_is_updated`; `test_pages_ignored`; `test_user_with_no_posts_returns_empty_array`; `test_range_filters_before_cleared_before`.
- [ ] **Step 3: Run; expect FAIL.**
- [ ] **Step 4: Implement** `Log` (sanitized date keys, today from site time) and `Activity` (one query for the user's `post` rows with statuses `publish,private,draft,pending,future` and one for revisions authored by the user whose parent is a `post`; convert `post_date`/`post_modified` using `wp_timezone()`; prime no per-day queries).
- [ ] **Step 5: Run tests; expect PASS. Commit** `feat: manual log and activity derivation`.

### Task 4: Rhythm math

**Files:** Create `includes/Rhythm.php`; Test `tests/phpunit/test-rhythm.php`. Reuse ideas from `reference/opa-rhythm-section/rhythm-math.js` and `date-helpers.js` only where they fit; drop streaks and percent feedback.

**Interfaces:**
- Consumes: `Settings::get`, `Settings::is_resting`, `Activity::for_range`.
- Produces: `Rhythm::counted_days( array $activity, array $counts ): array` (date to top counted type `published|drafted|idea`, `updated` shown as `drafted`, days with nothing counted omitted); `Rhythm::summary_period( string $pace ): string` (`week` for daily/weekly/open, `month` for monthly); `Rhythm::reminder_period( string $pace ): string` (`day` for daily, `week` for weekly/open, `month` for monthly); `Rhythm::period_bounds( string $period, \DateTimeImmutable $now ): array` (`start`, `end` as `Y-m-d`, week start from the `start_of_week` option); `Rhythm::summary( int $user_id, \DateTimeImmutable $now ): array` with `count` (show-up days this summary period), `target`, `pace`, `period`, `resting`, `rest_until`; `Rhythm::has_shown_up( int $user_id, \DateTimeImmutable $now ): bool` (any counted day in the reminder period).

- [ ] **Step 1: Write failing tests:** `test_one_day_with_many_activities_is_one_show_up`; `test_updated_only_counts_when_edit_enabled`; `test_idea_excluded_when_counts_idea_false`; `test_week_bounds_respect_start_of_week`; `test_month_bounds`; `test_summary_for_open_pace_has_zero_target`; `test_has_shown_up_uses_day_for_daily_and_week_for_weekly`; `test_over_target_reports_actual_count_without_flag`.
- [ ] **Step 2: Run; expect FAIL. Step 3: Implement. Step 4: Run; expect PASS. Commit** `feat: rhythm math`.

### Task 5: REST routes

**Files:** Create `includes/Rest.php`; Test `tests/phpunit/test-rest.php`

**Interfaces:**
- Consumes: Settings, Log, Activity, Rhythm.
- Produces (namespace `blog-rhythm/v1`, all require `edit_posts`): `GET /rhythm?weeks=26` (default 26, 1-53) returns `{ settings, days: {date: type}, summary, today, note }` (`note` is `null` until Task 7 supplies it); `GET /settings`; `POST /settings` (returns stored settings); `POST /log` (add today, returns refreshed `/rhythm` payload); `DELETE /log` (remove today, same); `POST /history/clear`; `GET /export` is added in Task 6.

- [ ] **Step 1: Write failing tests:** `test_subscriber_gets_403_on_every_route`; `test_logged_out_gets_401`; `test_settings_post_sanitizes_payload` (target 99, HTML intention, unknown key); `test_rhythm_returns_only_current_users_days`; `test_rhythm_for_new_user_is_empty_not_error`; `test_log_post_then_delete_roundtrip`; `test_weeks_param_clamped`; `test_clear_history_hides_earlier_days`.
- [ ] **Step 2: Run; expect FAIL. Step 3: Implement** a `register_routes()` on `rest_api_init` with `args` schemas. **Step 4: Run; expect PASS. Commit** `feat: rest api`.

### Task 6: Privacy, export, uninstall

**Files:** Create `includes/Privacy.php`, `uninstall.php`; Modify `includes/Rest.php`; Test `tests/phpunit/test-privacy.php`

**Interfaces:**
- Produces: `Privacy::register()` adding an exporter and eraser under the `blog-rhythm` slug on `wp_privacy_personal_data_exporters` / `_erasers`; `Privacy::export_data( int $user_id ): array` (settings, manual log, visible derived activity); `GET /export` returning that array. The eraser deletes meta keys `blog_rhythm_settings`, `blog_rhythm_log`, `blog_rhythm_reminders_on`, `blog_rhythm_reminder_secret`, `blog_rhythm_reminder_last`.

- [ ] **Step 1: Write failing tests:** `test_export_includes_settings_log_and_visible_activity`; `test_export_excludes_activity_before_cleared_before`; `test_eraser_removes_all_plugin_meta`; `test_export_never_includes_other_users`; `test_uninstall_removes_meta_for_all_users_and_cron_event`.
- [ ] **Step 2: Run; expect FAIL. Step 3: Implement.** **Step 4: Run; expect PASS. Commit** `feat: privacy export, eraser, uninstall`.

### Task 7: Reminders

**Files:** Create `includes/Reminders.php`; Modify `includes/Rest.php` (fills `note`), `blog-rhythm.php` (schedule `blog_rhythm_send_reminders` hourly on activation, clear on deactivation); Test `tests/phpunit/test-reminders.php`

**Interfaces:**
- Consumes: Settings, Rhythm.
- Produces: `Reminders::is_due( array $settings, \DateTimeImmutable $now ): bool` (reminders enabled, `now` day matches `day`, hour equals `hour`, all in site tz); `Reminders::period_key( array $settings, \DateTimeImmutable $now ): string` (day, ISO week, or month per `Rhythm::reminder_period`); `Reminders::run( ?\DateTimeImmutable $now = null ): int` (emails sent; queries users by meta `blog_rhythm_reminders_on` = `1`; skips resting users, users with `email` off, users with `skip_if_done` and `Rhythm::has_shown_up`, and users whose `blog_rhythm_reminder_last` equals the current period key; sends via `wp_mail()` as plain text using the writer's message, then records the period key); `Reminders::turn_off_url( int $user_id ): string` and `Reminders::verify_token( int $user_id, string $token ): bool` (HMAC-SHA256 of the user id with `blog_rhythm_reminder_secret`; no expiry; invalid after the secret rotates); handler on `admin_post_nopriv_blog_rhythm_turn_off` and `admin_post_blog_rhythm_turn_off` that disables reminders when the token verifies and shows a short plain confirmation; `Reminders::note_due( int $user_id, \DateTimeImmutable $now ): ?string` (the writer's message when reminders and `widget_note` are on, not resting, a reminder day, the reminder hour has passed, and not yet shown up; otherwise `null`).
- Email body: the writer's message plus the turn-off link; no "missed", no counts.

- [ ] **Step 1: Write failing tests** (mock `wp_mail` via the `pre_wp_mail` filter): `test_is_due_matches_day_and_hour_in_site_tz`; `test_weekdays_option_skips_weekend`; `test_run_twice_in_same_window_sends_once`; `test_resting_user_gets_nothing`; `test_email_off_gets_nothing`; `test_skip_if_done_gets_nothing_when_shown_up`; `test_skip_if_done_off_still_sends`; `test_email_contains_turn_off_link_and_no_forbidden_words` (no "missed", "streak", "behind"); `test_valid_token_turns_reminders_off`; `test_tampered_or_old_token_is_rejected`; `test_token_invalid_after_reenabling`; `test_note_due_only_when_widget_note_on`.
- [ ] **Step 2: Run; expect FAIL. Step 3: Implement.** **Step 4: Run; expect PASS. Commit** `feat: reminders`.

### Task 8: Shared layout and visualization

**Files:** Create `src/shared/api.js`, `src/shared/layout.js`, `src/shared/Visualization.js`, `src/shared/style.scss`; Test `src/shared/test/layout.test.js`

**Interfaces:**
- Produces (pure functions in `layout.js`, all take ISO `Y-m-d` strings and the `days` map from `GET /rhythm`): `buildDotColumns( days, today, weeks, weekStart ): Column[]` (columns of 7 cells `{ date, type|null, isToday, isFuture }`, padded at the start, with `monthLabels: {index, label}[]`); `buildWeekStack( days, today, target, weeks = 12 ): Week[]` (slots equal to `max(target, 2)`, filled count capped at slot count); `buildMonthBlocks( days, today, months = 12 ): Month[]` (shade by show-up days: 0 none, 1-3 light, 4-8 mid, 9+ strong); `describeRhythm( summary, settings ): string` (plain sentence such as "3 days this week. Your pace is 3 a week."; resting and quiet-week variants; never negative wording). `Visualization.js` exports default `Visualization( { look, days, today, settings, summary } )` choosing dots, weeks, or months by `look` and `settings.pace` (auto: daily/open to dots, weekly to weeks, monthly to months) and always rendering a visually hidden list alternative with one entry per active day (`"Oct 27: published"`).
- Visual spec: match `docs/mockups/widget-and-settings-v2.html` (10px cells, 3px gap, token colors; published solid, drafted and idea distinguished from published by a non-color cue such as pattern or shape; resting days hatched). Legend text unchanged: Published, Drafted, Idea or outline.
- `api.js`: `getRhythm( weeks )`, `getSettings()`, `saveSettings( settings )`, `logToday()`, `undoToday()`, `clearHistory()`, `exportData()`, all via `@wordpress/api-fetch` against `blog-rhythm/v1`.

- [ ] **Step 1: Write failing Jest tests:** `buildDotColumns pads first column and marks today`; `buildDotColumns month label appears once per month change and skips a crowded first label`; `buildWeekStack never exceeds slot count when over target`; `buildMonthBlocks shades by thresholds`; `describeRhythm never contains missed, behind, streak`; `describeRhythm resting variant`; `empty days map yields all-empty grid without throwing`; `Visualization renders a text alternative entry for every active day` (React Testing Library).
- [ ] **Step 2: Run** `npm run test:js`; expect FAIL. **Step 3: Implement.** **Step 4: Run; expect PASS. Commit** `feat: shared layout and visualization`.

### Task 9: Dashboard widget

**Files:** Modify `includes/Dashboard_Widget.php`, `src/widget/index.js`; Create `src/widget/Widget.js`, `src/widget/style.scss`; Test `src/widget/test/widget.test.js`, `tests/phpunit/test-widget.php`

**Interfaces:**
- Consumes: `api.getRhythm`, `api.logToday`, `api.undoToday`, `Visualization`, `describeRhythm`.
- Produces: `Dashboard_Widget::register()` on `wp_dashboard_setup`, only for users with `edit_posts`; widget id `blog_rhythm_widget`, title `Blog Rhythm`, context `normal`, priority `default`, with a "Settings" link in the header to the settings page (Task 10 exposes its URL via `Settings_Page::url(): string`); enqueues the `widget` build only on `index.php`.
- `Widget.js` states per the mockup: loading skeleton; empty state ("Set a pace that suits you" with a "Choose your pace" button linking to settings) shown when settings are default and no days exist; resting state (summary "Resting until {date}", no targets, no log button); normal (intention line if non-empty, visualization, one-line summary if `show_summary`, "I showed up today" button if `show_log_button`, which toggles to "Undo" after logging); quiet note above the visual when the API's `note` is non-null.

- [ ] **Step 1: Write failing tests:** Jest `renders empty state for new user`; `renders resting copy and no log button when resting`; `hides intention when empty`; `log button calls logToday then refreshes and offers undo`; `shows note only when API returns one`; `error from API shows a quiet inline Notice, not a crash`. PHPUnit `test_widget_registered_for_authors_not_subscribers`; `test_script_only_enqueued_on_dashboard`.
- [ ] **Step 2: Run; expect FAIL. Step 3: Implement.** **Step 4: Run both suites; expect PASS.** **Step 5: Playground smoke:** log in, load the dashboard, confirm the widget matches the mockup at about 420px wide with no console errors. **Commit** `feat: dashboard widget`.

### Task 10: Settings page

**Files:** Modify `includes/Settings_Page.php`, `src/settings/index.js`; Create `src/settings/SettingsApp.js`, `src/settings/Preview.js`, `src/settings/cards/{Pace,WhatCounts,InYourWords,QuietStretches,Reminders,WidgetLook,Privacy}.js`, `src/settings/style.scss`; Test `src/settings/test/settings.test.js`, `tests/phpunit/test-settings-page.php`

**Interfaces:**
- Consumes: `api.*`, `Visualization`, `describeRhythm`.
- Produces: `Settings_Page::register()` adding a submenu page titled `Blog Rhythm` under Posts (`edit.php`), capability `edit_posts`, slug `blog-rhythm` (call: the spec leaves the menu home to the builder; Posts works for every user who can write); `Settings_Page::url(): string`. `SettingsApp` uses `@wordpress/admin-ui` `Page` (title `Blog Rhythm`, badge `Only you can see this`, subtitle from the mockup) with the seven cards in the spec order, a sticky `Preview` (the real widget component fed from unsaved form state), and a Save button that calls `saveSettings`. Copy comes verbatim from the mockup. Cadence change resets the target to 3 (daily), 2 (weekly), 4 (monthly) as in the mockup. The reminders help text states delivery times are approximate and that the site must be able to send email. "Clear my history" confirms with an `AlertDialog` whose copy says earlier post activity is hidden, not deleted.

- [ ] **Step 1: Write failing tests:** `changing pace updates preview and default target`; `no target field when pace is open`; `target input rejects out-of-range values client-side`; `reminders options appear only when switched on`; `resting hides reminders note and shows return date`; `save sends sanitized payload and shows a success Notice`; `server validation error shows inline and keeps form state`; `clear history asks for confirmation before calling the API`; `export triggers a JSON download`. PHPUnit `test_menu_visible_to_authors`; `test_menu_hidden_from_subscribers`.
- [ ] **Step 2: Run; expect FAIL. Step 3: Implement. Step 4: Run; expect PASS. Step 5: Playground smoke** against the mockup: every control, live preview, keyboard-only pass through the form. **Commit** `feat: settings page`.

### Task 11: Quality pass and release prep

**Files:** Create `readme.txt`, `languages/.gitkeep`; Modify as findings require

- [ ] **Step 1: Accessibility.** Run an axe pass on both surfaces; fix findings. Confirm the visualization has a non-color cue and its hidden list reads correctly with a screen reader, and animations honor `prefers-reduced-motion`.
- [ ] **Step 2: Copy sweep.** Grep user-facing strings, emails, and `readme.txt` for em dashes, "missed", "streak", "behind", and title-case headings; fix.
- [ ] **Step 3: i18n and escaping.** `wp i18n make-pot` must run clean; review every echo for escaping.
- [ ] **Step 4: Plugin Check.** `wp plugin check blog-rhythm --categories=plugin_repo,security,performance` must report no errors; also run PHPCS with WordPress standards and fix.
- [ ] **Step 5: Full suites.** `npm run test:php` and `npm run test:js` pass; `npm run build` produces `build/`; a production zip contains no dev files.
- [ ] **Step 6: `readme.txt`** (lean): description, installation, FAQ ("Who can see my rhythm?" answers: only you), changelog `1.0.0`, `Tested up to` the WordPress version used in Task 1.
- [ ] **Step 7: Commit** `chore: quality pass and readme`, then open the PR from `feat/blog-rhythm-v1` to `main` through `wp-ship`.

---

## Self-review notes

- Spec coverage: settings table (Tasks 2, 10), passive and manual data rules (3, 4), REST (5), privacy and lifecycle (6), reminders and quiet note (7, 9), visuals and accessibility (8, 11), widget (9), spike gate and floor (1), Abilities API intentionally absent (services are the groundwork).
- Plan calls where the spec was silent: reminder skip period is day, week, or month per pace; settings page lives under Posts; reminders are found through a mirrored `blog_rhythm_reminders_on` flag; `updated` renders with the drafted treatment.

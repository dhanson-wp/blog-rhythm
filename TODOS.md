# TODOS

## Before WordPress.org submission

- [ ] Confirm the `Contributors:` username in readme.txt (currently `derekhanson`).
- [ ] Check that "Blog Rhythm" is free in the plugin directory and as a name.
- [ ] Decide on the turn-off link: it's a one-click GET (plus RFC 8058 headers), so some corporate mail scanners could switch reminders off by prefetching it. A confirm page would fix that but costs the one click.
- [ ] Decide on multisite: settings follow the account across a network; per-site settings would need a storage change.
- [x] Run one page-level axe pass in a browser on both screens, and check forced colors and contrast by hand. Done on the Studio site with axe-core 4.14.0: no plugin violations (only core admin bar and menu), all text passes AA, and forced colors now redraws the Drafted ring without the brand-blue shadow leaking through.
- [x] Run the PHP suite once on PHP 7.4 (wp-env runs 8.2). All 117 tests pass on PHP 7.4.33 with no changes; use a gitignored `.wp-env.override.json` with `"phpVersion": "7.4"` to repeat it.
- [ ] Revisit "Tested up to" when WordPress 7.2 ships (December 8, 2026).

## Later

- [ ] Abilities API: read and log actions for a writer's own AI tools (separate brainstorm).
- [x] Fix the 28 existing `lint-js` errors in `src/`. Formatting only (the built bundle is byte-identical), and `npm run lint:js` now also lints the tests with Jest globals.
- [ ] Preview the widget's quiet reminder note on the settings page.
- [ ] Use the payload that POST and DELETE `/log` already return instead of refetching.
- [ ] Add the blog ID to the activity memo key if the plugin ever switches blogs.
- [ ] Make the reminder period claim atomic (a per-user lock) if double sends ever show up.
- [ ] Decide whether to darken the resting hatch and the lightest month shade. Both sit near 1.2 to 1.6:1 against the card; text already says when you're resting, so it's a design call, not a WCAG failure.
- [ ] Clear the existing `lint-style` errors (mostly the deliberate `--wpds-*` fallbacks, which the WPDS plugin flags).

## Completed

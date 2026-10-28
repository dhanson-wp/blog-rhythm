# Spike result: scaffold and bundle

## Build outcome
PASS. `npm run build` (`@wordpress/scripts` 37.0.0, webpack config with named entries `widget` and `settings` in `webpack.config.js`) compiles with no errors or warnings and emits `build/widget.js`, `build/settings.js`, and both `.asset.php` files.

## Versions
- `@wordpress/ui` 0.25.0 and `@wordpress/admin-ui` 3.0.0 (exact, no caret), `@wordpress/scripts` 37.0.0, `@wordpress/env` 12.0.0 (all pinned exactly).
- Node 24.15.0 (`@wordpress/ui` requires >= 22.13).

## Bundle notes
- Both packages bundle cleanly. React, `react-jsx-runtime`, `wp-element`, and `wp-i18n` are externalized (see `.asset.php`); settings also lists `wp-components` (pulled in by `@wordpress/admin-ui`).
- Sizes: `widget.js` about 50 KiB, `settings.js` about 79 KiB minified.
- Neither package needs a stylesheet import. Component CSS is injected at runtime through `@wordpress/style-runtime`, so the build emits no `.css`. The PHP enqueue therefore does not enqueue a plugin stylesheet; it enqueues the core `wp-theme` handle (guarded by `wp_style_is( 'wp-theme', 'registered' )`) so `--wpds-*` tokens resolve. Token CSS is never bundled.
- Import names verified against package source: `Card.Root`, `Card.Content`, and `SwitchControl` from `@wordpress/ui`; `Page` from `@wordpress/admin-ui`.
- A bare `wp-scripts build src/a.js src/b.js` collapses both entries into one `index.js`; `webpack.config.js` with named entries is required to get `widget.*` and `settings.*`.

## `wp-theme` style handle, per WordPress version (Playground CLI, PHP 8.3)
| Version | `wp_style_is( 'wp-theme', 'registered' )` |
| --- | --- |
| 6.9.10 | false |
| 7.0.7 | false (also false with wp-admin includes loaded) |
| 7.1.3 (current "latest") | true |
| 7.2-beta2 (Studio site) | true |

## Floor chosen
WordPress 7.1. 6.9 and 7.0 lack `wp-theme`, so "Requires at least" is raised to `7.1` in `blog-rhythm.php`. `readme.txt` does not exist yet; it must say 7.1 when it is created in a later task.

## Render checks
- Playground 7.1.3 (`server --auto-mount --wp=7.1 --login`), headless-style browser check: dashboard widget and Settings > Blog rhythm page both render the Card and Switch (`role="switch"`), the `--wpds-color-fg-content-neutral` token resolves (rgb(30, 30, 30)), and the console shows no errors.
- Studio site (7.2-beta2, SQLite): plugin activates with no errors; WP-CLI eval confirms the dashboard widget registers, the submenu page registers, both scripts enqueue, and `wp-theme` enqueues; no `debug.log` created.

## PHPUnit
`npm run test:php` (wp-env tests-cli, PHP 8.2, PHPUnit 9.6.38, yoast/phpunit-polyfills 2.x): OK (1 test, 2 assertions).

## Pending controller check
Visual render on the Studio site (7.2-beta2, Ipsum theme) in a real browser.

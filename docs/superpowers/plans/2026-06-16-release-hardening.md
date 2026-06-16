# Release Hardening Plan (issues #16, #22, #20, #21)

> **For agentic workers:** implement task-by-task; each task is its own branch + PR, squash-merged after Michael verifies. Run `accessibility-lead` on any PR touching admin views or front-end output.

**Goal:** Close out the remaining Phase 4 (quality/maintainability) and Phase 5 (release readiness) issues so the plugin is distribution-ready.

**Tech Stack:** WordPress 7+, PHP 8.3, `$wpdb`, PHPUnit 10 + Brain Monkey, Composer, PHPCS.

**Sequencing:** four issues, four PRs, in this order. #16 and #22 are independent quality fixes; #20 is split into a tooling PR (Phase 4) and a readme/headers PR (Phase 5); #21 is a small cleanup that should ride after the tooling lands so PHPCS confirms it.

1. **#16** — paginate the subscribers admin list (Phase 4)
2. **#22** — explicit option autoload flags (Phase 4)
3. **#20a** — PHPCS + Plugin Check tooling + CI (Phase 4)
4. **#21** — remove/guard `error_log()` calls (Phase 5, verified clean by the new PHPCS)
5. **#20b** — `readme.txt` + plugin headers + version bump (Phase 5)

---

## Decisions needed before implementation

These three shape the work; the rest follows established patterns.

- **D1 (#16 pagination style):** simple `LIMIT/OFFSET` + core `paginate_links()` **(recommended)**, matching the plugin's hand-rolled admin style, versus `WP_List_Table`. Recommendation: simple pagination — lighter, and nothing else in the admin uses `WP_List_Table`.
- **D2 (#20b version bumps):** bump the plugin **Version `1.0.0` → `1.1.0`** (this release adds the source-account feature) and bump **`Requires PHP: 8.0` → `8.3`** to match the project standard in CLAUDE.md. Recommendation: do both in the readme/headers PR. Confirm the version number.
- **D3 (#20a Plugin Check):** the official Plugin Check is a WordPress plugin that needs a running WP install, so it can't run in plain CI. Recommendation: wire **PHPCS + PHPUnit + `php -l`** into a GitHub Actions workflow, add a `composer lint` script, and document Plugin Check as a local `wp-env`/manual step in CONTRIBUTING. Confirm whether a CI workflow is wanted.

---

## #16 — Paginate the subscribers admin list (Phase 4)

**Branch:** `fix/issue-16-subscribers-pagination`

**Files:** `admin/views/subscribers.php`

**Problem:** both query branches call `$wpdb->get_results( ... ORDER BY s.created_at DESC )` with no `LIMIT`, rendering every subscriber on one page.

**Approach (D1 = simple pagination):**
- Add a `const OUTPOST_SUBSCRIBERS_PER_PAGE = 50;` (or inline) and read `$paged = max( 1, (int) ( $_GET['paged'] ?? 1 ) )`.
- Run a `COUNT(*)` query (with the same optional `hashtag_id` filter) for the total.
- Add `LIMIT %d OFFSET %d` (`$per_page`, `($paged-1)*$per_page`) to the list query, keeping `$wpdb->prepare` and the existing JOIN/filter.
- Render `paginate_links()` below the table (core, accessible: it emits an accessible prev/next nav). Wrap it in a `<nav aria-label="Subscribers pagination">`.
- Preserve the `hashtag_id` filter across pages via `add_args` on `paginate_links()`.

**Accessibility:** UI change → `accessibility-lead` review (focus the pagination nav labelling and that the results count is announced).

**Verification:** with >50 subscribers, confirm pages of 50, working prev/next, filter preserved across pages, and the total count shown.

---

## #22 — Explicit option autoload flags (Phase 4)

**Branch:** `fix/issue-22-option-autoload`

**Files:** `includes/class-outpost-activator.php`

**Problem:** all options are created without an explicit autoload value, so they all autoload on every request.

**Autoload map:**
- **Autoload `yes`** (read on the front end): `outpost_branding_text`, `outpost_branding_url`, `outpost_cache_duration`, `outpost_brand_account`.
- **Autoload `no`** (admin/cron only): `outpost_digest_send_hour`, `outpost_digest_send_minute`, `outpost_from_name`, `outpost_from_email`, `outpost_posts_per_digest`, `outpost_digest_batch_size`, `outpost_double_optin`, `outpost_manage_page_id`, `outpost_db_version`, `outpost_show_setup_wizard`.

**Approach:**
- In `set_defaults()`, replace the `update_option()` seeding with `add_option( $key, $value, '', $autoload )`, where `$autoload` comes from the map (`'yes'`/`'no'`).
- For already-installed sites, apply the autoload change once via a migration step in `maybe_upgrade()`: bump the schema marker to `1.2.0` and call `wp_set_options_autoload( $no_list, false )` and `wp_set_options_autoload( $yes_list, true )` (available since WP 6.4; the plugin requires 7.0).
- `OUTPOST_Settings::save()` keeps using `update_option()` (autoload is fixed at creation/migration; saves don't change it).

**Tests:** none practical (option storage needs WP); verified manually. Optionally a small unit test asserting the autoload map function returns the expected lists.

**Verification:** on an existing install, after load, spot-check `wp_load_alloptions()` no longer includes the admin-only keys (or check the `autoload` column via a query).

---

## #20a — PHPCS + Plugin Check tooling + CI (Phase 4)

**Branch:** `chore/issue-20-tooling`

**Files:** `composer.json`, `phpcs.xml.dist` (new), `.github/workflows/ci.yml` (new), `CONTRIBUTING.md` (new or append)

**Approach:**
- Add dev deps: `squizlabs/php_codesniffer`, `wp-coding-standards/wpcs`, `phpcompatibility/phpcompatibility-wp`, and `dealerdirect/phpcodesniffer-composer-installer` (registers standards).
- `phpcs.xml.dist`: use `WordPress-Extra` + `WordPress-Docs` (or a trimmed ruleset), set `testVersion` 8.3- for PHPCompatibility, configure the text-domain sniff (`outpost`) and the prefix sniff (`outpost`/`OUTPOST_`), and exclude `vendor/` and `tests/` (or relax rules there).
- Triage the first run: fix real findings (escaping/nonce/i18n/prefix) and add narrowly-scoped, commented `phpcs:ignore` only where justified (the uninstall/direct-query lines already do this).
- `composer.json` scripts: `"lint": "phpcs"`, `"lint:fix": "phpcbf"`, `"test": "phpunit"`.
- `.github/workflows/ci.yml` (D3): matrix PHP 8.3, steps = `composer install`, `php -l` sweep, `composer lint`, `composer test`.
- `CONTRIBUTING.md`: document running PHPCS locally and running the official **Plugin Check** via `wp-env` / a local WP install (it can't run in plain CI).

**Accessibility:** none (tooling/config only).

**Verification:** `composer lint` runs and passes (after triage); CI workflow green on a test push.

---

## #21 — Remove or guard `error_log()` calls (Phase 5)

**Branch:** `chore/issue-21-error-log`

**Files:** `includes/class-outpost-feed-fetcher.php` (lines 242, 249, 257 — in `fetch_from_api()`)

**Approach:**
- Remove the three unconditional `error_log()` calls. The methods already return `WP_Error`, and callers (`get_posts()`) handle the error with stale-cache fallback, so behavior is unchanged.
- If we want to keep diagnostics, gate behind `if ( defined( 'WP_DEBUG' ) && WP_DEBUG )` instead — but recommendation is to remove, since Plugin Check flags `WordPress.PHP.DevelopmentFunctions` and the `WP_Error` path already carries the message.

**Verification:** `composer lint` (from #20a) no longer flags development functions in the fetcher; feed still degrades gracefully on a simulated API error.

---

## #20b — readme.txt + plugin headers + version bump (Phase 5)

**Branch:** `chore/issue-20-readme`

**Files:** `readme.txt` (new), `outpost.php` (header), `README.md` (optional changelog cross-link)

**Approach:**
- Create `readme.txt` in the WordPress.org format: header block (Contributors, Tags, Requires at least `7.0`, Tested up to, Requires PHP `8.3`, Stable tag `1.1.0`, License), then `== Description ==`, `== Installation ==`, `== Frequently Asked Questions ==`, `== Changelog ==`, `== Upgrade Notice ==`. Source the prose from `README.md` so the two stay aligned.
- `outpost.php` header (D2): add `Tested up to: <current WP>`, bump `Version: 1.1.0`, bump `Requires PHP: 8.3`. Bump the `OUTPOST_VERSION` constant to match.
- Changelog entry for 1.1.0 summarizing the security/a11y fixes and the source-account feature shipped this cycle.

**Accessibility:** none (docs/headers).

**Verification:** `readme.txt` parses in the WordPress.org readme validator; header shows the new version/requirements; `OUTPOST_VERSION` matches.

---

## Notes
- One issue per branch/PR; squash-merge only after Michael verifies.
- After #20a lands, re-run `composer lint` on each subsequent branch.
- Plugin Check remains a manual gate (documented in CONTRIBUTING) since it requires WordPress.

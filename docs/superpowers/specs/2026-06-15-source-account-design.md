# Source account support — design (issues #9, #10)

Date: 2026-06-15
Issues: [#9](https://github.com/Community-Access/outpost/issues/9), [#10](https://github.com/Community-Access/outpost/issues/10)
Status: approved, pending implementation plan

## Goal

Let a site show Mastodon content sourced from a specific account, in two ways:

1. **#9 — per-hashtag account filter:** each hashtag config can optionally be
   restricted to a single account, so its feed and digest only include posts
   from that account. Plus a single global "brand account" setting.
2. **#10 — brand account feed:** fetch and display all of the global brand
   account's own posts (not hashtag-filtered).

Shortcodes and the classic widget stay registered; this is additive.

## Decisions (from brainstorm)

- #10 includes both the data layer and a front-end display.
- Display surface: a new shortcode `[outpost_account_feed]` and a new dynamic
  block `outpost/account-feed`. Not an overload of the existing feed block.
- Brand account is entered as a single full handle `user@instance.social`
  (leading `@` optional); the instance is parsed from it. No separate instance
  field.
- The brand feed shows original posts only: `exclude_replies=true` and
  `exclude_reblogs=true`.

## Data model & migration (#9)

- Add column to `outpost_hashtags`:
  `account_filter VARCHAR(255) NOT NULL DEFAULT ''`.
- Add it to the `CREATE TABLE` in `OUTPOST_Activator::create_tables()` so fresh
  installs get it.
- Add `OUTPOST_Activator::maybe_upgrade()`, called on load (via `outpost_init`):
  if `get_option('outpost_db_version')` is below `1.1.0`, re-run `dbDelta` with
  the updated schema (adds the column on existing installs), then
  `update_option('outpost_db_version', '1.1.0')`.
- New global option `outpost_brand_account` (string, full handle). Seeded empty
  in `set_defaults()` and removed in `uninstall.php`.

## Per-hashtag account filter (#9)

- `OUTPOST_Hashtag_Manager`:
  - `add()` and `update()` accept an `account_filter` value.
  - New `normalize_handle( $handle )`: trim, strip a single leading `@`,
    lowercase. Stored normalized.
- Filtering lives in `OUTPOST_Feed_Fetcher::get_posts()`, applied after the
  cached/fresh posts are retrieved and **before** the `limit` slice, so both the
  on-page feed and the digest (`get_posts_since_yesterday()` builds on
  `get_posts()`) respect it.
- **Match rule** for a stored filter `F` against a post `P`:
  - Keep `P` if `F === strtolower(P->account->acct)`.
  - If `F` contains a host part (`user@host`) but `P->account->acct` has no host
    (the account is local to the hashtag's instance), also keep `P` when
    `F === P->account->username . '@' . host(P->account->url)`.
  - Blank `F` keeps all posts (current behavior).
- The raw timeline stays cached unfiltered; the filter is applied per call. The
  account filter is part of the hashtag row, so no cache-key change is needed.

## Brand account feed data layer (#10)

- `OUTPOST_Feed_Fetcher::get_account_posts( $limit = 20, $force = false )`:
  1. Read `outpost_brand_account`. If empty or it has no `@host` part, return
     `[]`.
  2. Parse into `username` and `host`.
  3. Resolve the account id via
     `https://{host}/api/v1/accounts/lookup?acct={username}`, cached in a
     transient keyed on the handle.
  4. Fetch
     `https://{host}/api/v1/accounts/{id}/statuses?limit=40&exclude_replies=true&exclude_reblogs=true`.
  5. Cache the result in a transient for `OUTPOST_Settings::get_cache_duration()`.
     On `WP_Error` or non-200, return stale cache if present, else `[]` — same
     resilience pattern as `get_posts()`.
- Reuses the existing `wp_remote_get` user-agent and null-safe JSON handling.
- Add a brand-feed refresh to the hourly `refresh_all_caches()` so the display
  stays warm.

## Display (#10)

- Extract the post-list markup currently inline in
  `OUTPOST_Shortcodes::render_feed()` into a shared private helper (e.g.
  `render_posts_list( array $posts )`) so the hashtag feed and account feed emit
  the identical, already-accessibility-reviewed card markup. No visual change to
  the existing feed.
- New shortcode `[outpost_account_feed limit="20"]`:
  - Heading is the brand account's display name (falling back to the handle).
  - Renders the shared post list; empty/branding states mirror `render_feed()`.
  - Returns an empty string when no brand account is configured (consistent with
    how the block renders nothing), so an unconfigured feed is invisible on the
    front end rather than showing an error.
- New block `outpost/account-feed`:
  - `block.json` (apiVersion 3), attribute `limit` (number, default 20).
  - Hand-written `editor.js` using `wp.*` globals with a `RangeControl` and
    `ServerSideRender`, plus a hand-written `editor.asset.php` (no build step),
    mirroring `blocks/feed`.
  - `OUTPOST_Blocks::render_account_feed_block()` maps attributes onto
    `[outpost_account_feed]` via `do_shortcode`, mirroring `render_feed_block()`.

## Admin UI (#9)

- Hashtag add/edit forms (`admin/views/hashtags.php`): add an "Account filter
  (optional)" text field with help text describing the `user@instance.social`
  format and that blank means all accounts. Wired through the existing
  `add_hashtag` / `update_hashtag` handlers in `OUTPOST_Admin`.
- Settings (`admin/views/settings.php`): add a "Brand account" field, wired
  through `OUTPOST_Settings::save()` (new allowed key `outpost_brand_account`,
  sanitized via `normalize_handle`). New getter `get_brand_account()`.
- Both admin changes and the new front-end feed get an `accessibility-lead`
  review before merge.

## Testing

Unit tests (PHPUnit + brain/monkey, no WP/DB), mirroring `tests/Unit`:

- `normalize_handle()` cases (leading `@`, casing, whitespace, with/without
  host).
- The account-filter match rule (local acct, remote acct, host-only-on-filter
  case, blank filter).
- `OUTPOST_Blocks::render_account_feed_block()` (empty when no account,
  builds the expected shortcode with the limit, escapes attributes).

## Sequencing

Two issues, two branches/PRs (one issue per PR; #10 depends on #9):

- **PR 1 — #9:** migration + `maybe_upgrade()`, `account_filter` column,
  hashtag manager + filter logic, `outpost_brand_account` option + getter,
  admin fields, uninstall cleanup, unit tests.
- **PR 2 — #10:** `get_account_posts()`, shared `render_posts_list()` helper,
  `[outpost_account_feed]` shortcode, `outpost/account-feed` block, hourly
  refresh, unit tests, README display docs.

## Out of scope (YAGNI)

- No separate instance field for the brand account (derived from the handle).
- No `showSubscribe` on the account feed; the account feed takes only `limit`.
- No new admin UI for choosing which instance to query the brand account on.

## Acceptance criteria

From #9:
- [ ] Each hashtag config has an optional account-handle field in the admin.
- [ ] Hashtag feeds (and digests) respect the account filter when set.
- [ ] A global brand-account handle can be saved in settings.
- [ ] Blank fields preserve the current any-account behavior.

From #10:
- [ ] `get_account_posts()` fetches and caches the brand account's posts.
- [ ] Falls back gracefully (empty array) when no brand account is configured or
      the lookup fails.
- [ ] The brand feed is displayable via `[outpost_account_feed]` and the
      `outpost/account-feed` block.

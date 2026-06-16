# Source Account Support Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let a site filter a hashtag feed to a single account (#9) and display all of a configured global brand account's own posts (#10).

**Architecture:** Additive to the existing static-class plugin. #9 adds an `account_filter` column + a global `outpost_brand_account` option, with filtering applied in `OUTPOST_Feed_Fetcher::get_posts()`. #10 adds `get_account_posts()` plus a new `[outpost_account_feed]` shortcode and `outpost/account-feed` block that reuse the existing post-card markup via a shared render helper.

**Tech Stack:** WordPress 7+, PHP 8.3, `$wpdb` + `dbDelta`, `wp_remote_get`, WP-Cron, shortcodes + dynamic blocks (no JS build), PHPUnit 10 + Brain Monkey for isolated unit tests.

**Reference spec:** `docs/superpowers/specs/2026-06-15-source-account-design.md`

**Conventions:** tabs for indentation; `OUTPOST_` class prefix; text domain `outpost`; unit tests live in `tests/Unit/` and extend `Outpost\Tests\TestCase`, mocking WP functions with `Brain\Monkey\Functions`.

---

## PART 1 — Issue #9 (branch: `feat/issue-9-source-account`)

Branch from a clean `main`. PR closes #9.

### Task 1: `normalize_handle()` on the hashtag manager

**Files:**
- Modify: `includes/class-outpost-hashtag-manager.php`
- Test: `tests/Unit/HandleNormalizationTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/HandleNormalizationTest.php`:

```php
<?php

namespace Outpost\Tests\Unit;

use Outpost\Tests\TestCase;
use OUTPOST_Hashtag_Manager;

class HandleNormalizationTest extends TestCase {

	public function test_strips_leading_at_and_lowercases(): void {
		$this->assertSame( 'news@example.social', OUTPOST_Hashtag_Manager::normalize_handle( '@News@Example.Social' ) );
	}

	public function test_trims_whitespace(): void {
		$this->assertSame( 'alice', OUTPOST_Hashtag_Manager::normalize_handle( '  alice  ' ) );
	}

	public function test_empty_string_stays_empty(): void {
		$this->assertSame( '', OUTPOST_Hashtag_Manager::normalize_handle( '' ) );
	}
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit --filter HandleNormalizationTest`
Expected: FAIL (`Call to undefined method ...::normalize_handle()`).

- [ ] **Step 3: Add the method**

In `includes/class-outpost-hashtag-manager.php`, after `normalize_instance()`, add:

```php
	/**
	 * Normalize a Mastodon account handle: trim, strip one leading @, lowercase.
	 *
	 * @param string $handle
	 * @return string
	 */
	public static function normalize_handle( $handle ) {
		return strtolower( ltrim( trim( (string) $handle ), '@' ) );
	}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit --filter HandleNormalizationTest`
Expected: PASS (3 tests).

- [ ] **Step 5: Commit**

```bash
git add tests/Unit/HandleNormalizationTest.php includes/class-outpost-hashtag-manager.php
git commit -m "feat: add normalize_handle() helper (#9)"
```

### Task 2: account-filter match rule

**Files:**
- Modify: `includes/class-outpost-hashtag-manager.php`
- Test: `tests/Unit/AccountFilterMatchTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/AccountFilterMatchTest.php`:

```php
<?php

namespace Outpost\Tests\Unit;

use Brain\Monkey\Functions;
use Outpost\Tests\TestCase;
use OUTPOST_Hashtag_Manager;

class AccountFilterMatchTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		// Real WP wp_parse_url() behavior for the host case.
		Functions\when( 'wp_parse_url' )->alias( function ( $url, $component ) {
			return parse_url( $url, $component );
		} );
	}

	public function test_blank_filter_matches_everything(): void {
		$this->assertTrue( OUTPOST_Hashtag_Manager::post_matches_filter( '', 'anyone', 'https://x.social/@anyone' ) );
	}

	public function test_exact_acct_match(): void {
		$this->assertTrue( OUTPOST_Hashtag_Manager::post_matches_filter( 'news@example.social', 'news@example.social', 'https://example.social/@news' ) );
	}

	public function test_non_match(): void {
		$this->assertFalse( OUTPOST_Hashtag_Manager::post_matches_filter( 'news@example.social', 'someoneelse', 'https://example.social/@someoneelse' ) );
	}

	public function test_local_acct_with_host_only_on_filter(): void {
		// Post is local to the hashtag instance, so acct has no host; filter does.
		$this->assertTrue( OUTPOST_Hashtag_Manager::post_matches_filter( 'alice@example.social', 'alice', 'https://example.social/@alice' ) );
	}

	public function test_local_acct_wrong_host_does_not_match(): void {
		$this->assertFalse( OUTPOST_Hashtag_Manager::post_matches_filter( 'alice@other.social', 'alice', 'https://example.social/@alice' ) );
	}
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit --filter AccountFilterMatchTest`
Expected: FAIL (`undefined method post_matches_filter`).

- [ ] **Step 3: Add the method**

In `includes/class-outpost-hashtag-manager.php`, after `normalize_handle()`, add:

```php
	/**
	 * Whether a post matches a stored account filter.
	 *
	 * @param string $filter      Stored filter (any casing; may be blank).
	 * @param string $acct        Post's account->acct ("user" if local to the
	 *                             hashtag instance, "user@host" if remote).
	 * @param string $account_url Post's account->url (used to derive the host
	 *                             when the acct is local).
	 * @return bool
	 */
	public static function post_matches_filter( $filter, $acct, $account_url ) {
		$filter = self::normalize_handle( $filter );
		if ( '' === $filter ) {
			return true;
		}

		$acct = strtolower( (string) $acct );
		if ( $filter === $acct ) {
			return true;
		}

		// Filter carries a host but the post is local to the hashtag instance
		// (acct has no host): compare against username@<host of account url>.
		if ( false !== strpos( $filter, '@' ) && false === strpos( $acct, '@' ) ) {
			$host = strtolower( (string) wp_parse_url( $account_url, PHP_URL_HOST ) );
			if ( $host && $filter === $acct . '@' . $host ) {
				return true;
			}
		}

		return false;
	}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit --filter AccountFilterMatchTest`
Expected: PASS (5 tests).

- [ ] **Step 5: Commit**

```bash
git add tests/Unit/AccountFilterMatchTest.php includes/class-outpost-hashtag-manager.php
git commit -m "feat: add account-filter match rule (#9)"
```

### Task 3: DB column + migration

**Files:**
- Modify: `includes/class-outpost-activator.php`
- Modify: `outpost.php` (call the upgrade on load)

No unit test (dbDelta needs WordPress + DB); verified manually in the PR.

- [ ] **Step 1: Add the column to the create statement**

In `create_tables()`, in the `$sql_hashtags` heredoc, add the column after `label`:

```php
				label         VARCHAR(255)        NOT NULL DEFAULT '',
				account_filter VARCHAR(255)       NOT NULL DEFAULT '',
```

- [ ] **Step 2: Bump the version written by create_tables**

In `create_tables()`, change the final line to:

```php
		update_option( 'outpost_db_version', '1.1.0' );
```

- [ ] **Step 3: Add `maybe_upgrade()`**

In `includes/class-outpost-activator.php`, add a public method:

```php
	/**
	 * Run schema upgrades for already-installed sites. Idempotent.
	 */
	public static function maybe_upgrade() {
		$installed = get_option( 'outpost_db_version' );
		if ( $installed && version_compare( $installed, '1.1.0', '>=' ) ) {
			return;
		}
		// create_tables() runs dbDelta with the current schema, which adds any
		// missing columns (e.g. account_filter) on existing installs, and writes
		// the new db version.
		self::create_tables();
	}
```

- [ ] **Step 4: Call it on load**

In `outpost.php`, inside `outpost_init()`, immediately after the `load_plugin_textdomain(...)` line, add:

```php
	// Run any pending schema upgrades for already-installed sites.
	OUTPOST_Activator::maybe_upgrade();
```

- [ ] **Step 5: Lint and commit**

```bash
php -l includes/class-outpost-activator.php && php -l outpost.php
git add includes/class-outpost-activator.php outpost.php
git commit -m "feat: add account_filter column and 1.1.0 migration (#9)"
```

### Task 4: hashtag manager stores `account_filter`

**Files:**
- Modify: `includes/class-outpost-hashtag-manager.php`

No new unit test (DB writes); covered by manual PR verification.

- [ ] **Step 1: Accept it in `add()`**

Change the `add()` signature and insert. New signature:

```php
	public static function add( $hashtag, $instance_url, $label = '', $account_filter = '' ) {
```

In the `$wpdb->insert()` call, add the column to the data array and format array:

```php
			[
				'hashtag'        => $hashtag,
				'instance_url'   => $instance_url,
				'label'          => sanitize_text_field( $label ?: '#' . $hashtag ),
				'account_filter' => self::normalize_handle( $account_filter ),
				'active'         => 1,
			],
			[ '%s', '%s', '%s', '%s', '%d' ]
```

- [ ] **Step 2: Accept it in `update()`**

In `update()`, after the `label` block, add:

```php
		if ( isset( $data['account_filter'] ) ) {
			$allowed['account_filter'] = self::normalize_handle( $data['account_filter'] );
		}
```

- [ ] **Step 3: Lint and commit**

```bash
php -l includes/class-outpost-hashtag-manager.php
git add includes/class-outpost-hashtag-manager.php
git commit -m "feat: persist account_filter on hashtag add/update (#9)"
```

### Task 5: apply the filter in `get_posts()`

**Files:**
- Modify: `includes/class-outpost-feed-fetcher.php`

- [ ] **Step 1: Filter after retrieval, before the limit slice**

In `OUTPOST_Feed_Fetcher::get_posts()`, replace the body so the cached/fresh
posts are filtered before slicing. The current method returns
`array_slice( $posts, 0, $limit )` in several branches; centralize by computing
`$posts` first, then filtering, then slicing once. Replace the method with:

```php
	public static function get_posts( $hashtag_id, $limit = 20, $force = false ) {
		$hashtag_row = OUTPOST_Hashtag_Manager::get( $hashtag_id );
		if ( ! $hashtag_row ) {
			return [];
		}

		$cache_key = 'outpost_feed_' . $hashtag_id;
		$posts     = false;

		if ( ! $force ) {
			$cached = get_transient( $cache_key );
			if ( $cached !== false ) {
				$posts = $cached;
			}
		}

		if ( $posts === false ) {
			$fetched = self::fetch_from_api( $hashtag_row );
			if ( ! is_wp_error( $fetched ) ) {
				set_transient( $cache_key, $fetched, OUTPOST_Settings::get_cache_duration() );
				$posts = $fetched;
			} else {
				// On error, fall back to stale cache if available.
				$stale = get_transient( $cache_key );
				$posts = $stale ? $stale : [];
			}
		}

		$posts = self::apply_account_filter( $posts, $hashtag_row );

		return array_slice( $posts, 0, $limit );
	}

	/**
	 * Restrict posts to the hashtag's account_filter when one is set.
	 *
	 * @param array  $posts
	 * @param object $hashtag_row
	 * @return array
	 */
	private static function apply_account_filter( $posts, $hashtag_row ) {
		$filter = isset( $hashtag_row->account_filter ) ? $hashtag_row->account_filter : '';
		if ( '' === $filter ) {
			return $posts;
		}

		$matched = array_filter( $posts, function ( $post ) use ( $filter ) {
			$acct = isset( $post->account->acct ) ? $post->account->acct : '';
			$url  = isset( $post->account->url ) ? $post->account->url : '';
			return OUTPOST_Hashtag_Manager::post_matches_filter( $filter, $acct, $url );
		} );

		return array_values( $matched );
	}
```

- [ ] **Step 2: Lint**

Run: `php -l includes/class-outpost-feed-fetcher.php`
Expected: No syntax errors.

- [ ] **Step 3: Run the full suite (no regressions)**

Run: `vendor/bin/phpunit`
Expected: PASS (existing + new tests).

- [ ] **Step 4: Commit**

```bash
git add includes/class-outpost-feed-fetcher.php
git commit -m "feat: apply hashtag account filter in get_posts (#9)"
```

### Task 6: brand-account option + getter + cleanup

**Files:**
- Modify: `includes/class-outpost-settings.php`
- Modify: `includes/class-outpost-activator.php` (`set_defaults`)
- Modify: `uninstall.php`

- [ ] **Step 1: Default the option**

In `OUTPOST_Activator::set_defaults()`, add to the `$defaults` array:

```php
			'outpost_brand_account'         => '',
```

- [ ] **Step 2: Getter**

In `includes/class-outpost-settings.php`, after `get_branding_url()`, add:

```php
	public static function get_brand_account() {
		return get_option( 'outpost_brand_account', '' );
	}
```

- [ ] **Step 3: Allow saving it**

In `OUTPOST_Settings::save()`, add `'outpost_brand_account'` to `$allowed_keys`,
and add a case to the switch (before `default`):

```php
				case 'outpost_brand_account':
					update_option( $key, OUTPOST_Hashtag_Manager::normalize_handle( $data[ $key ] ) );
					break;
```

- [ ] **Step 4: Remove on uninstall**

In `uninstall.php`, add `'outpost_brand_account'` to the `$options` array.

- [ ] **Step 5: Lint and commit**

```bash
php -l includes/class-outpost-settings.php && php -l includes/class-outpost-activator.php && php -l uninstall.php
git add includes/class-outpost-settings.php includes/class-outpost-activator.php uninstall.php
git commit -m "feat: add outpost_brand_account option (#9)"
```

### Task 7: admin fields (UI — needs accessibility-lead review)

**Files:**
- Modify: `admin/views/hashtags.php`
- Modify: `admin/views/settings.php`
- Modify: `admin/class-outpost-admin.php`

- [ ] **Step 1: Hashtag add form field**

In `admin/views/hashtags.php`, in the **add** form table (after the Label row,
before `</table>`), add:

```php
				<tr>
					<th scope="row"><label for="new-account-filter"><?php esc_html_e( 'Account filter', 'outpost' ); ?></label></th>
					<td>
						<input type="text" id="new-account-filter" name="account_filter" value="" class="regular-text" placeholder="user@instance.social" />
						<p class="description"><?php esc_html_e( 'Optional. Only show posts from this account. Leave blank to show all accounts.', 'outpost' ); ?></p>
					</td>
				</tr>
```

- [ ] **Step 2: Hashtag edit form field**

In the **edit** form table (after the Label row), add:

```php
				<tr>
					<th scope="row"><label for="edit-account-filter"><?php esc_html_e( 'Account filter', 'outpost' ); ?></label></th>
					<td>
						<input type="text" id="edit-account-filter" name="account_filter" value="<?php echo esc_attr( $edit_row->account_filter ); ?>" class="regular-text" placeholder="user@instance.social" />
						<p class="description"><?php esc_html_e( 'Optional. Only show posts from this account. Leave blank to show all accounts.', 'outpost' ); ?></p>
					</td>
				</tr>
```

- [ ] **Step 3: Settings brand-account field**

In `admin/views/settings.php`, inside the Branding `<table class="form-table">`
(or a new section just above it), add a row. Place it after the existing
"Subscriptions" table, as its own section:

```php
		<h2><?php esc_html_e( 'Brand account', 'outpost' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="s-brand-account"><?php esc_html_e( 'Mastodon account', 'outpost' ); ?></label></th>
				<td>
					<input type="text" id="s-brand-account" name="brand_account" value="<?php echo esc_attr( OUTPOST_Settings::get_brand_account() ); ?>" class="regular-text" placeholder="user@instance.social" />
					<p class="description"><?php esc_html_e( 'Optional. Used by the account feed. Format: user@instance.social.', 'outpost' ); ?></p>
				</td>
			</tr>
		</table>
```

- [ ] **Step 4: Wire the handlers**

In `admin/class-outpost-admin.php`, in the `add_hashtag` branch, pass the filter:

```php
				$result   = OUTPOST_Hashtag_Manager::add( $tag, $instance, $label, sanitize_text_field( $_POST['account_filter'] ?? '' ) );
```

In the `update_hashtag` branch, add to the `update()` data array:

```php
					'account_filter' => sanitize_text_field( $_POST['account_filter'] ?? '' ),
```

In the `save_settings` branch, add to the `OUTPOST_Settings::save([...])` array:

```php
					'outpost_brand_account'      => $_POST['brand_account'] ?? '',
```

- [ ] **Step 5: Lint, accessibility review, commit**

```bash
php -l admin/class-outpost-admin.php
```

Then run an `accessibility-lead` review of the three admin view/handler changes
(new form fields: label association, help-text wiring). Apply any required fixes.

```bash
git add admin/views/hashtags.php admin/views/settings.php admin/class-outpost-admin.php
git commit -m "feat: admin fields for account filter and brand account (#9)"
```

### Task 8: open PR 1

- [ ] **Step 1: Full suite + lint sweep**

Run: `vendor/bin/phpunit` (PASS) and `php -l` on every modified PHP file.

- [ ] **Step 2: Push and open the PR**

```bash
git push -u origin feat/issue-9-source-account
gh pr create --base main --title "feat: source account config + per-hashtag filter (closes #9)" --body "Implements #9 per docs/superpowers/specs/2026-06-15-source-account-design.md. Closes #9"
```

Manual verification notes for the PR body: activate on an existing install and
confirm the `account_filter` column is added (db_version 1.1.0); set a filter on
a hashtag and confirm the feed/digest only show that account; save a brand
account in Settings and confirm it persists normalized.

---

## PART 2 — Issue #10 (branch: `feat/issue-10-account-feed`)

Branch from `main` **after PR 1 is merged** (needs the brand-account option and
`normalize_handle`). PR closes #10.

### Task 9: extract shared `render_posts_list()` helper

**Files:**
- Modify: `includes/class-outpost-shortcodes.php`

Refactor only — the existing feed output must be byte-for-byte identical.

- [ ] **Step 1: Add the helper**

In `OUTPOST_Shortcodes`, add a private method containing the exact `<ul>` block
currently inside `render_feed()` (the `outpost-feed__list` list and its
`foreach`). The helper returns the markup string:

```php
	/**
	 * Render the shared post-card list markup used by the hashtag and account feeds.
	 *
	 * @param array $posts
	 * @return string
	 */
	private static function render_posts_list( $posts ) {
		ob_start();
		?>
		<ul class="outpost-feed__list" role="list">
			<?php foreach ( $posts as $post ) :
				$text    = OUTPOST_Feed_Fetcher::post_to_plain_text( $post->content );
				$date    = OUTPOST_Feed_Fetcher::format_date( $post->created_at );
				$url     = esc_url( $post->url );
				$account = isset( $post->account->acct ) ? '@' . esc_html( $post->account->acct ) : '';
			?>
			<li class="outpost-feed__item">
				<article class="outpost-post">
					<?php if ( $account ) : ?>
					<h3 class="outpost-post__heading">
						<?php printf( esc_html__( 'Post by %s', 'outpost' ), $account ); ?>
					</h3>
					<?php endif; ?>

					<div class="outpost-post__content">
						<?php echo wp_kses_post( wpautop( esc_html( $text ) ) ); ?>
					</div>

					<footer class="outpost-post__footer">
						<?php if ( $date ) : ?>
						<time class="outpost-post__date" datetime="<?php echo esc_attr( $post->created_at ); ?>">
							<?php echo esc_html( $date ); ?>
						</time>
						<?php endif; ?>
						<a class="outpost-post__link" href="<?php echo $url; ?>" target="_blank" rel="noopener noreferrer">
							<?php esc_html_e( 'View on Mastodon', 'outpost' ); ?>
							<span class="screen-reader-text"><?php esc_html_e( '(opens in new tab)', 'outpost' ); ?></span>
						</a>
					</footer>
				</article>
			</li>
			<?php endforeach; ?>
		</ul>
		<?php
		return ob_get_clean();
	}
```

- [ ] **Step 2: Use it in `render_feed()`**

In `render_feed()`, replace the inline `<ul class="outpost-feed__list">...</ul>`
block (inside the `else`) with:

```php
				<?php else : ?>
					<?php echo self::render_posts_list( $posts ); ?>
				<?php endif; ?>
```

- [ ] **Step 3: Lint and verify no test regressions**

Run: `php -l includes/class-outpost-shortcodes.php && vendor/bin/phpunit`
Expected: No syntax errors; tests PASS.

- [ ] **Step 4: Commit**

```bash
git add includes/class-outpost-shortcodes.php
git commit -m "refactor: extract shared render_posts_list() helper (#10)"
```

### Task 10: `get_account_posts()`

**Files:**
- Modify: `includes/class-outpost-feed-fetcher.php`
- Test: `tests/Unit/AccountPostsTest.php`

- [ ] **Step 1: Write the failing test (empty fallbacks)**

Create `tests/Unit/AccountPostsTest.php`:

```php
<?php

namespace Outpost\Tests\Unit;

use Brain\Monkey\Functions;
use Outpost\Tests\TestCase;
use OUTPOST_Feed_Fetcher;

class AccountPostsTest extends TestCase {

	public function test_returns_empty_when_no_brand_account(): void {
		Functions\when( 'get_option' )->justReturn( '' );
		$this->assertSame( [], OUTPOST_Feed_Fetcher::get_account_posts() );
	}

	public function test_returns_empty_when_handle_has_no_host(): void {
		Functions\when( 'get_option' )->justReturn( 'aliceonly' );
		$this->assertSame( [], OUTPOST_Feed_Fetcher::get_account_posts() );
	}
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit --filter AccountPostsTest`
Expected: FAIL (`undefined method get_account_posts`).

- [ ] **Step 3: Implement `get_account_posts()`**

In `includes/class-outpost-feed-fetcher.php`, add to the Public API section:

```php
	/**
	 * Get the configured brand account's own posts (original posts only).
	 *
	 * @param int  $limit
	 * @param bool $force
	 * @return array Post objects, empty array on failure / no account.
	 */
	public static function get_account_posts( $limit = 20, $force = false ) {
		$handle = OUTPOST_Settings::get_brand_account();
		if ( '' === $handle || false === strpos( $handle, '@' ) ) {
			return [];
		}

		list( $username, $host ) = explode( '@', $handle, 2 );
		if ( '' === $username || '' === $host ) {
			return [];
		}

		$cache_key = 'outpost_account_feed';

		if ( ! $force ) {
			$cached = get_transient( $cache_key );
			if ( $cached !== false ) {
				return array_slice( $cached, 0, $limit );
			}
		}

		$account_id = self::resolve_account_id( $host, $username );
		if ( ! $account_id ) {
			$stale = get_transient( $cache_key );
			return $stale ? array_slice( $stale, 0, $limit ) : [];
		}

		$url = 'https://' . $host . '/api/v1/accounts/' . rawurlencode( $account_id ) . '/statuses';
		$url = add_query_arg(
			[
				'limit'           => 40,
				'exclude_replies' => 'true',
				'exclude_reblogs' => 'true',
			],
			$url
		);

		$posts = self::request_json( $url );
		if ( is_wp_error( $posts ) || ! is_array( $posts ) ) {
			$stale = get_transient( $cache_key );
			return $stale ? array_slice( $stale, 0, $limit ) : [];
		}

		set_transient( $cache_key, $posts, OUTPOST_Settings::get_cache_duration() );
		return array_slice( $posts, 0, $limit );
	}

	/**
	 * Resolve and cache a Mastodon account id from its host + username.
	 *
	 * @param string $host
	 * @param string $username
	 * @return string|false
	 */
	private static function resolve_account_id( $host, $username ) {
		$key    = 'outpost_account_id_' . md5( $host . '|' . $username );
		$cached = get_transient( $key );
		if ( $cached !== false ) {
			return $cached;
		}

		$url     = 'https://' . $host . '/api/v1/accounts/lookup?acct=' . rawurlencode( $username );
		$account = self::request_json( $url );
		if ( is_wp_error( $account ) || ! isset( $account->id ) ) {
			return false;
		}

		set_transient( $key, $account->id, DAY_IN_SECONDS );
		return $account->id;
	}

	/**
	 * Shared JSON GET against the Mastodon API.
	 *
	 * @param string $url
	 * @return mixed|WP_Error Decoded JSON, or WP_Error on failure.
	 */
	private static function request_json( $url ) {
		$response = wp_remote_get( $url, [
			'timeout'    => 15,
			'user-agent' => 'MastodonHashtagDigest/' . OUTPOST_VERSION . ' WordPress/' . get_bloginfo( 'version' ),
		] );

		if ( is_wp_error( $response ) ) {
			return $response;
		}
		if ( wp_remote_retrieve_response_code( $response ) !== 200 ) {
			return new WP_Error( 'api_error', 'Non-200 from ' . $url );
		}

		$decoded = json_decode( wp_remote_retrieve_body( $response ) );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return new WP_Error( 'json_error', 'Invalid JSON from ' . $url );
		}
		return $decoded;
	}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit --filter AccountPostsTest`
Expected: PASS (2 tests). (Both early-return paths only call `get_option`, which is mocked.)

- [ ] **Step 5: Commit**

```bash
git add tests/Unit/AccountPostsTest.php includes/class-outpost-feed-fetcher.php
git commit -m "feat: add get_account_posts() brand feed fetcher (#10)"
```

### Task 11: `[outpost_account_feed]` shortcode

**Files:**
- Modify: `includes/class-outpost-shortcodes.php`

- [ ] **Step 1: Register the shortcode**

In `OUTPOST_Shortcodes::init()`, add:

```php
		add_shortcode( 'outpost_account_feed', [ __CLASS__, 'render_account_feed' ] );
```

- [ ] **Step 2: Add the render method**

In `OUTPOST_Shortcodes`, add:

```php
	/**
	 * [outpost_account_feed limit="20"]
	 */
	public static function render_account_feed( $atts ) {
		$atts = shortcode_atts( [ 'limit' => 20 ], $atts, 'outpost_account_feed' );

		$handle = OUTPOST_Settings::get_brand_account();
		if ( '' === $handle ) {
			return '';
		}

		$posts    = OUTPOST_Feed_Fetcher::get_account_posts( (int) $atts['limit'] );
		$branding = OUTPOST_Settings::get_branding_html();
		$heading  = '@' . $handle;
		$heading_id = wp_unique_id( 'outpost-account-heading-' );

		ob_start();
		?>
		<section class="outpost-feed" aria-labelledby="<?php echo esc_attr( $heading_id ); ?>">
			<h2 class="outpost-feed__heading" id="<?php echo esc_attr( $heading_id ); ?>"><?php echo esc_html( $heading ); ?></h2>

			<?php if ( empty( $posts ) ) : ?>
				<p class="outpost-feed__empty"><?php esc_html_e( 'No posts found yet. Check back soon.', 'outpost' ); ?></p>
			<?php else : ?>
				<?php echo self::render_posts_list( $posts ); ?>
			<?php endif; ?>

			<?php if ( $branding ) : ?>
			<div class="outpost-feed__branding">
				<?php echo $branding; ?>
			</div>
			<?php endif; ?>
		</section>
		<?php
		return ob_get_clean();
	}
```

- [ ] **Step 3: Lint and commit**

```bash
php -l includes/class-outpost-shortcodes.php && vendor/bin/phpunit
git add includes/class-outpost-shortcodes.php
git commit -m "feat: add [outpost_account_feed] shortcode (#10)"
```

### Task 12: `outpost/account-feed` block

**Files:**
- Create: `blocks/account-feed/block.json`
- Create: `blocks/account-feed/editor.js`
- Create: `blocks/account-feed/editor.asset.php`
- Modify: `includes/class-outpost-blocks.php`
- Test: `tests/Unit/AccountFeedBlockTest.php`

- [ ] **Step 1: Write the failing render-callback test**

Create `tests/Unit/AccountFeedBlockTest.php`:

```php
<?php

namespace Outpost\Tests\Unit;

use Brain\Monkey\Functions;
use Outpost\Tests\TestCase;
use OUTPOST_Blocks;

class AccountFeedBlockTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'absint' )->alias( function ( $v ) { return abs( (int) $v ); } );
	}

	public function test_builds_account_feed_shortcode_with_default_limit(): void {
		Functions\expect( 'do_shortcode' )
			->once()
			->with( '[outpost_account_feed limit="20"]' )
			->andReturn( '<section>account</section>' );

		$this->assertSame( '<section>account</section>', OUTPOST_Blocks::render_account_feed_block( [] ) );
	}

	public function test_builds_account_feed_shortcode_with_custom_limit(): void {
		Functions\expect( 'do_shortcode' )
			->once()
			->with( '[outpost_account_feed limit="5"]' )
			->andReturn( '<section>account</section>' );

		$this->assertSame( '<section>account</section>', OUTPOST_Blocks::render_account_feed_block( [ 'limit' => 5 ] ) );
	}
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit --filter AccountFeedBlockTest`
Expected: FAIL (`undefined method render_account_feed_block`).

- [ ] **Step 3: Add the render callback + registration**

In `includes/class-outpost-blocks.php`, in `register_blocks()`, add after the
feed block registration:

```php
		register_block_type( OUTPOST_PLUGIN_DIR . 'blocks/account-feed', [
			'render_callback' => [ __CLASS__, 'render_account_feed_block' ],
		] );
```

Add the method:

```php
	/**
	 * Render callback for the outpost/account-feed block.
	 *
	 * @param array $attributes Block attributes (limit).
	 * @return string
	 */
	public static function render_account_feed_block( $attributes ) {
		$limit = isset( $attributes['limit'] ) ? absint( $attributes['limit'] ) : 20;
		return do_shortcode( sprintf( '[outpost_account_feed limit="%d"]', $limit ) );
	}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit --filter AccountFeedBlockTest`
Expected: PASS (2 tests).

- [ ] **Step 5: Create `blocks/account-feed/block.json`**

```json
{
    "$schema": "https://schemas.wp.org/trunk/block.json",
    "apiVersion": 3,
    "name": "outpost/account-feed",
    "title": "Mastodon Account Feed",
    "category": "widgets",
    "icon": "rss",
    "description": "Display recent posts from the configured brand Mastodon account.",
    "textdomain": "outpost",
    "attributes": {
        "limit": {
            "type": "number",
            "default": 20
        }
    },
    "supports": {
        "html": false
    },
    "editorScript": "file:./editor.js"
}
```

- [ ] **Step 6: Create `blocks/account-feed/editor.asset.php`**

```php
<?php
/**
 * Script dependencies for blocks/account-feed/editor.js (no JS build step).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return [
	'dependencies' => [
		'wp-blocks',
		'wp-element',
		'wp-block-editor',
		'wp-components',
		'wp-server-side-render',
		'wp-i18n',
	],
	'version' => OUTPOST_VERSION,
];
```

- [ ] **Step 7: Create `blocks/account-feed/editor.js`**

```js
( function ( blocks, element, blockEditor, components, ServerSideRender, i18n ) {
	var el = element.createElement;
	var Fragment = element.Fragment;
	var __ = i18n.__;
	var useBlockProps = blockEditor.useBlockProps;
	var InspectorControls = blockEditor.InspectorControls;
	var PanelBody = components.PanelBody;
	var RangeControl = components.RangeControl;

	blocks.registerBlockType( 'outpost/account-feed', {
		edit: function ( props ) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;
			var blockProps = useBlockProps();

			return el(
				Fragment,
				{},
				el(
					InspectorControls,
					{},
					el(
						PanelBody,
						{ title: __( 'Account feed settings', 'outpost' ) },
						el( RangeControl, {
							label: __( 'Number of posts', 'outpost' ),
							value: attributes.limit,
							onChange: function ( value ) {
								setAttributes( { limit: value } );
							},
							min: 1,
							max: 50,
						} )
					)
				),
				el(
					'div',
					blockProps,
					el( ServerSideRender, {
						block: 'outpost/account-feed',
						attributes: attributes,
					} )
				)
			);
		},
		save: function () {
			return null;
		},
	} );
} )(
	window.wp.blocks,
	window.wp.element,
	window.wp.blockEditor,
	window.wp.components,
	window.wp.serverSideRender,
	window.wp.i18n
);
```

- [ ] **Step 8: Lint, JS check, commit**

```bash
php -l includes/class-outpost-blocks.php && php -l blocks/account-feed/editor.asset.php
node --check blocks/account-feed/editor.js
git add includes/class-outpost-blocks.php blocks/account-feed/ tests/Unit/AccountFeedBlockTest.php
git commit -m "feat: add outpost/account-feed block (#10)"
```

### Task 13: warm the brand feed on the hourly cron

**Files:**
- Modify: `includes/class-outpost-feed-fetcher.php`

- [ ] **Step 1: Refresh the brand feed alongside hashtag caches**

In `refresh_all_caches()`, after the `foreach` over hashtags, add:

```php
		// Keep the brand-account feed warm too.
		self::get_account_posts( 40, true );
```

- [ ] **Step 2: Lint and commit**

```bash
php -l includes/class-outpost-feed-fetcher.php
git add includes/class-outpost-feed-fetcher.php
git commit -m "feat: refresh brand account feed on hourly cron (#10)"
```

### Task 14: README docs

**Files:**
- Modify: `README.md`

- [ ] **Step 1: Document the account feed**

Add a short subsection under the display docs describing `[outpost_account_feed limit="20"]`, the `outpost/account-feed` block, and that both require a Brand account set in Settings. Mirror the wording/structure of the existing `[outpost_feed]` docs.

- [ ] **Step 2: Commit**

```bash
git add README.md
git commit -m "docs: document the account feed shortcode and block (#10)"
```

### Task 15: accessibility review + open PR 2

- [ ] **Step 1: Accessibility review**

Run an `accessibility-lead` review of the new front-end feed: the
`render_account_feed` section markup (heading/section wiring, the shared post
list it reuses, empty/branding states). Apply any required fixes.

- [ ] **Step 2: Full suite + lint sweep**

Run: `vendor/bin/phpunit` (PASS) and `php -l` on every modified PHP file; `node --check` the new editor.js.

- [ ] **Step 3: Push and open the PR**

```bash
git push -u origin feat/issue-10-account-feed
gh pr create --base main --title "feat: brand account feed shortcode + block (closes #10)" --body "Implements #10 per docs/superpowers/specs/2026-06-15-source-account-design.md. Depends on #9. Closes #10"
```

Manual verification for the PR body: set a brand account in Settings; add
`[outpost_account_feed]` to a page and confirm it shows that account's original
posts (no replies/boosts); add the block in the editor and confirm the live
preview; clear the brand account and confirm the feed renders nothing.

---

## Notes for the implementer

- Each PR gets squash-merged only after Michael verifies on device/production.
- Run `accessibility-lead` before merging any PR that touches admin views or
  front-end output (Tasks 7 and 15).
- Keep commits atomic as written; do not bundle tasks.
- The unit suite never touches WordPress or a DB; anything requiring real WP
  (migration, admin forms, live API) is verified manually in the PR.

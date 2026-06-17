=== OutPost ===
Contributors: communityaccess
Tags: mastodon, fediverse, email digest, accessibility, hashtag
Requires at least: 7.0
Tested up to: 7.0
Requires PHP: 8.2
Stable tag: 1.1.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Display accessible Mastodon hashtag (and account) feeds on your site and send daily email digests to subscribers.

== Description ==

OutPost bridges your Mastodon content to your WordPress site and your email
subscribers. It is built for accessibility and designed for nonprofits,
community groups, and individuals who want to share Mastodon content with their
audience.

* Follow one or more Mastodon hashtags from any instance.
* Optionally restrict a hashtag feed to a single account.
* Display a configured brand account's own posts.
* Show feeds via shortcodes, blocks, or a sidebar widget.
* Let visitors subscribe to a daily email digest per hashtag, with double
  opt-in and one-click-style unsubscribe.
* Customizable branding line on all feeds and emails.
* Screen-reader friendly HTML throughout.

Email is sent through WordPress's `wp_mail()`, so Postmark, Mailgun, SMTP, or
any mailer plugin works automatically. No Mastodon API key is required for
public timelines.

== Installation ==

1. Upload the `outpost` folder to `/wp-content/plugins/`.
2. Activate OutPost through the Plugins screen. You will be sent to the setup
   wizard on first activation.
3. In the wizard, set your Mastodon instance, hashtags, and email sender.
4. Add `[outpost_feed tag="yourtag"]` (or the Mastodon Hashtag Feed block) to a
   page, and `[outpost_subscribe tag="yourtag"]` for a subscribe form.

== Frequently Asked Questions ==

= Does this require a Mastodon API key? =

No. Public hashtag and account timelines are accessible without authentication.

= Can I follow hashtags from different instances? =

Yes. Each hashtag has its own instance URL.

= How do I show a single account's posts? =

Set a Brand account (in the form `user@instance.social`) in Settings, then use
`[outpost_account_feed]` or the Mastodon Account Feed block.

= How does the cache work? =

Posts are fetched from the Mastodon API and cached for 60 minutes by default
(configurable). The daily digest fetches fresh posts.

== Changelog ==

= 1.1.0 =
* New: per-hashtag account filter and a global brand account.
* New: `[outpost_account_feed]` shortcode and Mastodon Account Feed block.
* New: redirect to the setup wizard on first activation.
* Security: abuse controls (honeypot + rate limit) on the subscribe endpoint;
  the subscription lookup now emails management links instead of exposing
  tokens; unsubscribe requires a POST confirmation so link prefetch cannot
  trigger it.
* Fix: saving settings no longer re-shows the setup wizard or re-runs activation.
* Accessibility: localized email `lang`, higher-contrast email and link colors,
  and unique element IDs for repeated feeds/forms.
* Performance: paginated the subscribers admin screen; explicit option autoload.
* Tooling: PHPCS (WordPress-Extra) + PHPCompatibility, CI, and a contributing
  guide. Minimum PHP is now 8.2.

= 1.0.0 =
* Initial release: Mastodon hashtag feeds, subscribe forms, daily email digests,
  subscriber management, branding, and a setup wizard.

== Upgrade Notice ==

= 1.1.0 =
Adds account feeds and hardens security and accessibility. Requires PHP 8.2.

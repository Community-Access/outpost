# OutPost — Product Requirements Document
**Version:** 1.0.0
**Status:** Draft
**Author:** Community Access
**License:** GPL-2.0-or-later
**Last Updated:** June 2026

---

## 1. Overview

### 1.1 Product Name
OutPost

### 1.2 Tagline
Bring your Mastodon content to everyone, not just the people already on Mastodon.

### 1.3 Summary
OutPost is an open source WordPress plugin that bridges the gap between Mastodon's federated social network and the broader public. It pulls posts from one or more Mastodon hashtags, displays them on a WordPress site as accessible feeds, and sends daily email digests to opted-in subscribers. Each hashtag has its own feed, widget, subscriber list, and digest. The plugin is designed to be installed and used by nonprofits, community organizations, bloggers, and individuals with no special technical setup beyond a working WordPress site and an outbound email service.

### 1.4 Problem Statement
Mastodon content is siloed. People who are not on Mastodon cannot easily follow hashtags, receive updates, or engage with the community posting under those tags. Organizations that use Mastodon as their primary social platform have no built-in way to reach audiences who prefer email or who discover them through a website. OutPost solves this by making hashtag content portable: viewable on any WordPress page and deliverable to any inbox.

---

## 2. Goals

### 2.1 Primary Goals
- Allow any WordPress site owner to display public Mastodon hashtag posts without requiring visitors to have a Mastodon account.
- Provide a double opt-in email subscription system so visitors can receive daily digests of hashtag posts.
- Support multiple hashtags, each with independent feeds, widgets, and subscriber lists.
- Be fully accessible to screen reader users out of the box, targeting WCAG 2.2 AA compliance.
- Work with any transactional email service connected to WordPress via wp_mail(), with no plugin-specific email configuration required.

### 2.2 Secondary Goals
- Be usable by nonprofits and community organizations who want to credit themselves with customizable branding on all output.
- Be open source and forkable so other organizations can adapt it to their needs.
- Require no Mastodon API key or authentication for public hashtag timelines.

### 2.3 Success Criteria for v1.0.0
- A site administrator can complete setup in under 10 minutes using the setup wizard.
- A visitor can subscribe to a digest in under 60 seconds using only a keyboard and screen reader.
- The daily digest sends only new posts and skips sending if there is nothing new.
- All front-end output passes WCAG 2.2 AA automated checks and manual screen reader testing.

---

## 3. Users

### 3.1 Site Administrator
The person who installs and configures OutPost. May be a nonprofit staff member, a blogger, an accessibility advocate, or a developer. Comfortable with WordPress admin but not necessarily a developer. Uses the setup wizard and admin dashboard to manage hashtags, view subscribers, and adjust settings.

### 3.2 Subscriber
A visitor to the WordPress site who opts in to receive daily email digests. May or may not have a Mastodon account. Uses the front-end subscription form and management page to sign up, confirm, and unsubscribe. Must be able to complete all actions using only a keyboard and screen reader.

### 3.3 Open Source Adopter
A developer or organization that forks or extends OutPost for their own use. Benefits from clean, well-documented code, GPL-2.0-or-later licensing, and a plugin architecture that makes customization straightforward.

---

## 4. Requirements

### 4.1 Environment
- WordPress 7.0 or newer
- PHP 8.0 or newer
- MySQL or MariaDB (standard WordPress database)
- An outbound email service connected to WordPress via wp_mail() (examples: Postmark, Mailgun, SendGrid, SMTP)
- No Mastodon account or API key required

### 4.2 Hashtag Management

**R-HM-01:** The plugin must support one or more Mastodon hashtags configured independently.

**R-HM-02:** Each hashtag must have its own instance URL, allowing hashtags from different Mastodon servers to be tracked simultaneously.

**R-HM-03:** The instance URL must be configurable per hashtag and must default to a reasonable example during setup.

**R-HM-04:** Each hashtag must have a human-readable label used in the admin interface.

**R-HM-05:** Hashtags must be individually activatable and deactivatable without deleting their subscriber data.

**R-HM-06:** Deleting a hashtag must also delete all associated subscribers and digest log records.

**R-HM-07:** The plugin must normalize hashtag input by stripping leading hash symbols, trimming whitespace, and converting to lowercase for consistent storage and API queries.

### 4.3 Feed Display

**R-FD-01:** Each hashtag must have a shortcode that renders its feed on any WordPress page or post: `[outpost_feed tag="tagname"]`.

**R-FD-02:** The shortcode must accept a `limit` parameter to control how many posts are displayed: `[outpost_feed tag="tagname" limit="10"]`.

**R-FD-03:** Each post in the feed must display: post content (HTML stripped to readable text), author handle, post date and time, and a link to the original post on Mastodon.

**R-FD-04:** The feed must use semantic HTML: a `<section>` with an ARIA label, an unordered list with `role="list"`, each post wrapped in an `<article>` element.

**R-FD-05:** All links to Mastodon must open in a new tab with `rel="noopener noreferrer"` and include a screen-reader-only notice that the link opens in a new tab.

**R-FD-06:** The feed must display a meaningful empty state message when no posts are available.

**R-FD-07:** Feed data must be cached using WordPress transients to avoid hitting the Mastodon API on every page load. Default cache duration is 60 minutes, configurable in settings.

**R-FD-08:** The cache must refresh automatically on an hourly WP-Cron schedule.

### 4.4 Widget

**R-WG-01:** Each hashtag must be available as a WordPress sidebar widget instance.

**R-WG-02:** Each widget instance must allow the administrator to select which hashtag to display, set a post limit, set a custom title, and choose whether to show a subscribe form below the feed.

**R-WG-03:** Widget post excerpts must be truncated at 160 characters with an ellipsis for readability in narrow sidebar contexts.

**R-WG-04:** The widget must include an accessible ARIA label identifying which hashtag feed it represents.

### 4.5 Subscription System

**R-SS-01:** Each hashtag must have its own independent subscriber list. A subscriber can be on multiple lists simultaneously.

**R-SS-02:** The subscribe shortcode `[outpost_subscribe tag="tagname"]` must render an opt-in form with an optional name field and a required email field.

**R-SS-03:** The form must include proper ARIA labels, `aria-required` attributes, and autocomplete attributes on all fields.

**R-SS-04:** Form submission must use AJAX so the page does not reload. Success and error messages must be announced to screen readers via an `aria-live` region.

**R-SS-05:** Double opt-in must be enabled by default. After form submission the subscriber receives a confirmation email and remains in pending status until they click the confirmation link.

**R-SS-06:** Double opt-in must be configurable. When disabled, subscribers are immediately confirmed and receive a welcome email instead.

**R-SS-07:** If a previously unsubscribed email address resubscribes, it must be returned to pending status and a new confirmation email sent.

**R-SS-08:** If an already-confirmed email address submits the form again, the plugin must resend the confirmation email without creating a duplicate record.

**R-SS-09:** Confirmation and unsubscribe actions must be handled via URL tokens so subscribers can act without logging in.

**R-SS-10:** All token-based actions must redirect to the subscription management page with a clear status message after processing.

### 4.6 Subscriber Management Page

**R-MP-01:** A shortcode `[outpost_manage_subscriptions]` must render a front-end page where visitors can subscribe to available hashtags and look up their existing subscriptions by email address.

**R-MP-02:** The email lookup must return a list of active subscriptions for that address with an unsubscribe link for each.

**R-MP-03:** The management page must display contextual status messages for confirmed, unsubscribed, and error states using accessible alert roles.

**R-MP-04:** The administrator must be able to designate any WordPress page as the subscription management page in settings. Confirmation and unsubscribe redirects go to this page.

### 4.7 Daily Email Digest

**R-DE-01:** The plugin must send one digest email per hashtag per day to all confirmed subscribers for that hashtag.

**R-DE-02:** The digest must only include posts that have not been sent in a previous digest. Posts are tracked in a digest log table by URI.

**R-DE-03:** If no new posts exist for a hashtag since the last digest, no email is sent for that hashtag that day.

**R-DE-04:** The digest must be triggered by a WordPress WP-Cron event scheduled once daily at a configurable hour.

**R-DE-05:** The digest email must include: a header with the hashtag name and send date, each post with content, author handle, date, and link to original, an unsubscribe link specific to that subscriber, and optionally a branding line.

**R-DE-06:** The digest email must be sent as HTML with inline styles for email client compatibility. Plain text is not required for v1.0.0 but the HTML must be clean and readable in text-only email clients.

**R-DE-07:** All digest email sending must use `wp_mail()` so it routes through whatever email service the site administrator has connected to WordPress.

**R-DE-08:** The administrator must be able to trigger a digest send manually from the admin dashboard for testing purposes.

**R-DE-09:** The maximum number of posts per digest must be configurable in settings. Default is 10.

**R-DE-10:** The digest sender must process subscribers in batches rather than a single loop. Each WP-Cron run must process one batch per hashtag, then schedule a follow-up event for the next batch if subscribers remain. This prevents PHP execution timeout on large lists.

**R-DE-11:** Batch size must be configurable in plugin settings. Default is 50 subscribers per batch. A batch offset must be tracked per hashtag per digest run and cleared when the run completes.

### 4.8 Transactional Emails

**R-TE-01:** The plugin must send a confirmation email when a subscriber opts in under double opt-in mode. The email must contain a confirmation link and a plain-text fallback URL.

**R-TE-02:** The plugin must send a welcome email after a subscription is confirmed, or immediately on subscription when double opt-in is disabled.

**R-TE-03:** All transactional emails must include the configurable From name and From email address.

**R-TE-04:** All transactional emails must include an unsubscribe link where contextually appropriate.

**R-TE-05:** All transactional emails must include the branding line if configured.

### 4.9 Branding

**R-BR-01:** The administrator must be able to configure a branding text string and a branding URL in plugin settings.

**R-BR-02:** When branding text is set, it must appear at the bottom of every feed display, widget feed, and digest email.

**R-BR-03:** When both branding text and branding URL are set, the text must render as a hyperlink to the URL.

**R-BR-04:** When only branding text is set with no URL, it must render as plain text with no link.

**R-BR-05:** When branding text is empty, no branding element must appear anywhere.

**R-BR-06:** Branding text and URL must each be independently editable at any time from plugin settings.

### 4.10 Admin Interface

**R-AI-01:** The plugin must add a top-level admin menu with the following subpages: Dashboard, Hashtags, Subscribers, Settings, and Setup Wizard.

**R-AI-02:** The Dashboard must display a table of all configured hashtags with confirmed subscriber counts, available shortcodes, active/inactive status, and actions for edit, view subscribers, and send digest now.

**R-AI-03:** The Dashboard must display the next scheduled digest send time.

**R-AI-04:** The Hashtags page must allow adding new hashtags, editing existing ones, and deleting them with a confirmation prompt.

**R-AI-05:** The Subscribers page must display all subscribers with filtering by hashtag, showing email, name, hashtag, status, and subscription date.

**R-AI-06:** The Settings page must expose all configurable options: From name, From email, digest send hour, max posts per digest, cache duration, double opt-in toggle, management page selection, branding text, and branding URL.

**R-AI-07:** The Setup Wizard must be shown automatically on first activation via an admin notice. It must collect: Mastodon instance URL, one or more hashtags, From name, From email, branding text, and branding URL.

**R-AI-08:** The Setup Wizard must accept a comma-separated list of hashtags and create a separate database record for each.

**R-AI-09:** All admin forms must use WordPress nonces for CSRF protection.

### 4.11 Database

**R-DB-01:** The plugin must create three custom database tables on activation using `dbDelta()`: `wp_outpost_hashtags`, `wp_outpost_subscribers`, and `wp_outpost_digest_log`.

**R-DB-02:** The hashtags table must store: id, hashtag, instance_url, label, active flag, and created_at timestamp.

**R-DB-03:** The subscribers table must store: id, hashtag_id (foreign key), email, name, status (pending/confirmed/unsubscribed), token, confirmed_at, and created_at.

**R-DB-04:** The digest log table must store: id, hashtag_id, post_uri, and sent_at timestamp. The combination of hashtag_id and post_uri must be unique.

**R-DB-05:** All database queries must use `$wpdb->prepare()` for parameterized queries. No raw user input may be interpolated into SQL strings.

### 4.12 Accessibility

**R-AC-01:** All front-end output must target WCAG 2.2 Level AA compliance.

**R-AC-02:** All interactive elements must be fully operable with keyboard alone.

**R-AC-03:** All form fields must have visible labels associated programmatically with their inputs.

**R-AC-04:** All dynamic content updates (form submission results, lookup results) must be announced to screen readers using `aria-live` regions with `aria-atomic="true"`.

**R-AC-05:** All links that open in a new tab must include a screen-reader-only text notice.

**R-AC-06:** All lists of posts must use `role="list"` to ensure correct announcement in Safari with VoiceOver when CSS list-style is removed.

**R-AC-07:** All images used in the plugin UI must have descriptive alt text or be marked as decorative.

**R-AC-08:** The plugin CSS must include a high contrast media query block (`forced-colors: active`) to ensure usability in Windows High Contrast Mode.

**R-AC-09:** The plugin CSS must include a reduced motion media query block (`prefers-reduced-motion: reduce`) to disable transitions for users who have requested reduced motion.

**R-AC-10:** All admin data tables must include a `<caption>` element for screen reader context.

**R-AC-11:** The `.screen-reader-text` utility class must be included in the plugin's public CSS for visually hidden but screen-reader-accessible text.

---

## 5. Architecture

### 5.1 File Structure
```
outpost/
  outpost.php                        # Main plugin file, bootstrap
  includes/
    class-outpost-activator.php      # Activation, deactivation, DB creation
    class-outpost-settings.php       # Centralized settings getters and setters
    class-outpost-hashtag-manager.php # CRUD for hashtag configurations
    class-outpost-feed-fetcher.php   # Mastodon API requests and caching
    class-outpost-subscriber.php     # Subscription flow, token handling
    class-outpost-email-digest.php   # Daily digest builder and sender
    class-outpost-shortcodes.php     # Shortcode registration and rendering
    class-outpost-widget.php         # WordPress widget class
  admin/
    class-outpost-admin.php          # Admin menu, pages, form handlers
    outpost-admin.css
    views/
      dashboard.php
      hashtags.php
      subscribers.php
      settings.php
      setup-wizard.php
  public/
    class-outpost-public-page.php    # Front-end management page shortcode
    outpost-public.css
    outpost-public.js
  templates/
    email/
      confirmation.php
      welcome.php
      digest.php
  README.md
```

### 5.2 Key Patterns
- Classes are loaded via `spl_autoload_register()` with a map of class names to file paths.
- All settings are read and written through `MHD_Settings` (centralized option access).
- All Mastodon API responses are cached in WordPress transients with a configurable TTL.
- All email is sent via `wp_mail()` with HTML content type headers.
- WP-Cron handles two recurring tasks: hourly feed cache refresh and daily digest send.
- Token-based actions (confirm, unsubscribe) are handled on the `init` hook before any output, followed by a `wp_safe_redirect()`.

### 5.3 Mastodon API
OutPost uses the public Mastodon REST API endpoint for hashtag timelines. No authentication is required for public instance timelines.

Endpoint pattern:
```
GET {instance_url}/api/v1/timelines/tag/{hashtag}?limit=40
```

OutPost does not write to Mastodon. It is read-only.

---

## 6. Shortcode Reference

| Shortcode | Description |
|---|---|
| `[outpost_feed tag="tagname"]` | Display posts for a hashtag |
| `[outpost_feed tag="tagname" limit="10"]` | Display with a post limit |
| `[outpost_subscribe tag="tagname"]` | Subscribe form for one hashtag |
| `[outpost_manage_subscriptions]` | Full subscription management page |

---

## 7. Settings Reference

| Setting | Default | Description |
|---|---|---|
| From name | Site name | Sender name on all outgoing email |
| From email | Admin email | Must be authorized in your email service |
| Digest send hour | 8 | Hour of day (0-23) to send daily digests |
| Max posts per digest | 10 | Maximum posts included in one digest email |
| Feed cache duration | 60 minutes | How long Mastodon API responses are cached |
| Double opt-in | Enabled | Require email confirmation before subscribing |
| Management page | None | WordPress page with `[outpost_manage_subscriptions]` |
| Branding text | Empty | Text shown at bottom of feeds and emails |
| Branding URL | Empty | Link destination for branding text |

---

## 8. Roadmap

The following items are not in scope for v1.0.0 but are identified as candidates for future releases.

### 8.1 Near-term
- Weekly digest option in addition to daily
- Per-subscriber hashtag preferences (subscribe to multiple from one form)
- Admin export of subscriber list to CSV
- Digest preview in the admin before sending
- WP-CLI commands for managing hashtags and triggering digests

### 8.2 Medium-term
- Block editor (Gutenberg) blocks as an alternative to shortcodes
- RSS feed output per hashtag for feed readers
- Webhook support to trigger cache refresh when new posts appear
- Plain text email alternative alongside HTML digest
- Digest scheduling per hashtag (different hashtags at different times)

### 8.3 Long-term
- Support for other ActivityPub-compatible platforms beyond Mastodon (Pixelfed, PeerTube, Misskey)
- Multiple digest frequencies per subscriber (daily, weekly, as-it-happens)
- Public subscriber count display per hashtag
- Integration with native WordPress user accounts for logged-in subscriber management

---

## 9. Non-Functional Requirements

### 9.1 Performance
- Feed display must not make a live API request on page load when a valid cache exists.
- The digest send loop must process subscribers in batches to avoid PHP timeout on large lists. Default batch size is 50 subscribers per WP-Cron run. Batching is configurable in settings.

### 9.2 Security
- All database queries must use prepared statements.
- All admin form submissions must be verified with WordPress nonces.
- All output rendered in HTML must be escaped using appropriate WordPress escaping functions (`esc_html()`, `esc_url()`, `wp_kses_post()`).
- Subscriber tokens must be generated using `random_bytes()` and stored as hex strings.
- Plugin settings must only be writable by users with the `manage_options` capability.

### 9.3 Compatibility
- The plugin must not conflict with common caching plugins (WP Super Cache, W3 Total Cache, WP Rocket).
- The plugin must not load assets on pages where no OutPost shortcode or widget is present.
- The plugin must use the `outpost-` prefix on all option names, hook names, and CSS classes to avoid conflicts with other plugins.

### 9.4 Maintainability
- Each class must have a single responsibility.
- All strings must be wrapped in localization functions (`__()`, `esc_html_e()`) even though translation files are not included in v1.0.0.
- The README must include complete installation, setup, and shortcode documentation.

---

## 10. Open Questions

All open questions from initial planning are now resolved.

1. **Plugin slug:** Resolved. All `mhd_` prefixes renamed to `outpost_` across all files, class names, table names, option names, hook names, shortcodes, CSS classes, and JS variables.

2. **WordPress.org submission:** Resolved. GitHub only for v1.0.0. WordPress.org directory submission is a future roadmap item after private testing.

3. **Minimum PHP version:** Resolved. WordPress 7.0 confirmed released. Minimum set to WordPress 7.0 and PHP 8.0 in plugin header.

4. **Digest batching:** Resolved. Batching built into the digest sender in v1.0.0. The sender processes subscribers in configurable batches with a WP-Cron chain to avoid PHP timeout. Default batch size is 50 subscribers per run. See R-DE-10 and R-DE-11.

5. **Plugin rename:** Resolved. See item 1 above.

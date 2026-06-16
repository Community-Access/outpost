# OutPost

A WordPress plugin that displays Mastodon hashtag feeds on your site and sends daily email digests to subscribers. Built for accessibility. Designed to be used by nonprofits, community groups, and individuals who want to share Mastodon content with their audience.

---

## Features

- Follow one or more Mastodon hashtags from any instance
- Display posts as an accessible feed using a shortcode or sidebar widget
- Let visitors subscribe to a daily email digest per hashtag
- Double opt-in with confirmation and unsubscribe links in every email
- Customizable branding line on all feeds and emails
- Sends email via WordPress's wp_mail(), so Postmark (or any mailer) works automatically
- Front-end subscription management page
- Full admin dashboard with subscriber counts, test send, and digest logs
- Screen-reader friendly HTML throughout

---

## Requirements

- WordPress 7.0 or later
- PHP 8.0 or later
- An outbound email service connected to WordPress (Postmark, Mailgun, SMTP, etc.)
- A publicly accessible Mastodon instance to search (no API key required for public hashtag timelines)

---

## Installation

1. Download or clone this repository.
2. Upload the `outpost` folder to `/wp-content/plugins/`.
3. In WordPress admin, go to Plugins and activate OutPost.
4. You will see a notice at the top of the admin. Click "Run the setup wizard."

---

## Setup wizard

The wizard asks for:

- **Mastodon instance URL** - The server to search. Example: `https://dragonscave.space`
- **Hashtags** - Comma-separated list without the # symbol. Example: `BitsTips, BlindTech`
- **Email sender name and address** - Used as the From field on all outgoing emails
- **Branding text and URL** - Optional. Appears at the bottom of every feed and email.

You can change all of these later in Settings.

---

## Adding hashtags after setup

1. Go to **Hashtag Digest > Hashtags** in the admin.
2. Fill in the hashtag, instance URL, and optional label.
3. Click Add hashtag.

Each hashtag automatically gets its own shortcodes, widget, and subscriber list.

---

## Displaying a feed on a page

### Using the block editor

Add the **Mastodon Hashtag Feed** block to any page or post. In the block
settings panel, choose a hashtag, set how many posts to show, and optionally
turn on "Show subscribe form" to display a subscribe form below the feed.

### Using the shortcode

```
[outpost_feed tag="bitstips"]
```

Optional: set a limit on how many posts to show:

```
[outpost_feed tag="bitstips" limit="10"]
```

---

## Displaying the account feed

This shows recent original posts from a single Mastodon account (not filtered by
hashtag). First set a **Brand account** in Settings, in the form
`user@instance.social`. Then display it either way:

### Using the block editor

Add the **Mastodon Account Feed** block to any page or post and set how many
posts to show.

### Using the shortcode

```
[outpost_account_feed]
```

Optional: set a limit on how many posts to show:

```
[outpost_account_feed limit="10"]
```

If no brand account is configured in Settings, the feed renders nothing.

---

## Displaying a subscribe form

```
[outpost_subscribe tag="bitstips"]
```

This shows a form where visitors enter their name (optional) and email. If double opt-in is enabled (recommended), they receive a confirmation email before being added.

---

## Subscriber management page

1. Create a new WordPress page.
2. Add this shortcode to it:

```
[outpost_manage_subscriptions]
```

3. Go to **Hashtag Digest > Settings** and select that page under "Subscription management page."

Confirmation and unsubscribe links in emails redirect to this page.

---

## Widget

Go to **Appearance > Widgets** (or use the block editor sidebar). Find the **Mastodon Hashtag Feed** widget. Configure:

- Which hashtag to display
- How many posts to show
- Whether to include a subscribe form below the feed
- A custom title

---

## Branding

In **Hashtag Digest > Settings**, fill in:

- **Branding text**: The full text you want to appear. Example: `Brought to you by BITS. Click here to join.`
- **Branding URL**: The link destination. Example: `https://community-access.org`

If you leave the text blank, no branding appears. If you fill in text but leave the URL blank, the text appears as plain text with no link.

---

## Email delivery

This plugin uses WordPress's `wp_mail()` function. It does not send email itself. Connect your email service to WordPress using:

- **Postmark**: Use the Postmark for WordPress plugin. Make sure your From address is an approved Sender Signature in Postmark.
- **Other services**: Any plugin that connects SMTP or a transactional email API will work.

---

## Shortcode reference

| Shortcode | Description |
|---|---|
| `[outpost_feed tag="tagname"]` | Display posts for a hashtag |
| `[outpost_feed tag="tagname" limit="10"]` | Display with a post limit |
| `[outpost_subscribe tag="tagname"]` | Subscribe form for one hashtag |
| `[outpost_manage_subscriptions]` | Full subscription management page |

---

## Sending a test digest

Go to **Hashtag Digest > Dashboard**. Next to each active hashtag, click **Send digest now**. This sends the current feed to all confirmed subscribers for that hashtag.

---

## Frequently asked questions

**Does this require a Mastodon API key?**
No. Public hashtag timelines on Mastodon are accessible without authentication.

**Can I follow hashtags from different Mastodon instances?**
Yes. Each hashtag has its own instance URL setting. You can follow `#BitsTips` on `dragonscave.space` and `#BlindTech` on `mastodon.social` as separate entries.

**How does the cache work?**
Posts are fetched from the Mastodon API and cached in WordPress for 60 minutes by default. You can change the cache duration in Settings. The daily digest always fetches fresh posts.

**What happens if there are no new posts?**
If no posts have been published in the past 24 hours for a hashtag, that digest is skipped for the day. No empty emails are sent.

**Can subscribers manage all their subscriptions in one place?**
Yes. The `[outpost_manage_subscriptions]` page lets visitors enter their email to see all active subscriptions and get unsubscribe links. Each digest email also contains a direct unsubscribe link.

---

## License

GPL-2.0-or-later. See LICENSE.txt.

---

## Credits

Built by Community Access, a program of the American Council of the Blind.

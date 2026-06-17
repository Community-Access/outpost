# Contributing to OutPost

## Local setup

```bash
composer install
```

## Checks

These run in CI on every pull request, and you can run them locally:

```bash
composer lint        # PHPCS (WordPress-Extra ruleset, see phpcs.xml.dist)
composer lint:fix    # PHPCBF, auto-fix what it can
composer test        # PHPUnit (isolated unit tests, no WordPress required)
```

Lint fails the build on **errors**. Warnings are reported but do not fail CI.

The unit tests use Brain Monkey to mock WordPress functions, so they need no
WordPress install or database.

## Plugin Check (manual)

The official [Plugin Check](https://wordpress.org/plugins/plugin-check/) plugin
validates against WordPress.org review guidelines. It runs inside a WordPress
install, so it is not part of CI. Run it locally before a release:

1. Spin up a WordPress environment (e.g. [`wp-env`](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/) or a local site).
2. Install and activate the Plugin Check plugin.
3. Activate OutPost and run **Tools → Plugin Check** against it.
4. Address any errors before tagging a release.

## Coding standards

- PHP follows the `WordPress-Extra` standard (see `phpcs.xml.dist`); the heavy
  documentation/formatting sniffs are relaxed, but all security and correctness
  sniffs are enforced.
- Where a security sniff flags a known-safe pattern (pre-escaped HTML,
  trusted table names, theme-provided markup), use a narrowly-scoped
  `phpcs:ignore` with a `--` reason rather than disabling the sniff globally.
- Minimum PHP is 8.2; `PHPCompatibilityWP` enforces this.

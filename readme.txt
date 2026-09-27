=== Mega Kadence Bridge ===
Contributors: jonjonesai
Tags: kadence, rest-api, codex, ai, automation, woocommerce
Requires at least: 6.0
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.6.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A model-agnostic REST API bridge that lets Codex, Claude, Cursor, local models, or any HTTP client operate your Kadence-powered WordPress site.

== Description ==

Mega Kadence Bridge exposes your WordPress site through a private, authenticated, Kadence-fluent REST API. An authorized AI agent or automation client can read and write Kadence settings, create and edit pages, manage WooCommerce products, and apply brand identity without any model-provider dependency inside the plugin.

It creates a dedicated `mkb-agent` user with a WordPress Application Password, shows a copy-paste .env block in Settings, and exposes a clean REST API under `/wp-json/mega-kadence-bridge/v1/`. Existing installs preserve their historical bridge username. The bridge does not call a model provider.

Built for students in the [Mega POD](https://mega.management) community who want to launch a fully branded print-on-demand store in a weekend without learning WordPress.

**Key features:**

* Auto-generates a provider-neutral `mkb-agent` admin user with Application Password on new installs
* Copy-as-.env button in Settings for Codex, Claude, Cursor, scripts, or another compatible client
* 50+ REST endpoints covering theme mods, content, palette, CSS, cache, blocks, WooCommerce
* Cache-bypassed /render endpoint for verification
* Snapshot + rollback for every write operation
* Automatic updates from GitHub Releases
* Works with free Kadence + Kadence Blocks (Kadence Pro and WooCommerce optional)
* No outbound network traffic — 100% local API

== Installation ==

1. Upload the plugin ZIP via Plugins → Add New → Upload Plugin
2. Activate the plugin
3. Go to Settings → Mega Kadence Bridge
4. Click "Copy as .env" and paste into an untracked `.env` file in your local project folder
5. Open the project with Codex, Claude Code, Cursor, or another compatible client
6. Start with the authenticated `/capabilities` endpoint, then build and verify

== Frequently Asked Questions ==

= Does this send my data anywhere? =

No. The bridge is a REST API on your own WordPress site. Your selected client talks directly to the site. The plugin does not send data to Mega, OpenAI, Anthropic, or another model provider.

= How do I revoke agent access? =

Go to Users, open the bridge agent account shown in Settings → Mega Kadence Bridge, and revoke the Mega Kadence Bridge Application Password. Alternatively, deactivate or delete the plugin.

= Does this work without Kadence Pro? =

Yes. The plugin works with free Kadence Theme and free Kadence Blocks. Pro features gracefully degrade — endpoints that require Pro simply return an informative error when Pro isn't installed.

= Can I use this with plugins other than Kadence? =

Some endpoints are Kadence-specific (palette, theme mods, Pro feature flags), but most are generic WordPress endpoints that work with any theme.

= What happens if I regenerate the credentials? =

The old Application Password is invalidated and a new one is created. You'll need to update your local .env file with the new value.

== Changelog ==

= 1.6.0 =
* Added a provider-neutral SKILL.md and AI client guide with Codex instructions
* New installs use the `mkb-agent` login and `.mega-kadence-bridge` credential directory
* Existing `claude-bot` installations remain compatible and keep their recorded username
* Reworked WordPress admin and public documentation around the model-agnostic REST boundary

= 1.5.0 =
* New `POST /woo/api-keys/generate` — mints a WooCommerce REST API key pair (consumer key + secret) for the authenticated bridge user, mirroring WooCommerce's own admin key generation (stores a hash of the key + the plaintext secret; secret returned once). This is how the store-drop hands MEGA's product engine write access to a freshly dropped store. Optional body: `description`, `permissions` (read | write | read_write, default read_write). Records an audit snapshot; revoke via WooCommerce > Settings > Advanced > REST API.

= 1.4.0 =
* New `POST /themes/install-from-url` — install a theme from a direct ZIP URL (the missing companion to /plugins/install, which already takes a zip_url). This is how the store-drop installs the Kadence theme itself onto a fresh WordPress. Optional `sha256` is verified before install; `activate` (default true) switches the theme and snapshots the prior one as `theme_switch` (now rollback-able via POST /rollback/{id}). Idempotent when `stylesheet` is supplied.
* `POST /plugins/install` + `/plugins/install-and-activate` now accept an optional `sha256` — when installing a premium plugin from a `zip_url`, the ZIP is downloaded and checksum-verified before install (refuses on mismatch). Backward compatible: omit `sha256` for the prior direct-from-URL behavior.
* Shared `MKB_REST_Controller::download_verified()` helper — the trust boundary for premium artifacts pulled from a private bucket against a manifest-pinned hash.

= 1.3.0 =
* New Production Endpoints class — the "Production Pass" the store-drop-skill runs once per fresh install to take a store from "deployed" to "production-ready". All endpoints are idempotent (return changed:false when already in the target state) and snapshot prior state before any destructive write.
* `POST /permalinks` — set permalink_structure (default `/%category%/%postname%/`) and flush rewrite rules so pretty URLs resolve. Snapshots the old structure.
* `POST /media/disable-thumbnails` — zero the eight core media-size options (Settings > Media) so WP stops generating the generic thumbnail/medium/large copies on upload. Scoped to core sizes only; leaves WooCommerce/Kadence registered sizes intact so the storefront still renders. Idempotent; snapshots each changed option for rollback.
* `POST /litespeed/optimize-images` — enable LiteSpeed Cache image optimization auto-request + WebP delivery via LSCWP's own config API. Skips cleanly (changed:false) when LiteSpeed is not active.
* `POST /spam-protection` — enable FluentForms honeypot (read-modify-write of misc.honeypotStatus, preserving sibling settings) and install + activate limit-login-attempts-reloaded. Does not touch reCAPTCHA. Skips FF part cleanly when FluentForms is inactive.
* `POST /production-pass` — orchestrator that runs all four and returns a combined per-step report.

= 1.0.0 =
* Initial release.
* Activator: creates or preserves the bridge agent user, generates an Application Password, and writes the credentials file
* Settings page with copy-as-.env button and system status
* REST endpoints: core (info, render, cache, plugins, wp-eval), theme (theme_mod, option, palette, css, settings), content (posts CRUD, pages/ensure, menus), media (upload from URL), Kadence (blocks, Pro config, header/footer), WooCommerce (products, categories, orders)
* History / snapshot / rollback system for every write operation
* Plugin-update-checker integration for GitHub Releases updates

== Upgrade Notice ==

= 1.0.0 =
Initial release.

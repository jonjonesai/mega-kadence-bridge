=== Mega Kadence Bridge ===
Contributors: jonjonesai
Tags: kadence, rest-api, claude, ai, automation, woocommerce
Requires at least: 6.0
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A REST API bridge that lets Claude Code operate your Kadence-powered WordPress site. Part of the Mega POD ecosystem.

== Description ==

Mega Kadence Bridge turns your WordPress site into a Claude-controllable system. After a one-click installation, Claude can read and write every Kadence theme setting, create and edit pages using Kadence blocks, manage WooCommerce products, and apply your brand identity across your entire site — all through a private, authenticated REST API.

It creates a dedicated `claude-bot` user with a WordPress Application Password, shows you a copy-paste .env block in Settings, and exposes a clean REST API under `/wp-json/mega-kadence-bridge/v1/`. Everything is local to your server. No data leaves your site.

Built for students in the [Mega POD](https://mega.management) community who want to launch a fully branded print-on-demand store in a weekend without learning WordPress.

**Key features:**

* Auto-generates `claude-bot` admin user with Application Password on activation
* Copy-as-.env button in Settings for instant Claude Code setup
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
4. Click "Copy as .env" and paste into a file called `.env` in your local project folder
5. Install Claude Code and open your project folder
6. Start building with Claude

== Frequently Asked Questions ==

= Does this send my data anywhere? =

No. The bridge is a local REST API on your own WordPress site. Claude Code runs on your computer and talks directly to your site. No data is sent to Mega, Anthropic, or any third party.

= How do I revoke Claude's access? =

Go to Users → claude-bot → Application Passwords, and revoke the Mega Kadence Bridge password. Alternatively, deactivate or delete the plugin.

= Does this work without Kadence Pro? =

Yes. The plugin works with free Kadence Theme and free Kadence Blocks. Pro features gracefully degrade — endpoints that require Pro simply return an informative error when Pro isn't installed.

= Can I use this with plugins other than Kadence? =

Some endpoints are Kadence-specific (palette, theme mods, Pro feature flags), but most are generic WordPress endpoints that work with any theme.

= What happens if I regenerate the credentials? =

The old Application Password is invalidated and a new one is created. You'll need to update your local .env file with the new value.

== Changelog ==

= 1.0.0 =
* Initial release.
* Activator: creates claude-bot user, generates Application Password, writes credentials file
* Settings page with copy-as-.env button and system status
* REST endpoints: core (info, render, cache, plugins, wp-eval), theme (theme_mod, option, palette, css, settings), content (posts CRUD, pages/ensure, menus), media (upload from URL), Kadence (blocks, Pro config, header/footer), WooCommerce (products, categories, orders)
* History / snapshot / rollback system for every write operation
* Plugin-update-checker integration for GitHub Releases updates

== Upgrade Notice ==

= 1.0.0 =
Initial release.

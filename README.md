# Mega Kadence Bridge

> A WordPress plugin that turns any AI agent into a master of Kadence on your site.

MKB is the Kadence-fluent REST surface. It exposes Kadence Theme, Kadence Blocks, Kadence Pro, WooCommerce, and the underlying WP primitives as clean, reversible, agent-friendly endpoints — plus a `/capabilities` discovery call that teaches the agent the doctrine of operating Kadence correctly.

Any Kadence site is in scope: a POD store, a product launch, a course site, a portfolio, an editorial publication, a local-service page, an agency demo. POD stores (the [Store Drop](https://mega.management) outcome) are the first proof, not the only one.

Works with [Claude Code](https://claude.ai/code), Claude Desktop, Cursor, Windsurf, VS Code + Copilot, or anything else that can call an authenticated REST endpoint.

## What It Does

When you activate this plugin, it:

1. Creates a dedicated `claude-bot` user with a WordPress Application Password
2. Locks itself to the current site domain so stale credentials can't operate a cloned site by accident
3. Shows you a copy-paste `.env` block in **Settings → Mega Kadence Bridge**
4. Exposes a clean REST API under `/wp-json/mega-kadence-bridge/v1/` that any AI agent can use to:
   - **Discover** the site's stack and read the Kadence operating doctrine via `/capabilities`
   - **Read and write** Kadence theme settings (all 1,725 of them)
   - **Create, update, and publish** pages with Kadence block markup
   - **Apply** brand colors, fonts, and logo globally via the Kadence palette and theme_mods
   - **Manage** WooCommerce products, categories, and orders (when active)
   - **Flush** LiteSpeed, WP Super Cache, W3 Total Cache, and more
   - **Snapshot every change** and roll back any of them with one call
5. Updates itself from GitHub Releases — no hunting for new versions

## Why You Want This

Without MKB, getting an AI agent to modify a WordPress site means SSH, WP-CLI, raw theme_mod surgery, and a lot of cache-flush fighting. The agent can't see what it's doing and you can't verify it worked.

With MKB, the agent talks to one clean Kadence-fluent API. Everything is verifiable, reversible, and fast. You say *"change the primary color to forest green, swap the homepage hero to the testimonials pattern, and rebuild the header with a sticky transparent variant on the home page only"* and it just works — using palette tokens, Kadence patterns, the Header Builder, and `_kad_*` post meta exactly the way Kadence is meant to be used.

The doctrine the agent follows comes from the bridge itself: every agent that calls `/capabilities` receives a Kadence operating-instructions string telling it to use palette tokens not inline hex, Kadence Blocks not raw HTML, Header Builder not template forks, and `/cache/flush` after significant changes. You don't have to teach each new agent how to drive Kadence — MKB does.

## Installation

### Option A — Upload via WordPress Admin (easiest)

1. Download the latest [release ZIP](https://github.com/jonjonesai/mega-kadence-bridge/releases/latest) from GitHub
2. In WordPress admin: **Plugins → Add New → Upload Plugin**
3. Choose the ZIP file → **Install Now** → **Activate**
4. Go to **Settings → Mega Kadence Bridge**
5. Click **Copy as .env** and paste the block into a file called `.env` in your local project folder
6. Install [Claude Code](https://claude.ai/code) and open your project folder — Claude reads `.env` automatically

### Option B — SSH (for advanced users)

```bash
cd /path/to/wordpress/wp-content/plugins/
curl -L -o mega-kadence-bridge.zip https://github.com/jonjonesai/mega-kadence-bridge/releases/latest/download/mega-kadence-bridge.zip
unzip mega-kadence-bridge.zip
wp plugin activate mega-kadence-bridge
cat /path/to/wordpress/wp-content/.claude-bridge/credentials.json
```

The credentials file gives you the same values as the Settings page, suitable for scripting.

## Requirements

| Requirement | Minimum | Recommended |
|---|---|---|
| WordPress | 6.0 | 6.4+ |
| PHP | 7.4 | 8.1+ |
| Kadence Theme | Free | Kadence Pro |
| Kadence Blocks | Free | Kadence Blocks Pro |
| WooCommerce | — | 8.0+ |

The plugin works with just free Kadence + Kadence Blocks. Kadence Pro and WooCommerce unlock additional endpoints but are not required.

## API Overview

All endpoints require HTTP Basic Authentication with the `claude-bot` username and the Application Password shown on the Settings page. They also require the request to be hitting the **locked domain** the bridge was activated on — see [Domain locking](#domain-locking) below.

### Discovery

| Endpoint | Method | Purpose |
|---|---|---|
| `/capabilities` | GET | Site context, Kadence stack inventory, endpoint catalog, and Kadence operating doctrine. **The first call any agent should make.** |

### Core

| Endpoint | Method | Purpose |
|---|---|---|
| `/info` | GET | Site, theme, plugin, PHP versions |
| `/render?url=...` | GET | Cache-bypassed HTML of any page |
| `/cache/flush` | POST | Clear every cache layer on the site |
| `/plugins` | GET | List all installed plugins |

### Theme Customization

| Endpoint | Method | Purpose |
|---|---|---|
| `/theme-mod/{key}` | GET/POST | Read or write a single Kadence theme_mod |
| `/theme-mods/batch` | POST | Write multiple theme_mods in one call |
| `/option/{key}` | GET/POST | Read or write a WordPress option |
| `/palette` | GET/POST | Kadence global color palette |
| `/css` | GET/POST | Site-wide custom CSS |
| `/settings` | GET | All Kadence-prefixed theme_mods |
| `/settings/all` | GET | Every theme_mod on the site |

### Content

| Endpoint | Method | Purpose |
|---|---|---|
| `/posts` | GET | List posts with filters |
| `/posts/{id}` | GET/POST | Read or update a single post |
| `/posts/create` | POST | Create a new post or page |
| `/posts/find?slug=...` | GET | Find a post by slug (idempotency helper) |
| `/pages/ensure` | POST | Create page only if it doesn't exist |
| `/menus` | GET | List navigation menus |
| `/menus/create` | POST | Create a new menu |
| `/menus/{id}/items` | POST | Add a menu item |

### Media

| Endpoint | Method | Purpose |
|---|---|---|
| `/media` | GET | List media library |
| `/media/upload-from-url` | POST | Upload a remote image into the media library |

### Kadence

| Endpoint | Method | Purpose |
|---|---|---|
| `/blocks` | GET | List all registered Kadence blocks |
| `/kadence-pro/config` | GET/POST | Kadence Pro feature flags |
| `/kadence-pro/preset/pod` | POST | Enable POD-recommended Pro modules in one call |
| `/header` | GET | Header configuration snapshot |
| `/footer` | GET | Footer configuration snapshot |

### WooCommerce (when active)

| Endpoint | Method | Purpose |
|---|---|---|
| `/woo/status` | GET | WooCommerce + Kadence Woo addons status |
| `/woo/settings` | GET/POST | WC-related Kadence settings |
| `/woo/products` | GET | List products |
| `/woo/products/{id}` | GET/POST | Read or update a product |
| `/woo/products/create` | POST | Create a product |
| `/woo/categories` | GET | List product categories |
| `/woo/categories/create` | POST | Create a product category |
| `/woo/orders` | GET | List orders |

### History / Rollback

| Endpoint | Method | Purpose |
|---|---|---|
| `/history` | GET | List recent snapshots |
| `/history/{id}` | GET | Get a specific snapshot |
| `/rollback/{id}` | POST | Revert a change |

Every write operation captures a snapshot of the previous state. If Claude (or you) makes a change you don't like, roll it back with a single call.

## Example — Hide the Page Title on the About Page

```bash
BRIDGE_URL="https://yoursite.com/wp-json/mega-kadence-bridge/v1"
AUTH="claude-bot:abcd 1234 wxyz 5678 qrst 9012"

# 1. Find the page ID by slug
PAGE_ID=$(curl -s -u "$AUTH" "$BRIDGE_URL/posts/find?slug=about" | jq -r '.id')

# 2. Hide the page title by setting the per-page meta
curl -s -X POST -u "$AUTH" \
  -H "Content-Type: application/json" \
  -d '{"meta": {"_kad_post_title": "hide"}}' \
  "$BRIDGE_URL/posts/$PAGE_ID"

# 3. Flush all caches
curl -s -X POST -u "$AUTH" "$BRIDGE_URL/cache/flush"

# 4. Verify the hero section is gone
curl -s -u "$AUTH" "$BRIDGE_URL/render?url=/about/" | jq -r '.html' | grep -c 'page-hero-section'
# Should return 0
```

## Security

- All endpoints require authentication as an administrator
- The `claude-bot` user can be revoked at any time by deleting its Application Password in **Users → Profile → Application Passwords** or by deactivating the plugin
- Credentials are stored in `wp-content/.claude-bridge/credentials.json`, protected by `.htaccess` and `index.php`
- File permissions on credentials.json are set to `0600` (owner-read-only)
- No outbound network traffic except the GitHub update checker
- No data is sent to Mega or Anthropic — the bridge is 100% local to your server

### Domain locking

On activation MKB records the current site host (e.g. `example.com`) as the **locked domain**. Every REST request thereafter checks the request's host against the locked value. If they differ — typically because a snapshot of the site has been restored to a staging URL or the site has been migrated — every endpoint returns:

```json
{
  "code": "mkb_domain_mismatch",
  "message": "Mega Kadence Bridge is locked to example.com but this request is hitting staging.example.com. ...",
  "data": {
    "status": 403,
    "locked_domain": "example.com",
    "current_domain": "staging.example.com"
  }
}
```

This is intentional: it prevents a stale `credentials.json` from operating an unintended copy of the site. To unlock for the new domain, an administrator visits **Settings → Mega Kadence Bridge** and re-locks. Existing installs upgrading from < 1.2.0 with no recorded lock fail open and adopt the current domain on next activation.

## Updates

Updates are delivered through GitHub Releases. When a new version is tagged, you'll see the update notification in WordPress **Plugins → Update Now**, same as any plugin from the WP Plugin Directory.

## License

GPL v2 or later — [see LICENSE](LICENSE).

## Acknowledgements

Built on top of:

- [Kadence Theme](https://www.kadencewp.com/) by StellarWP
- [Kadence Blocks](https://github.com/stellarwp/kadence-blocks)
- [plugin-update-checker](https://github.com/YahnisElsts/plugin-update-checker) by YahnisElsts
- [Claude Code](https://claude.ai/code) by Anthropic
